@php
    app()->setLocale($locale ?? 'en');
    $brand = __('mail.brand_name');
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $payload['subject'] ?? __('mail.templates.generic.subject') }}</title>
</head>
<body style="margin:0;padding:0;background:#f5f7fb;font-family:Arial,Helvetica,sans-serif;color:#111827;">
<div style="max-width:640px;margin:0 auto;padding:24px;">
    <div style="height:6px;border-radius:6px;background:linear-gradient(90deg,#059669,#f59e0b,#dc2626);"></div>

    <div style="background:#ffffff;padding:18px 22px 6px;">
        <img src="{{ asset('images/zimbosocials.png') }}" alt="{{ $brand }}" height="32" style="display:block;height:32px;width:auto;">
    </div>

    <div style="background:#ffffff;border:1px solid #e5e7eb;border-radius:14px;padding:22px;">
        <h1 style="font-size:20px;line-height:1.3;margin:0 0 12px;font-weight:700;">{{ $payload['subject'] ?? __('mail.templates.generic.subject') }}</h1>
        <p style="margin:0 0 14px;line-height:1.7;color:#374151;white-space:pre-line;">{{ $payload['body'] ?? __('mail.templates.generic.body') }}</p>

        @if(($template ?? '') === 'welcome')
            <p style="margin:16px 0 0;color:#111827;">{{ __('mail.templates.welcome.helper') }}</p>
        @endif

        @if(($template ?? '') === 'reset_password' && !empty($payload['action_url']))
            <p style="margin:20px 0 0;">
                <a href="{{ $payload['action_url'] }}" style="display:inline-block;background:#0B3E09;color:#fff;text-decoration:none;padding:10px 16px;border-radius:10px;font-weight:700;">
                    {{ __('mail.templates.reset_password.cta') }}
                </a>
            </p>
        @endif

        @if(($template ?? '') === 'verify_email' && !empty($payload['action_url']))
            <p style="margin:20px 0 0;">
                <a href="{{ $payload['action_url'] }}" style="display:inline-block;background:#0B3E09;color:#fff;text-decoration:none;padding:10px 16px;border-radius:10px;font-weight:700;">
                    {{ __('mail.templates.verify_email.cta') }}
                </a>
            </p>
        @endif
    </div>

    <p style="font-size:12px;color:#6b7280;text-align:center;margin:16px 0 0;line-height:1.5;">
        {{ __('mail.footer.manage_preferences') }}
    </p>
</div>
</body>
</html>
