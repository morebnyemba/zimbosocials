<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Captures a `?ref=CODE` query parameter into the session on any GET page so a
 * referral is credited regardless of which page the link lands on (home,
 * services, register, …). The register flow then reads it from the session.
 */
class CaptureReferral
{
    public function handle(Request $request, Closure $next): Response
    {
        if (
            $request->isMethod('GET')
            && $request->filled('ref')
            && ! $request->user()                       // a logged-in user can't be referred
            && ! $request->session()->has('referral_code') // don't overwrite an earlier referrer
        ) {
            $ref = (string) $request->query('ref');

            if (User::where('referral_code', $ref)->exists()) {
                $request->session()->put('referral_code', $ref);
            }
        }

        return $next($request);
    }
}
