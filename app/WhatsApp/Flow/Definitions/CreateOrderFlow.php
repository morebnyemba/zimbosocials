<?php

namespace App\WhatsApp\Flow\Definitions;

use App\Models\Service;
use App\Services\OrderService;
use App\Services\Upstream\OrderDispatchService;
use App\WhatsApp\Flow\AbstractFlow;
use App\WhatsApp\Flow\FlowResult;
use App\WhatsApp\Messaging\ResponseBuilder;
use App\WhatsApp\Session\SessionContext;

/**
 * Place an order end to end. Flow id: 'order'.
 * States: pick_category → pick_service → enter_link → enter_quantity → confirm.
 * The actual charge + order creation goes through OrderService::placeOrder,
 * which is atomic and balance-locked — this flow only collects input.
 */
class CreateOrderFlow extends AbstractFlow
{
    public function __construct(
        ResponseBuilder $rb,
        private readonly OrderService $orders,
        private readonly OrderDispatchService $dispatch,
    ) {
        parent::__construct($rb);
    }

    public function id(): string
    {
        return 'order';
    }

    public function prompt(string $state, SessionContext $ctx): FlowResult
    {
        return $this->showCategories($ctx);
    }

    public function handle(string $state, string $input, SessionContext $ctx): FlowResult
    {
        return match ($state) {
            'pick_category' => $this->pickCategory($input, $ctx),
            'pick_service' => $this->pickService($input, $ctx),
            'enter_link' => $this->enterLink($input, $ctx),
            'enter_quantity' => $this->enterQuantity($input, $ctx),
            'confirm' => $this->confirm($input, $ctx),
            default => $this->showCategories($ctx),
        };
    }

    private function showCategories(SessionContext $ctx): FlowResult
    {
        $categories = Service::active()->select('category')->distinct()->orderBy('category')
            ->pluck('category')->filter()->values();

        if ($categories->isEmpty()) {
            return FlowResult::fail('No services are available right now.');
        }

        $ctx->set('order_categories', $categories->all());
        $list = $categories->map(fn ($c, $i) => ($i + 1).". {$c}")->implode("\n");

        return FlowResult::step("🚀 *New order*\n\nWhich platform? Reply with a number:\n\n{$list}", 'pick_category');
    }

    private function pickCategory(string $input, SessionContext $ctx): FlowResult
    {
        $categories = collect($ctx->get('order_categories', []));
        $category = $this->resolveChoice($input, $categories);
        if (! $category) {
            return FlowResult::step('Please reply with a valid number, or type *cancel*.', 'pick_category');
        }

        $services = Service::active()->byCategory($category)->orderBy('display_order')->limit(10)->get();
        if ($services->isEmpty()) {
            return FlowResult::step("No services in *{$category}*. Pick another number.", 'pick_category');
        }

        $ctx->set('order_service_ids', $services->pluck('id')->all());
        $lines = $services->values()->map(function (Service $s, $i) {
            $rate = number_format((float) $s->rate, 2);

            return ($i + 1).". *{$s->name}* — \${$rate}/1k (min {$s->min_qty}, max {$s->max_qty})";
        })->implode("\n");

        return FlowResult::step("*{$category}*\n\nChoose a service:\n\n{$lines}", 'pick_service');
    }

    private function pickService(string $input, SessionContext $ctx): FlowResult
    {
        $ids = collect($ctx->get('order_service_ids', []));
        $idx = (int) preg_replace('/\D+/', '', $input) - 1;
        $serviceId = $ids->get($idx);
        if (! $serviceId) {
            return FlowResult::step('Please reply with a valid service number, or type *cancel*.', 'pick_service');
        }

        $service = Service::active()->find($serviceId);
        if (! $service) {
            return FlowResult::fail('That service is no longer available. Type *order* to start again.');
        }

        $ctx->set('order_service_id', $service->id);

        return FlowResult::step(
            "🔗 Send the *link* for your order (the profile/post URL for *{$service->name}*).",
            'enter_link'
        );
    }

