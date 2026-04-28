<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#10b981">
        <link rel="apple-touch-icon" href="/pwa-192x192.png">
        <link rel="manifest" href="/manifest.webmanifest">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

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
