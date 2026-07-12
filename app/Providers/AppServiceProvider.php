<?php

namespace App\Providers;

use App\Models\BusinessContract;
use App\Models\Setting;
use App\Policies\BusinessContractPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);
        Vite::prefetch(concurrency: 3);
        $this->registerRateLimiters();
        $this->registerPolicies();
        $this->loadSettings();
    }

    protected function registerPolicies(): void
    {
        Gate::policy(BusinessContract::class, BusinessContractPolicy::class);
    }

    protected function registerRateLimiters(): void
    {
        RateLimiter::for('paynow-init', function (Request $request) {
            $key = (string) ($request->user()?->id ?? $request->ip());

            return [
                Limit::perMinute(12)->by('paynow:'.$key),
            ];
        });

        RateLimiter::for('wallet-proof-submit', function (Request $request) {
            $key = (string) ($request->user()?->id ?? $request->ip());

            return [
                Limit::perMinute(10)->by('proof:'.$key),
            ];
        });

        RateLimiter::for('wallet-manual-deposit', function (Request $request) {
            $key = (string) ($request->user()?->id ?? $request->ip());

            return [
                Limit::perMinute(10)->by('manual-deposit:'.$key),
            ];
        });

        RateLimiter::for('wallet-withdraw', function (Request $request) {
            $key = (string) ($request->user()?->id ?? $request->ip());

            return [
                Limit::perMinute(3)->by('withdraw:'.$key),
            ];
        });

        RateLimiter::for('api-key', function (Request $request) {
            $apiKey = $request->bearerToken() ?? $request->ip();

            return [
                Limit::perMinute(60)->by('api:'.$apiKey),
            ];
        });

        RateLimiter::for('ai-drafts', function (Request $request) {
            $key = (string) ($request->user()?->id ?? $request->ip());

            return [
                Limit::perMinute(10)->by('ai:'.$key),
            ];
        });

        // Regular-user-facing AI generation (referral share message) — tighter
        // than ai-drafts, which is scoped to a handful of admins/marketers.
        // This is reachable by every user, so cost control matters more.
        RateLimiter::for('referral-share-draft', function (Request $request) {
            $key = (string) ($request->user()?->id ?? $request->ip());

            return [
                Limit::perDay(5)->by('referral-share:'.$key),
            ];
        });
    }

    protected function loadSettings(): void
    {
        try {
            // Cache settings as raw arrays for 5 minutes (Eloquent collections don't serialize reliably)
            $settingsArray = Cache::remember('app:boot_settings', 300, function () {
                if (! Schema::hasTable('settings')) {
                    return [];
                }

                return Setting::all(['key', 'value', 'group'])->toArray();
            });

            if (empty($settingsArray)) {
                return;
            }

            $mailKeyMap = [
                'host' => 'mail.mailers.smtp.host',
                'port' => 'mail.mailers.smtp.port',
                'username' => 'mail.mailers.smtp.username',
                'password' => 'mail.mailers.smtp.password',
                'encryption' => 'mail.mailers.smtp.encryption',
                'from_address' => 'mail.from.address',
                'from_name' => 'mail.from.name',
            ];

            $mail = [];

            foreach ($settingsArray as $setting) {
                if ($setting['group'] === 'mail' && isset($mailKeyMap[$setting['key']])) {
                    config([$mailKeyMap[$setting['key']] => $setting['value']]);
                    $mail[$setting['key']] = $setting['value'];
                }

                if ($setting['group'] === 'whatsapp') {
                    config(["services.whatsapp.{$setting['key']}" => $setting['value']]);
                }

                if ($setting['group'] === 'gemini') {
                    config(["services.gemini.{$setting['key']}" => $setting['value']]);
                }

                if ($setting['group'] === 'app') {
                    config(["app.{$setting['key']}" => $setting['value']]);
                }

                if ($setting['group'] === 'tawk') {
                    config(["services.tawk.{$setting['key']}" => $setting['value']]);
                }

                config(["settings.{$setting['key']}" => $setting['value']]);
            }

            // An SMTP host configured in the admin panel means "send real
            // mail" — force the smtp mailer even when .env still says
            // MAIL_MAILER=log (the stock default), which would otherwise
            // silently write every email to the log file forever.
            if (! empty($mail['host'])) {
                config(['mail.default' => 'smtp']);

                // Port 465 / 'ssl' is implicit TLS: Symfony needs scheme
                // 'smtps' for it, otherwise the handshake fails. 'tls'
                // (STARTTLS, port 587) works on the plain smtp scheme.
                $encryption = strtolower((string) ($mail['encryption'] ?? ''));
                if ($encryption === 'ssl' || (int) ($mail['port'] ?? 0) === 465) {
                    config(['mail.mailers.smtp.scheme' => 'smtps']);
                }
            }
        } catch (\Exception $e) {
            // Fail silently if DB not ready
        }
    }
}
