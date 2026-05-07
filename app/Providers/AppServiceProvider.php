<?php

namespace App\Providers;

use App\Models\BusinessContract;
use App\Policies\BusinessContractPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        \Illuminate\Support\Facades\Schema::defaultStringLength(191);
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
                Limit::perMinute(12)->by('paynow:' . $key),
            ];
        });

        RateLimiter::for('wallet-proof-submit', function (Request $request) {
            $key = (string) ($request->user()?->id ?? $request->ip());

            return [
                Limit::perMinute(10)->by('proof:' . $key),
            ];
        });

        RateLimiter::for('wallet-manual-deposit', function (Request $request) {
            $key = (string) ($request->user()?->id ?? $request->ip());

            return [
                Limit::perMinute(10)->by('manual-deposit:' . $key),
            ];
        });

        RateLimiter::for('wallet-withdraw', function (Request $request) {
            $key = (string) ($request->user()?->id ?? $request->ip());

            return [
                Limit::perMinute(3)->by('withdraw:' . $key),
            ];
        });

        RateLimiter::for('api-key', function (Request $request) {
            $apiKey = $request->bearerToken() ?? $request->ip();

            return [
                Limit::perMinute(60)->by('api:' . $apiKey),
            ];
        });
    }

    protected function loadSettings(): void
    {
        try {
            // Cache settings as raw arrays for 5 minutes (Eloquent collections don't serialize reliably)
            $settingsArray = \Illuminate\Support\Facades\Cache::remember('app:boot_settings', 300, function () {
                if (!\Illuminate\Support\Facades\Schema::hasTable('settings')) {
                    return [];
                }
                return \App\Models\Setting::all(['key', 'value', 'group'])->toArray();
            });

            if (empty($settingsArray)) {
                return;
            }

            $mailKeyMap = [
                'host'         => 'mail.mailers.smtp.host',
                'port'         => 'mail.mailers.smtp.port',
                'username'     => 'mail.mailers.smtp.username',
                'password'     => 'mail.mailers.smtp.password',
                'encryption'   => 'mail.mailers.smtp.encryption',
                'from_address' => 'mail.from.address',
                'from_name'    => 'mail.from.name',
            ];

            foreach ($settingsArray as $setting) {
                if ($setting['group'] === 'mail' && isset($mailKeyMap[$setting['key']])) {
                    config([$mailKeyMap[$setting['key']] => $setting['value']]);
                }

                if ($setting['group'] === 'whatsapp') {
                    config(["services.whatsapp.{$setting['key']}" => $setting['value']]);
                }

                if ($setting['group'] === 'app') {
                    config(["app.{$setting['key']}" => $setting['value']]);
                }

                config(["settings.{$setting['key']}" => $setting['value']]);
            }
        } catch (\Exception $e) {
            // Fail silently if DB not ready
        }
    }
}
