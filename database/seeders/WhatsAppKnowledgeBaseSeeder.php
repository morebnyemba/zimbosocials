<?php

namespace Database\Seeders;

use App\Models\WhatsAppKnowledge;
use Illuminate\Database\Seeder;

/**
 * Starter FAQ entries for the WhatsApp assistant. Idempotent: keyed on title,
 * so re-running updates rather than duplicates. Admins can edit these in the
 * WhatsApp → Knowledge Base screen.
 */
class WhatsAppKnowledgeBaseSeeder extends Seeder
{
    public function run(): void
    {
        $entries = [
            [
                'title' => 'Place an order',
                'question' => 'How do I place an order or buy followers likes views',
                'answer' => "Type *order*, choose a platform and service, paste your link and enter a quantity. You'll see the total before you confirm.",
                'keywords' => 'order buy place followers likes views how purchase',
                'category' => 'orders',
            ],
            [
                'title' => 'Add funds',
                'question' => 'How do I deposit add funds top up my wallet',
                'answer' => "Type *deposit* and follow the secure link to top up (EcoCash, card and more). Your balance updates automatically once payment is confirmed.",
                'keywords' => 'deposit add funds top up wallet money pay ecocash',
                'category' => 'wallet',
            ],
            [
                'title' => 'Delivery time',
                'question' => 'How long does delivery take how fast when will my order start',
                'answer' => "Most orders start within a few minutes and complete based on the service's speed. Type *track* with your order number for live status.",
                'keywords' => 'delivery time how long fast speed when start complete',
                'category' => 'orders',
            ],
            [
                'title' => 'Track an order',
                'question' => 'How do I track my order check status',
                'answer' => "Type *track* then send your order number, or type *orders* to see all your recent orders and their status.",
                'keywords' => 'track order status check progress where',
                'category' => 'orders',
            ],
            [
                'title' => 'Is my password safe',
                'question' => 'Do you need my password is it safe secure',
                'answer' => "We never ask for your password on WhatsApp. Linking your account uses a one-time code sent to your email.",
                'keywords' => 'password safe secure security privacy login',
                'category' => 'account',
            ],
            [
                'title' => 'Sponsored adverts',
                'question' => 'Do you run sponsored adverts advertising facebook instagram ads promote my business get customers how much per week',
                'answer' => "Yes! Alongside growing your page, we run *sponsored adverts* on Facebook & Instagram that put your business in front of new customers.\n\nWeekly packages:\n• *\$15/week* — starter\n• *\$30/week* — standard\n• *\$50/week* — maximum reach\n\nThe bigger the package, the more people your advert reaches. Tell me what you're promoting and your budget, and we'll set it up for you.",
                'keywords' => 'sponsored advert adverts advertising ads facebook ads instagram ads promote promotion boost campaign marketing customers sales business weekly package 15 30 50 price cost',
                'category' => 'advertising',
            ],
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
            [
                'title' => 'Refunds',
                'question' => 'Can I get a refund what if my order fails',
                'answer' => "If an order can't be delivered, the charge is automatically returned to your wallet. For anything else, type *support* to open a ticket.",
                'keywords' => 'refund money back failed cancel not delivered',
                'category' => 'wallet',
            ],
        ];

        foreach ($entries as $e) {
            WhatsAppKnowledge::updateOrCreate(
                ['title' => $e['title']],
                array_merge($e, ['locale' => 'en', 'status' => true]),
            );
        }
    }
}
