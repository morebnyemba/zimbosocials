<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Two FAQs the assistant kept having to improvise, both straight off real
 * chats: "is this legit / are you scammers?" (the single biggest objection) and
 * "I paid and nothing arrived". Grounding them in the knowledge base keeps the
 * answers accurate and consistent instead of invented on the spot.
 *
 * Idempotent on title, so an admin's later edits in WhatsApp → Knowledge Base
 * survive re-runs.
 */
return new class extends Migration
{
    private function entries(): array
    {
        return [
            [
                'title' => 'Are we legit',
                'question' => 'Is this legit real genuine scam scammers can I trust you trustworthy safe are you stealing money fake ndinovimba here munobira',
                'answer' => "We completely understand the worry — there are a lot of scams out there. 🙏 Here's how we keep you safe:\n\n"
                    ."• We *never* ask for your password — only your public profile or post link.\n"
                    ."• Start with a small order and see it work before you spend more.\n"
                    ."• If an order can't be delivered, the charge is *automatically refunded* to your wallet — you don't have to chase us.\n"
                    ."• Every order is tracked: type *orders* for your history, or *track* for live status.\n"
                    .'• A real person from our team can take over this chat any time — just ask for a human.',
                'keywords' => 'legit legitimate real genuine scam scammer fake trust trustworthy safe secure stealing robbed cheated munobira ndinovimba kubirwa nekubirwa tanzwa mbavha makabira hamusi proof reviews',
                'category' => 'trust',
            ],
            [
                'title' => 'Order not delivered',
                'question' => 'My order has not arrived not delivered nothing happened I paid but got nothing missing late unfulfilled stuck incomplete order haina kusvika',
                'answer' => "Sorry about that — let's sort it out. 🙏\n\n"
                    ."1. Type *track* and send your order number to see live status. Many services deliver *gradually* over minutes to hours, so it may still be on its way.\n"
                    ."2. If it shows *partial*, the part that wasn't delivered is *automatically refunded* to your wallet.\n"
                    ."3. If it couldn't be delivered at all, the full charge is refunded automatically — check *balance*.\n\n"
                    .'Still not right? Ask for a *human* and a real person will take over this chat and fix it for you.',
                'keywords' => 'not delivered no delivery missing order nothing happened didnt arrive late stuck incomplete partial unfulfilled refund where is my order haina kusvika hapana',
                'category' => 'orders',
            ],
        ];
    }

    public function up(): void
    {
        if (! Schema::hasTable('whatsapp_knowledge_base')) {
            return;
        }

        foreach ($this->entries() as $entry) {
            if (DB::table('whatsapp_knowledge_base')->where('title', $entry['title'])->exists()) {
                continue;
            }

            DB::table('whatsapp_knowledge_base')->insert(array_merge($entry, [
                'locale' => 'en',
                'status' => true,
                'hits' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('whatsapp_knowledge_base')) {
            return;
        }

        DB::table('whatsapp_knowledge_base')
            ->whereIn('title', array_column($this->entries(), 'title'))
            ->delete();
    }
};
