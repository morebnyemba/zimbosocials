<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#0B3E09">

        <!-- PWA / icons -->
        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" type="image/png" sizes="192x192" href="/pwa-192x192.png">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        <link rel="manifest" href="/manifest.webmanifest">
        <meta name="application-name" content="Zimbo Socials">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="Zimbo Socials">

        <title inertia>{{ config('app.name', 'Zimbo Socials') }}</title>

        <!-- Open Graph / Facebook / WhatsApp -->
        <meta property="og:type" content="website">
        <meta property="og:url" content="{{ url()->current() }}">
        <meta property="og:title" content="Zimbo Socials - Grow Your Digital Presence">
        <meta property="og:description" content="Grow your social media presence, get monetization, and access digital marketing services in one place. Join today and start earning!">
        <meta property="og:image" content="{{ secure_asset('images/zimbosocials_og.jpg') }}">
        <meta property="og:image:secure_url" content="{{ secure_asset('images/zimbosocials_og.jpg') }}">
        <meta property="og:image:type" content="image/jpeg">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta property="og:image:alt" content="Zimbo Socials - Invite Friends, Earn Rewards">

        <!-- Twitter -->
        <meta property="twitter:card" content="summary_large_image">
        <meta property="twitter:url" content="{{ url()->current() }}">
        <meta property="twitter:title" content="Zimbo Socials - Grow Your Digital Presence">
        <meta property="twitter:description" content="Grow your social media presence, get monetization, and access digital marketing services in one place. Join today and start earning!">
        <meta property="twitter:image" content="{{ secure_asset('images/zimbosocials_og.jpg') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @viteReactRefresh
        @vite(['resources/js/app.tsx', "resources/js/Pages/{$page['component']}.tsx"])
        @inertiaHead
        @php
            $tawkPropertyId = config('services.tawk.property_id');
            $tawkWidgetId = config('services.tawk.widget_id');
            $currentRole = strtolower((string) optional(auth()->user())->role);
            $allowedTawkRoles = ['marketer', 'reseller', 'user', 'business'];
            $shouldShowTawk = !auth()->check() || in_array($currentRole, $allowedTawkRoles, true);
        @endphp
        @if($tawkPropertyId && $tawkWidgetId && $shouldShowTawk)
            <script type="text/javascript">
                var Tawk_API = Tawk_API || {}, Tawk_LoadStart = new Date();
                (function() {
                    var s1 = document.createElement('script');
                    var s0 = document.getElementsByTagName('script')[0];
                    s1.async = true;
                    s1.src = 'https://embed.tawk.to/{{ $tawkPropertyId }}/{{ $tawkWidgetId }}';
                    s1.charset = 'UTF-8';
                    s1.setAttribute('crossorigin', '*');
                    s0.parentNode.insertBefore(s1, s0);
                })();
            </script>
        @endif
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
