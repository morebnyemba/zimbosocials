{{-- resources/views/auth/login.blade.php --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('messages.login') }} — zimsocials</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Sora:wght@300;400;500;600&display=swap');
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --bg: #0a0c10; --bg2: #111318; --bg3: #181b22; --bg4: #1e222c;
            --accent: #00e5a0; --accent3: #7c6aff;
            --text: #e8eaf0; --text2: #8b93a8; --text3: #555e72;
            --border: #252a35; --border2: #2e3547;
            --danger: #ff4757;
            --font: 'Sora', sans-serif; --mono: 'Space Mono', monospace;
        }
        body { font-family: var(--font); background: var(--bg); color: var(--text); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .auth-wrap { width: 100%; max-width: 400px; padding: 20px; }
        .auth-logo { text-align: center; margin-bottom: 32px; }
        .auth-logo-img { width: min(240px, 72vw); height: auto; display: inline-block; }
        .auth-card { background: var(--bg2); border: 1px solid var(--border); border-radius: 10px; padding: 28px; }
        .auth-title { font-size: 16px; font-weight: 600; margin-bottom: 6px; }
        .auth-sub   { font-size: 12px; color: var(--text3); margin-bottom: 24px; }
        .form-group { margin-bottom: 14px; }
        .form-label { display: block; font-size: 10px; color: var(--text2); margin-bottom: 5px; font-family: var(--mono); letter-spacing: .3px; }
        .form-control { width: 100%; background: var(--bg3); border: 1px solid var(--border2); border-radius: 6px; padding: 10px 12px; font-size: 13px; color: var(--text); font-family: var(--font); outline: none; transition: border-color .15s; }
        .form-control:focus { border-color: var(--accent); }
        .form-error { font-size: 11px; color: var(--danger); margin-top: 4px; }
        .btn-primary { display: block; width: 100%; background: var(--accent); color: #000; border: none; border-radius: 6px; padding: 11px; font-size: 13px; font-weight: 600; cursor: pointer; font-family: var(--font); transition: background .15s; margin-top: 6px; }
        .btn-primary:hover { background: #00c985; }
        .auth-foot  { text-align: center; margin-top: 20px; font-size: 12px; color: var(--text3); }
        .auth-foot a { color: var(--accent); text-decoration: none; }
        .check-row  { display: flex; align-items: center; gap: 8px; margin-bottom: 16px; font-size: 12px; color: var(--text2); }
        .check-row input { accent-color: var(--accent); }
        .lang-switch { display: flex; justify-content: center; gap: 8px; margin-bottom: 20px; }
        .lang-btn { padding: 4px 12px; font-size: 10px; font-weight: 700; border-radius: 4px; border: none; cursor: pointer; font-family: var(--mono); background: var(--bg3); color: var(--text3); }
        .lang-btn.active { background: var(--accent); color: #000; }
        .alert-danger { background: rgba(255,71,87,.08); border: 1px solid rgba(255,71,87,.2); color: #ff8a94; border-radius: 6px; padding: 10px 12px; font-size: 12px; margin-bottom: 14px; }
    </style>
</head>
<body>
<div class="auth-wrap">
    <div class="auth-logo">
        <img src="{{ asset('images/zimbosocials.png') }}" alt="Zimbo Socials" class="auth-logo-img">
    </div>

    {{-- Language switch --}}
    <div class="lang-switch">
        <form method="POST" action="{{ route('locale.switch') }}" style="display:contents">
            @csrf <input type="hidden" name="locale" value="sn">
            <button class="lang-btn {{ app()->getLocale()==='sn'?'active':'' }}">SHONA</button>
        </form>
        <form method="POST" action="{{ route('locale.switch') }}" style="display:contents">
            @csrf <input type="hidden" name="locale" value="en">
            <button class="lang-btn {{ app()->getLocale()==='en'?'active':'' }}">ENG</button>
        </form>
    </div>

    <div class="auth-card">
        <div class="auth-title">{{ __('messages.login') }}</div>
        <div class="auth-sub">{{ app()->getLocale()==='sn' ? 'Pinda neimeri nepasiwedhi yako.' : 'Sign in with your email and password.' }}</div>

        @if($errors->any())
            <div class="alert-danger">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf
            <div class="form-group">
                <label class="form-label">{{ __('messages.email') }}</label>
                <input type="email" name="email" class="form-control" value="{{ old('email') }}" required autofocus>
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('messages.password') }}</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <div class="check-row">
                <input type="checkbox" name="remember" id="remember">
                <label for="remember">{{ __('messages.remember_me') }}</label>
            </div>
            <button type="submit" class="btn-primary">{{ __('messages.login') }}</button>
        </form>
    </div>

    <div class="auth-foot">
        {{ __('messages.no_account') }}
        <a href="{{ route('register') }}">{{ __('messages.register') }}</a>
    </div>
</div>
</body>
</html>
