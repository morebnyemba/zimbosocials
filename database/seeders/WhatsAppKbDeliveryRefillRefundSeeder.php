<?php

namespace Database\Seeders;

use App\Models\WhatsAppKnowledge;
use Illuminate\Database\Seeder;

/**
 * Richer KB entries: an About/positioning entry plus the three most-asked
 * policy topics — delivery time, refill guarantee, and refunds. Idempotent
 * (keyed on title) — re-running updates in place, and it upgrades the two thin
 * starters from WhatsAppKnowledgeBaseSeeder ("Delivery time", "Refunds").
 *
 * Positioning note: ZimboSocials is framed as a *platform powered by a network
 * of social media marketers and experts*, NOT a plain SMM panel — keep this
 * consistent with the assistant's system prompt (Simbah persona).
 *
 * Refund answer mirrors the actual auto-refund logic in OrderStatusSyncService
 * (full refund on cancel/fail, proportional on partial, credited to wallet).
 * Delivery: 5 minutes to 24 hours (operator-confirmed 2026-07-14).
 *
 * ⚠️ VERIFY the REFILL wording: it stays general ("the refill period shown on
 * the service") because refill is per-service (is_refill / refill_days). If you
 * advertise a standard window (e.g. "30-day refill"), edit it in
 * Admin → WA Assistant → Knowledge Base.
 */
class WhatsAppKbDeliveryRefillRefundSeeder extends Seeder
{
    public function run(): void
    {
        $entries = [
            [
                'title' => 'About ZimboSocials',
                'question' => 'What is ZimboSocials, who are you, tell me about your company, are you an SMM panel, what do you do',
                'answer' => "👋 We're *ZimboSocials* — a platform powered by a real network of *social media marketers and growth experts*. We're not just an SMM panel or a piece of software: our team helps people and businesses across Zimbabwe and beyond grow their social media — more followers, likes, views, subscribers and engagement — delivered fast and paid with local methods like EcoCash, all right here on WhatsApp.\n\nTell me what you'd like to grow and I'll set you up! 🚀",
                'keywords' => 'about zimbosocials who what is company platform smm panel network experts marketers agency what do you do tell me about your service business simbah ndeipi chii masocial media tell ngobani liyini',
                'category' => 'general',
            ],
            [
                'title' => 'Delivery time',
                'question' => 'How long does delivery take, how fast, when will my order start and finish, delivery speed',
                'answer' => "⏱️ Orders usually *start within 5 minutes* of you confirming, and complete anywhere up to *24 hours* depending on the service and quantity — smaller orders are often much faster. Each service also shows its average speed before you order.\n\nSend *track* with your order number anytime to see live progress.",
                'keywords' => 'delivery time how long fast speed when start finish complete begin quick hours minutes eta wait nguva kukurumidza kumhanya masaha isikhathi masekethe ukusheshisa nini',
                'category' => 'orders',
            ],
            [
                'title' => 'Refill guarantee',
                'question' => 'Do you offer refill, what if followers drop, is there a refill guarantee, will you top up if numbers fall',
                'answer' => "🔁 Many of our *followers* and *subscribers* services are *refill-guaranteed*: if some drop off within the refill period shown on that service, we top them back up for free. Not every service includes refill, so check the service details before you order.\n\nNoticed a drop? Send *support* and we'll sort it out.",
                'keywords' => 'refill guarantee drop dropped fell falling top up replace refills lost followers subscribers guarantee warranty maintain kudonha kudzikira kuwedzera zvadzoka refill kuzadzisa ukwehla ayancipha buyisela',
                'category' => 'orders',
            ],
            [
                'title' => 'Refunds',
                'question' => 'Can I get a refund, what if my order fails or is cancelled, do I get my money back, partial delivery refund',
                'answer' => "💰 Yes — refunds are automatic:\n• If an order is *cancelled* or can't be delivered, the *full charge* goes back to your wallet.\n• If only *part* of an order is delivered, you're automatically refunded for the undelivered portion.\n\nThe money returns to your *wallet balance* (type *balance* to check) — you can use it on your next order right away. For anything else, type *support* to open a ticket.",
                'keywords' => 'refund refunds money back cash return returned failed fail cancel cancelled canceled partial not delivered undelivered reimburse credit wallet mari kudzoserwa kudzoka mari yangu kudzosewa refund imali ukubuyiselwa imali ibuyile',
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
