<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
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
        Vite::prefetch(concurrency: 3);
        $this->registerRateLimiters();
        $this->loadSettings();
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
    }

    protected function loadSettings(): void
    {
        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('settings')) {
                return;
            }

            $settings = \App\Models\Setting::all();

            foreach ($settings as $setting) {
                if ($setting->group === 'mail') {
                    config(["mail.mailers.smtp.{$setting->key}" => $setting->value]);
                    
                    if ($setting->key === 'host') config(['mail.mailers.smtp.host' => $setting->value]);
                    if ($setting->key === 'port') config(['mail.mailers.smtp.port' => $setting->value]);
                    if ($setting->key === 'username') config(['mail.mailers.smtp.username' => $setting->value]);
                    if ($setting->key === 'password') config(['mail.mailers.smtp.password' => $setting->value]);
                    if ($setting->key === 'encryption') config(['mail.mailers.smtp.encryption' => $setting->value]);
                    if ($setting->key === 'from_address') config(['mail.from.address' => $setting->value]);
                    if ($setting->key === 'from_name') config(['mail.from.name' => $setting->value]);
                }

                if ($setting->group === 'whatsapp') {
                    config(["services.whatsapp.{$setting->key}" => $setting->value]);
                }
                
                if ($setting->group === 'app') {
                    config(["app.{$setting->key}" => $setting->value]);
                }

                config(["settings.{$setting->key}" => $setting->value]);
            }
        } catch (\Exception $e) {
            // Fail silently if DB not ready
        }
    }
}
