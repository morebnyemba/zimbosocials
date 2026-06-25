<?php

// database/seeders/DatabaseSeeder.php

namespace Database\Seeders;

use App\Models\ManualPaymentDetail;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── Admin user ────────────────────────────────────────────────────────
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@zimsocials.co.zw',
            'password' => Hash::make('password'),
            'balance' => 100.00,
            'referral_code' => User::generateReferralCode(),
            'role' => 'admin',
            'locale' => 'sn',
            'is_active' => true,
        ]);
        $admin->generateApiKey();

        // ── Marketer / reseller demo ──────────────────────────────────────────
        $marketer = User::create([
            'name' => 'Tendai Moyo',
            'email' => 'tendai@example.com',
            'password' => Hash::make('password'),
            'balance' => 24.50,
            'referral_code' => User::generateReferralCode(),
            'role' => 'marketer',
            'locale' => 'sn',
            'is_active' => true,
        ]);
        $marketer->generateApiKey();

        // ── Regular user demo ─────────────────────────────────────────────────
        $customer = User::create([
            'name' => 'Chiedza Dube',
            'email' => 'chiedza@example.com',
            'password' => Hash::make('password'),
            'balance' => 10.00,
            'referral_code' => User::generateReferralCode(),
            'role' => 'user',
            'locale' => 'en',
            'is_active' => true,
        ]);
        $customer->generateApiKey();

        // ── Services ──────────────────────────────────────────────────────────
        $services = [
            // ── Instagram ──
            ['category' => 'instagram', 'name' => 'Instagram Followers (Active Real)', 'name_sn' => 'Instagram — Vateveri Vari Kuita', 'rate' => 1.20, 'min_qty' => 100, 'max_qty' => 50000, 'is_refill' => true, 'avg_time_minutes' => 60],
            ['category' => 'instagram', 'name' => 'Instagram Likes (High Quality)', 'name_sn' => 'Instagram — Zvinodikwa Zvakakwana', 'rate' => 0.50, 'min_qty' => 50, 'max_qty' => 20000, 'is_refill' => false, 'avg_time_minutes' => 15],
            ['category' => 'instagram', 'name' => 'Instagram Video Views', 'name_sn' => 'Instagram — Kuonekwa kweVideo', 'rate' => 0.15, 'min_qty' => 1000, 'max_qty' => 100000, 'is_refill' => false, 'avg_time_minutes' => 10],
            ['category' => 'instagram', 'name' => 'Instagram Comments (Custom)', 'name_sn' => 'Instagram — Mashoko Evanhu', 'rate' => 5.00, 'min_qty' => 10, 'max_qty' => 500, 'is_refill' => false, 'avg_time_minutes' => 120],
            ['category' => 'instagram', 'name' => 'Instagram Story Views', 'name_sn' => 'Instagram — Story Inoonekwa', 'rate' => 0.20, 'min_qty' => 1000, 'max_qty' => 50000, 'is_refill' => false, 'avg_time_minutes' => 5],
            ['category' => 'instagram', 'name' => 'Instagram Saves', 'name_sn' => 'Instagram — Kuchengetedza', 'rate' => 1.80, 'min_qty' => 100, 'max_qty' => 10000, 'is_refill' => false, 'avg_time_minutes' => 30],

            // ── YouTube ──
            ['category' => 'youtube', 'name' => 'YouTube Channel Subscribers', 'name_sn' => 'YouTube — Vateveri veChannel', 'rate' => 3.50, 'min_qty' => 100, 'max_qty' => 10000, 'is_refill' => true, 'avg_time_minutes' => 180],
            ['category' => 'youtube', 'name' => 'YouTube Video Views (HQ)', 'name_sn' => 'YouTube — Kuonekwa kweVideo', 'rate' => 0.80, 'min_qty' => 500, 'max_qty' => 500000, 'is_refill' => false, 'avg_time_minutes' => 30],
            ['category' => 'youtube', 'name' => 'YouTube Likes (Real)', 'name_sn' => 'YouTube — Zvinodikwa Zvevanhu', 'rate' => 2.00, 'min_qty' => 50, 'max_qty' => 5000, 'is_refill' => false, 'avg_time_minutes' => 60],
            ['category' => 'youtube', 'name' => 'YouTube Watch Hours', 'name_sn' => 'YouTube — Maawa Ekutarisa', 'rate' => 15.00, 'min_qty' => 100, 'max_qty' => 4000, 'is_refill' => false, 'avg_time_minutes' => 2880],

            // ── TikTok ──
            ['category' => 'tiktok', 'name' => 'TikTok Followers', 'name_sn' => 'TikTok — Vateveri', 'rate' => 1.80, 'min_qty' => 100, 'max_qty' => 30000, 'is_refill' => true, 'avg_time_minutes' => 60],
            ['category' => 'tiktok', 'name' => 'TikTok Video Views', 'name_sn' => 'TikTok — Mifananidzo Inoonekwa', 'rate' => 0.20, 'min_qty' => 1000, 'max_qty' => 200000, 'is_refill' => false, 'avg_time_minutes' => 5],
            ['category' => 'tiktok', 'name' => 'TikTok Likes', 'name_sn' => 'TikTok — Zvinodikwa', 'rate' => 0.70, 'min_qty' => 100, 'max_qty' => 50000, 'is_refill' => false, 'avg_time_minutes' => 10],
            ['category' => 'tiktok', 'name' => 'TikTok Shares', 'name_sn' => 'TikTok — Kugoveranya', 'rate' => 1.50, 'min_qty' => 100, 'max_qty' => 10000, 'is_refill' => false, 'avg_time_minutes' => 20],

            // ── Facebook ──
            ['category' => 'facebook', 'name' => 'Facebook Page Followers', 'name_sn' => 'Facebook — Vateveri vePeji', 'rate' => 1.50, 'min_qty' => 100, 'max_qty' => 20000, 'is_refill' => true, 'avg_time_minutes' => 120],
            ['category' => 'facebook', 'name' => 'Facebook Page Likes', 'name_sn' => 'Facebook — Zvinodikwa zvePeji', 'rate' => 1.80, 'min_qty' => 100, 'max_qty' => 10000, 'is_refill' => false, 'avg_time_minutes' => 120],
            ['category' => 'facebook', 'name' => 'Facebook Post Likes', 'name_sn' => 'Facebook — Zvinodikwa zvePost', 'rate' => 0.90, 'min_qty' => 50, 'max_qty' => 20000, 'is_refill' => false, 'avg_time_minutes' => 30],
            ['category' => 'facebook', 'name' => 'Facebook Video Views', 'name_sn' => 'Facebook — Kuonekwa kweVideo', 'rate' => 0.25, 'min_qty' => 1000, 'max_qty' => 100000, 'is_refill' => false, 'avg_time_minutes' => 15],

            // ── Twitter/X ──
            ['category' => 'twitter', 'name' => 'Twitter/X Followers', 'name_sn' => 'Twitter/X — Vateveri', 'rate' => 2.50, 'min_qty' => 50, 'max_qty' => 10000, 'is_refill' => true, 'avg_time_minutes' => 180],
            ['category' => 'twitter', 'name' => 'Twitter/X Likes', 'name_sn' => 'Twitter/X — Zvinodikwa', 'rate' => 0.80, 'min_qty' => 50, 'max_qty' => 20000, 'is_refill' => false, 'avg_time_minutes' => 30],
            ['category' => 'twitter', 'name' => 'Twitter/X Retweets', 'name_sn' => 'Twitter/X — Kutweet zvakare', 'rate' => 1.20, 'min_qty' => 50, 'max_qty' => 10000, 'is_refill' => false, 'avg_time_minutes' => 30],

            // ── Telegram ──
            ['category' => 'telegram', 'name' => 'Telegram Channel Members', 'name_sn' => 'Telegram — Members weChannel', 'rate' => 2.00, 'min_qty' => 100, 'max_qty' => 50000, 'is_refill' => false, 'avg_time_minutes' => 60],
            ['category' => 'telegram', 'name' => 'Telegram Group Members', 'name_sn' => 'Telegram — Members weGroup', 'rate' => 2.50, 'min_qty' => 100, 'max_qty' => 20000, 'is_refill' => false, 'avg_time_minutes' => 90],
            ['category' => 'telegram', 'name' => 'Telegram Post Views', 'name_sn' => 'Telegram — Kuonekwa kwePost', 'rate' => 0.10, 'min_qty' => 1000, 'max_qty' => 1000000, 'is_refill' => false, 'avg_time_minutes' => 5],

            // ── WhatsApp ──
            ['category' => 'whatsapp', 'name' => 'WhatsApp Channel Followers', 'name_sn' => 'WhatsApp — Vateveri veChannel', 'rate' => 2.20, 'min_qty' => 100, 'max_qty' => 25000, 'is_refill' => false, 'avg_time_minutes' => 90],
        ];

        foreach ($services as $i => $data) {
            Service::create(array_merge($data, [
                'type' => 'default',
                'upstream_service_id' => (string) (1000 + $i + 1),
                'is_active' => true,
                'is_dripfeed' => false,
                'refill_days' => 30,
                'display_order' => $i,
                'description' => null,
                'description_sn' => null,
            ]));
        }

        ManualPaymentDetail::create([
            'method_key' => 'ecocash',
            'label' => 'EcoCash USD',
            'account_name' => 'ZimSocials Pvt Ltd',
            'account_number' => '0771234567',
            'instructions' => 'Send payment proof and your username to support for fast approval.',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        ManualPaymentDetail::create([
            'method_key' => 'innbucks',
            'label' => 'InnBucks Wallet',
            'account_name' => 'ZimSocials Billing',
            'account_number' => '263771234567',
            'instructions' => 'Include transaction reference in your wallet top-up request.',
            'is_active' => true,
            'sort_order' => 2,
        ]);
    }
}
