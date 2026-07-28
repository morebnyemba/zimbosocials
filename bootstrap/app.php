<?php

use App\Http\Middleware\CaptureReferral;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\IsAdmin;
use App\Http\Middleware\IsMarketer;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Behind the host's nginx, which terminates TLS and forwards over HTTP.
        // Without trusting it, X-Forwarded-Proto is ignored, every generated URL
        // comes out as http://, and the browser then blocks the stylesheets and
        // scripts on an https page as mixed content — the page renders unstyled
        // while images (passive content) still load.
        //
        // The proxy is another container on a private Docker network and the
        // port is not published publicly, so the client address cannot be
        // spoofed from outside.
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            CaptureReferral::class,
            SetLocale::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            '/paynow/webhook',
            '/webhooks/whatsapp',
        ]);

        $middleware->alias([
            'admin' => IsAdmin::class,
            'marketer' => IsMarketer::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