    private function enterLink(string $input, SessionContext $ctx): FlowResult
    {
        $link = trim($input);
        if (! filter_var($link, FILTER_VALIDATE_URL)) {
            return FlowResult::step("That doesn't look like a valid URL. Send the full link (starting with https://), or type *cancel*.", 'enter_link');
        }

        $ctx->set('order_link', $link);
        $service = Service::find($ctx->get('order_service_id'));

        return FlowResult::step(
            "🔢 How many? (min *{$service->min_qty}*, max *{$service->max_qty}*)",
            'enter_quantity'
        );
    }

    private function enterQuantity(string $input, SessionContext $ctx): FlowResult
    {
        $qty = (int) preg_replace('/\D+/', '', $input);
        $service = Service::find($ctx->get('order_service_id'));
        if (! $service) {
            return FlowResult::fail('That service is no longer available. Type *order* to start again.');
        }

        if ($qty < $service->min_qty || $qty > $service->max_qty) {
            return FlowResult::step("Please enter a number between *{$service->min_qty}* and *{$service->max_qty}*.", 'enter_quantity');
        }

        $ctx->set('order_quantity', $qty);
        $charge = $service->calculateCharge($qty);
        $user = $this->user($ctx);
        $cur = $user?->currency ?? 'USD';
        $balance = (float) ($user?->balance ?? 0);

        $summary = "🧾 *Confirm your order*\n\n"
            ."Service: {$service->name}\n"
            ."Quantity: {$qty}\n"
            ."Link: {$ctx->get('order_link')}\n"
            .'Charge: *'.$this->money($charge, $cur)."*\n"
            .'Balance: '.$this->money($balance, $cur)."\n\n";

        if ($balance < $charge) {
            $summary .= "⚠️ Not enough balance. Type *deposit* to top up, or *cancel*.";

            return FlowResult::step($summary, 'confirm');
        }

        $summary .= 'Reply *YES* to place the order, or *cancel*.';

        return FlowResult::step($summary, 'confirm');
    }

    private function confirm(string $input, SessionContext $ctx): FlowResult
    {
        if (! in_array(mb_strtolower(trim($input)), ['yes', 'y', 'confirm', 'ok', 'yebo'], true)) {
            return FlowResult::fail('Order not placed. Type *order* to start over or *menu* to go back.');
        }

        $user = $this->user($ctx);
        $service = Service::find($ctx->get('order_service_id'));
        if (! $user || ! $service) {
            return FlowResult::fail('Something went wrong. Type *order* to try again.');
        }

        $res = $this->orders->placeOrder(
            $user,
            $service,
            (string) $ctx->get('order_link'),
            (int) $ctx->get('order_quantity'),
            $this->dispatch,
            'WhatsApp order'
        );

        if (! empty($res['ok'])) {
            $order = $res['order'];

            return FlowResult::complete(
                "✅ *Order placed!*\n\n".$this->rb->orderCard($order->fresh('service'), $user->currency ?? 'USD')
                ."\n\nType *track* anytime to check progress."
            );
        }

        // Map service-layer validation failures back to the right step.
        $field = $res['field'] ?? null;
        if ($field === 'link') {
            return FlowResult::step('❌ '.$res['error']."\n\nSend a different *link*, or type *cancel*.", 'enter_link');
        }
        if ($field === 'quantity') {
            return FlowResult::step('❌ '.$res['error']."\n\nEnter a different *quantity*, or type *cancel*.", 'enter_quantity');
        }
        if (($res['code'] ?? null) === 402) {
            return FlowResult::fail("💸 ".$res['error']."\n\nType *deposit* to top up your wallet.");
        }

        return FlowResult::fail('⚠️ '.($res['error'] ?? 'Could not place the order.').' Type *menu* to go back.');
    }

    private function resolveChoice(string $input, $collection)
    {
        $choice = trim($input);
        if (is_numeric($choice)) {
            return $collection->get((int) $choice - 1);
        }

        return $collection->first(fn ($c) => mb_strtolower((string) $c) === mb_strtolower($choice));
    }
}
