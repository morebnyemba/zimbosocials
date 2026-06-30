<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0B3E09">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('code') &middot; {{ config('app.name', 'Zimbo Socials') }}</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: #18181b;
            background: radial-gradient(120% 120% at 50% 0%, #ffffff 0%, #f4f7f3 45%, #e9f2e7 100%);
        }
        .card {
            width: 100%;
            max-width: 30rem;
            text-align: center;
            background: #ffffff;
            border: 1px solid #e4e7e4;
            border-radius: 24px;
            padding: 40px 28px;
            box-shadow: 0 24px 60px -28px rgba(11, 62, 9, 0.35);
        }
        .logo { height: 34px; width: auto; margin: 0 auto 24px; display: block; }
        .code {
            display: inline-block;
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: #0B3E09;
            background: #e9f2e7;
            padding: 6px 14px;
            border-radius: 999px;
            margin-bottom: 18px;
        }
        h1 { margin: 0 0 10px; font-size: 1.6rem; font-weight: 800; letter-spacing: -0.01em; }
        p { margin: 0 auto 28px; max-width: 24rem; color: #52525b; line-height: 1.55; font-size: 0.95rem; }
        .actions { display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 11px 20px;
            border-radius: 14px;
            font-weight: 700;
            font-size: 0.9rem;
            text-decoration: none;
            transition: transform .15s ease, background-color .15s ease;
        }
        .btn:active { transform: scale(0.98); }
        .btn-primary { background: #0B3E09; color: #ffffff; }
        .btn-primary:hover { background: #0e5210; }
        .btn-ghost { background: #f4f4f5; color: #3f3f46; }
        .btn-ghost:hover { background: #e4e4e7; }
        .footnote { margin-top: 26px; font-size: 0.78rem; color: #a1a1aa; }
    </style>
</head>
<body>
    <main class="card">
        <img class="logo" src="{{ asset('images/zimbosocials.png') }}" alt="{{ config('app.name', 'Zimbo Socials') }}">
        <span class="code">@yield('code') &middot; @yield('label')</span>
        <h1>@yield('title')</h1>
        <p>@yield('message')</p>
        <div class="actions">
            @yield('actions')
                <a class="btn btn-primary" href="{{ url('/') }}">Back to home</a>
        </div>
        <p class="footnote">{{ config('app.name', 'Zimbo Socials') }} &mdash; Grow your brand. Grow your future.</p>
    </main>
</body>
</html>
