<?php
// app/Http/Middleware/SetLocale.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class SetLocale
{
    public function handle(Request $request, Closure $next): mixed
    {
        // Priority: session > user preference > 'sn' (Shona default)
        $locale = session('locale')
            ?? ($request->user()?->locale)
            ?? config('app.locale', 'sn');

        App::setLocale(in_array($locale, ['sn', 'en', 'nd']) ? $locale : 'sn');

        return $next($request);
    }
}
