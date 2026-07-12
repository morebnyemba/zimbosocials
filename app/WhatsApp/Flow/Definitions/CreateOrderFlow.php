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
        // AI fast-forward: if the orchestrator extracted a service/platform (and
        // maybe link/quantity), resolve it and jump to the first missing step.
        // We always stop at the confirm step — never auto-place an order.
        $serviceId = $ctx->pullPrefill('service_id');
        $platform = $ctx->pullPrefill('platform');
        $serviceName = $ctx->pullPrefill('service');
        $link = $ctx->pullPrefill('link');
        $quantity = $ctx->pullPrefill('quantity');

        // Gemini may pass an exact catalogue service_id; otherwise match by name.
        $service = ($serviceId && is_numeric($serviceId))
            ? Service::active()->find((int) $serviceId)
            : null;

        if (! $service && ($platform || $serviceName)) {
            $service = $this->resolveService((string) $platform, (string) $serviceName);
        }

        // Mid-flow AI redirect back into 'order' (e.g. "make it 2000 instead"):
        // keep the service the user already chose so their progress survives.
        if (! $service && ! $platform && ! $serviceName && $ctx->has('order_service_id')) {
            $service = Service::active()->find((int) $ctx->get('order_service_id'));
        }

        if ($service) {
            $ctx->set('order_service_id', $service->id);

            if ($link && ($normalized = $this->normalizeLink((string) $link))) {
                $ctx->set('order_link', $normalized);
            }
            $qty = (int) preg_replace('/\D+/', '', (string) $quantity);
            if ($qty >= $service->min_qty && $qty <= $service->max_qty) {
                $ctx->set('order_quantity', $qty);
            }

            if (! $ctx->has('order_link')) {
                return FlowResult::step("🔗 Send the *link* for your *{$service->name}* order.", 'enter_link');
            }
            if (! $ctx->has('order_quantity')) {
                return FlowResult::step("🔢 How many? (min *{$service->min_qty}*, max *{$service->max_qty}*)", 'enter_quantity');
            }

            return $this->toConfirm($service, (int) $ctx->get('order_quantity'), $ctx);
        }

        return $this->showCategories($ctx);
    }

    /** Best-effort match of a service from an AI-extracted platform + name. */
    private function resolveService(string $platform, string $name): ?Service
    {
        $q = Service::active();
        if ($platform !== '') {
            $q->where('category', 'like', "%{$platform}%");
        }
        if ($name !== '') {
            $q->where('name', 'like', "%{$name}%");
        }

        // Only auto-select when it's unambiguous.
        $matches = $q->limit(2)->get();

        return $matches->count() === 1 ? $matches->first() : null;
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

        // WhatsApp lists carry at most 10 rows — fall back to a numbered text
        // list for larger catalogues (typing a number/name still works).
        if ($categories->count() <= 10) {
            $rows = $categories->map(fn ($c, $i) => [
                'id' => 'fs:'.($i + 1),
                'title' => (string) $c,
            ])->all();

            return FlowResult::step('🚀 Which platform would you like to grow?', 'pick_category')
                ->withList('Choose platform', [['title' => 'Platforms', 'rows' => $rows]], 'New order', 'Or type its name');
        }

        $list = $categories->map(fn ($c, $i) => ($i + 1).". {$c}")->implode("\n");

        return FlowResult::step("🚀 *New order*\n\nWhich platform? Reply with a number:\n\n{$list}", 'pick_category');
    }

    private function pickCategory(string $input, SessionContext $ctx): FlowResult
    {
        $categories = collect($ctx->get('order_categories', []));
        $category = $this->resolveChoice($input, $categories);
        if (! $category) {
            return FlowResult::retry('Please reply with a valid number, or type *cancel*.', 'pick_category');
        }

        $services = Service::active()->byCategory($category)->orderBy('display_order')->limit(10)->get();
        if ($services->isEmpty()) {
            return FlowResult::step("No services in *{$category}*. Pick another number.", 'pick_category');
        }

        $ctx->set('order_service_ids', $services->pluck('id')->all());
        $rows = $services->values()->map(function (Service $s, $i) {
            $rate = number_format((float) $s->rate, 2);

            return [
                'id' => 'fs:'.($i + 1),
                'title' => $s->name,
                'description' => "\${$rate}/1k · min {$s->min_qty} · max {$s->max_qty}",
            ];
        })->all();

        return FlowResult::step("Pick a *{$category}* service:", 'pick_service')
            ->withList('Choose service', [['title' => $category, 'rows' => $rows]], null, 'Prices are per 1,000');
    }

    private function pickService(string $input, SessionContext $ctx): FlowResult
    {
        $ids = collect($ctx->get('order_service_ids', []));
        $idx = (int) preg_replace('/\D+/', '', $input) - 1;
        $serviceId = $ids->get($idx);
        if (! $serviceId) {
            return FlowResult::retry('Please reply with a valid service number, or type *cancel*.', 'pick_service');
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
        $link = $this->normalizeLink($input);
        if ($link === null) {
            return FlowResult::retry("That doesn't look like a valid link. Send the profile/post URL (e.g. tiktok.com/@yourname), or type *cancel*.", 'enter_link');
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
            return FlowResult::retry("Please enter a number between *{$service->min_qty}* and *{$service->max_qty}*.", 'enter_quantity');
        }

        $ctx->set('order_quantity', $qty);

        return $this->toConfirm($service, $qty, $ctx);
    }

    /** Build the confirmation summary (shared by the manual + AI fast-forward paths). */
    private function toConfirm(Service $service, int $qty, SessionContext $ctx): FlowResult
    {
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
            $short = $charge - $balance;
            // Pre-fill the deposit amount so "deposit" jumps straight to methods.
            $ctx->set('_prefill_amount', (float) ceil($charge));

            return FlowResult::step(
                $summary."⚠️ You're a bit short — you need *".$this->money($short, $cur)."* more.\n\n"
                .'Top up first (I\'ve got the amount ready 👍), then just place your order again.',
                'confirm'
            )->withButtons([
                ['id' => 'fl_deposit', 'title' => '💰 Deposit'],
                ['id' => 'fs:cancel', 'title' => '✖ Cancel'],
            ]);
        }

        return FlowResult::step($summary.'Ready to place it?', 'confirm')->withButtons([
            ['id' => 'fs:yes', 'title' => '✅ Place order'],
            ['id' => 'fs:cancel', 'title' => '✖ Cancel'],
        ]);
    }

    private function confirm(string $input, SessionContext $ctx): FlowResult
    {
        $t = mb_strtolower(trim($input));

        if (! in_array($t, ['yes', 'y', 'confirm', 'ok', 'yebo'], true)) {
            // Only an explicit "no" cancels; anything else (e.g. "make it 2000
            // instead") is handed to the AI, which can adjust and re-confirm.
            if (in_array($t, ['no', 'n', 'cancel', 'stop', 'kwete', 'hatshi'], true)) {
                return FlowResult::fail('Order not placed. Type *order* to start over or *menu* to go back.');
            }

            return FlowResult::retry('Tap *✅ Place order* (or reply *YES*) to confirm — or *✖ Cancel* to stop.', 'confirm')
                ->withButtons([
                    ['id' => 'fs:yes', 'title' => '✅ Place order'],
                    ['id' => 'fs:cancel', 'title' => '✖ Cancel'],
                ]);
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

    /**
     * Accept links the way people actually type them on WhatsApp —
     * "tiktok.com/@name" gets an https:// prefix before validation.
     */
    private function normalizeLink(string $link): ?string
    {
        $link = trim($link);
        if ($link === '' || preg_match('/\s/', $link)) {
            return null;
        }
        if (! preg_match('#^https?://#i', $link)) {
            $link = 'https://'.$link;
        }

        $valid = filter_var($link, FILTER_VALIDATE_URL)
            && str_contains((string) parse_url($link, PHP_URL_HOST), '.');

        return $valid ? $link : null;
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
