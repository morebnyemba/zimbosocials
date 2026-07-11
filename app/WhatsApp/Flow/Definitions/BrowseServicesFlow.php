<?php

namespace App\WhatsApp\Flow\Definitions;

use App\Models\Service;
use App\WhatsApp\Flow\AbstractFlow;
use App\WhatsApp\Flow\FlowResult;
use App\WhatsApp\Session\SessionContext;

/**
 * Browse the service catalogue. Flow id: 'browse'.
 * States: start (list categories) → pick_category (show services in the chosen
 * category). The user replies with a category number or name.
 */
class BrowseServicesFlow extends AbstractFlow
{
    public function id(): string
    {
        return 'browse';
    }

    public function prompt(string $state, SessionContext $ctx): FlowResult
    {
        $categories = Service::active()
            ->select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->filter()
            ->values();

        if ($categories->isEmpty()) {
            return FlowResult::fail("No services are available right now. Please check back soon.");
        }

        $ctx->set('browse_categories', $categories->all());

        $list = $categories->map(fn ($c, $i) => ($i + 1).". {$c}")->implode("\n");

        return FlowResult::step(
            "📂 *Browse services*\n\nReply with a category number:\n\n{$list}",
            'pick_category'
        );
    }

    public function handle(string $state, string $input, SessionContext $ctx): FlowResult
    {
        $categories = collect($ctx->get('browse_categories', []));
        if ($categories->isEmpty()) {
            return $this->prompt('start', $ctx);
        }

        // Accept either an index or a category name.
        $choice = trim($input);
        $category = null;
        if (is_numeric($choice)) {
            $category = $categories->get((int) $choice - 1);
        } else {
            $category = $categories->first(fn ($c) => mb_strtolower($c) === mb_strtolower($choice));
        }

        if (! $category) {
            return FlowResult::step("Please reply with a valid category number, or type *cancel*.", 'pick_category');
        }

        $services = Service::active()
            ->byCategory($category)
            ->orderBy('display_order')
            ->limit(10)
            ->get();

        if ($services->isEmpty()) {
            return FlowResult::complete("No active services in *{$category}* right now.");
        }

        $lines = $services->map(function (Service $s) {
            $rate = number_format((float) $s->rate, 2);
            return "• *{$s->name}*\n   \${$rate}/1k · min {$s->min_qty} · max {$s->max_qty}";
        })->implode("\n\n");

        $msg = "📂 *{$category}*\n\n{$lines}\n\nType *order* to place an order, or *menu* to go back.";

        return FlowResult::complete($msg);
    }
}
