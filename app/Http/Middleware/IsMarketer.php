<?php

// app/Http/Middleware/IsMarketer.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsMarketer
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! ($request->user()->hasMarketerAccess() || $request->user()->isAdmin())) {
            abort(403, app()->getLocale() === 'sn'
                ? 'Huna mvumo yekupinda pano.'
                : 'Unauthorized. Marketer access required.');
        }

        return $next($request);
    }
}
