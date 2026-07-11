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
