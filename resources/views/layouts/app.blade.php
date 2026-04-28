{{-- resources/views/layouts/app.blade.php --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('messages.dashboard')) — zimsocials</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Sora:wght@300;400;500;600&display=swap');

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:      #0a0c10;
            --bg2:     #111318;
            --bg3:     #181b22;
            --bg4:     #1e222c;
            --accent:  #00e5a0;
            --accent2: #ff6b35;
            --accent3: #7c6aff;
            --text:    #e8eaf0;
            --text2:   #8b93a8;
            --text3:   #555e72;
            --border:  #252a35;
            --border2: #2e3547;
            --danger:  #ff4757;
            --warn:    #ffa502;
            --info:    #4ecdc4;
            --success: #00e5a0;
            --font:    'Sora', sans-serif;
            --mono:    'Space Mono', monospace;
            --sidebar: 210px;
        }

        html, body { height: 100%; }

        body {
            font-family: var(--font);
            background: var(--bg);
            color: var(--text);
            font-size: 13px;
            line-height: 1.6;
        }

        /* ── Layout ── */
        .layout       { display: flex; height: 100vh; overflow: hidden; }
        .sidebar      { width: var(--sidebar); background: var(--bg2); border-right: 1px solid var(--border); display: flex; flex-direction: column; flex-shrink: 0; }
        .main-area    { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
        .topbar       { background: var(--bg2); border-bottom: 1px solid var(--border); padding: 12px 24px; display: flex; align-items: center; justify-content: space-between; flex-shrink: 0; }
        .page-content { flex: 1; overflow-y: auto; padding: 20px 24px; }

        /* ── Sidebar ── */
        .sidebar-logo  { padding: 20px 16px 16px; border-bottom: 1px solid var(--border); }
        .logo-mark     { font-family: var(--mono); font-size: 12px; font-weight: 700; color: var(--accent); letter-spacing: 2px; }
        .logo-sub      { font-size: 9px; color: var(--text3); letter-spacing: 1px; margin-top: 3px; }
        .sidebar-user  { padding: 12px 16px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 10px; }
        .avatar        { width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg, var(--accent3), var(--accent)); display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600; color: #fff; flex-shrink: 0; }
        .user-name     { font-size: 12px; font-weight: 500; }
        .user-bal      { font-size: 11px; color: var(--accent); font-family: var(--mono); }
        .nav           { flex: 1; padding: 8px 0; overflow-y: auto; }
        .nav-section   { padding: 14px 16px 4px; font-size: 9px; color: var(--text3); letter-spacing: 2px; text-transform: uppercase; font-family: var(--mono); }
        .nav-item      { display: flex; align-items: center; gap: 10px; padding: 9px 16px; cursor: pointer; color: var(--text2); font-size: 12px; transition: all .15s; border-left: 2px solid transparent; text-decoration: none; }
        .nav-item:hover  { color: var(--text); background: var(--bg3); }
        .nav-item.active { color: var(--accent); background: rgba(0,229,160,.06); border-left-color: var(--accent); }
        .nav-icon      { width: 16px; text-align: center; font-size: 13px; flex-shrink: 0; }
        .nav-badge     { margin-left: auto; background: var(--accent); color: #000; font-size: 9px; font-weight: 700; padding: 2px 6px; border-radius: 8px; font-family: var(--mono); }
        .sidebar-foot  { padding: 12px 16px; border-top: 1px solid var(--border); }
        .lang-toggle   { display: flex; gap: 4px; background: var(--bg3); border-radius: 6px; padding: 3px; }
        .lang-btn      { flex: 1; padding: 5px; font-size: 9px; font-weight: 700; letter-spacing: 1px; text-align: center; border-radius: 4px; cursor: pointer; border: none; font-family: var(--mono); transition: all .15s; color: var(--text3); background: transparent; }
        .lang-btn.active { background: var(--accent); color: #000; }

        /* ── Topbar ── */
        .page-title  { font-size: 15px; font-weight: 600; }
        .page-sub    { font-size: 11px; color: var(--text3); margin-top: 1px; }
        .topbar-actions { display: flex; align-items: center; gap: 8px; }

        /* ── Buttons ── */
        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; border-radius: 6px; font-size: 12px; font-weight: 500; cursor: pointer; border: none; font-family: var(--font); transition: all .15s; text-decoration: none; }
        .btn-primary   { background: var(--accent); color: #000; }
        .btn-primary:hover { background: #00c985; }
        .btn-secondary { background: var(--bg3); color: var(--text); border: 1px solid var(--border2); }
        .btn-secondary:hover { background: var(--bg4); }
        .btn-danger    { background: rgba(255,71,87,.12); color: var(--danger); border: 1px solid rgba(255,71,87,.25); }
        .btn-danger:hover { background: rgba(255,71,87,.2); }
        .btn-sm        { padding: 5px 10px; font-size: 11px; }
        .btn-full      { width: 100%; justify-content: center; }

        /* ── Cards ── */
        .card          { background: var(--bg2); border: 1px solid var(--border); border-radius: 8px; padding: 16px; }
        .card-title    { font-size: 11px; font-weight: 600; color: var(--text3); font-family: var(--mono); letter-spacing: .5px; text-transform: uppercase; margin-bottom: 14px; }

        /* ── Stat cards ── */
        .stat-grid     { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 20px; }
        .stat-card     { background: var(--bg2); border: 1px solid var(--border); border-radius: 8px; padding: 14px; }
        .stat-label    { font-size: 9px; color: var(--text3); letter-spacing: 1.5px; text-transform: uppercase; font-family: var(--mono); }
        .stat-val      { font-size: 24px; font-weight: 600; font-family: var(--mono); margin: 6px 0 4px; }
        .stat-sub      { font-size: 10px; color: var(--text2); }
        .c-green .stat-val { color: var(--accent); }
        .c-orange .stat-val { color: var(--accent2); }
        .c-purple .stat-val { color: var(--accent3); }
        .c-blue .stat-val { color: var(--info); }

        /* ── Tables ── */
        .table-wrap    { background: var(--bg2); border: 1px solid var(--border); border-radius: 8px; overflow-x: auto; }
        table          { width: 100%; border-collapse: collapse; }
        thead tr       { background: var(--bg3); }
        th             { padding: 9px 14px; font-size: 10px; font-weight: 600; color: var(--text3); text-align: left; font-family: var(--mono); letter-spacing: .5px; border-bottom: 1px solid var(--border); white-space: nowrap; }
        td             { padding: 10px 14px; font-size: 12px; color: var(--text); border-bottom: 1px solid var(--border); }
        tr:last-child td { border-bottom: none; }
        tbody tr:hover td { background: rgba(255,255,255,.02); }
        .mono          { font-family: var(--mono); }
        .muted         { color: var(--text3); }
        .text-accent   { color: var(--accent); }
        .text-accent2  { color: var(--accent2); }
        .text-info     { color: var(--info); }

        /* ── Badges ── */
        .badge         { display: inline-flex; padding: 2px 8px; border-radius: 10px; font-size: 10px; font-weight: 600; font-family: var(--mono); }
        .badge-success { background: rgba(0,229,160,.12); color: var(--accent); }
        .badge-warn    { background: rgba(255,165,2,.12); color: var(--warn); }
        .badge-danger  { background: rgba(255,71,87,.12); color: var(--danger); }
        .badge-info    { background: rgba(78,205,196,.12); color: var(--info); }
        .badge-purple  { background: rgba(124,106,255,.12); color: var(--accent3); }

        /* ── Forms ── */
        .form-group    { margin-bottom: 14px; }
        .form-label    { display: block; font-size: 10px; color: var(--text2); margin-bottom: 5px; font-family: var(--mono); letter-spacing: .3px; }
        .form-control  { width: 100%; background: var(--bg3); border: 1px solid var(--border2); border-radius: 6px; padding: 9px 12px; font-size: 12px; color: var(--text); font-family: var(--font); outline: none; transition: border-color .15s; }
        .form-control:focus { border-color: var(--accent); }
        select.form-control option { background: var(--bg3); }
        textarea.form-control { resize: vertical; min-height: 80px; }
        .form-hint     { font-size: 10px; color: var(--text3); margin-top: 4px; }
        .form-error    { font-size: 11px; color: var(--danger); margin-top: 4px; }

        /* ── Alerts ── */
        .alert         { padding: 10px 14px; border-radius: 6px; font-size: 12px; margin-bottom: 14px; display: flex; align-items: flex-start; gap: 8px; }
        .alert-success { background: rgba(0,229,160,.08); border: 1px solid rgba(0,229,160,.2); color: var(--text2); }
        .alert-danger  { background: rgba(255,71,87,.08); border: 1px solid rgba(255,71,87,.2); color: var(--text2); }
        .alert-warn    { background: rgba(255,165,2,.08); border: 1px solid rgba(255,165,2,.2); color: var(--text2); }
        .alert-info    { background: rgba(78,205,196,.08); border: 1px solid rgba(78,205,196,.2); color: var(--text2); }
        .alert strong  { color: var(--text); }

        /* ── Misc ── */
        .grid-2        { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .grid-3        { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; }
        .section-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
        .section-title  { font-size: 11px; font-weight: 600; color: var(--text3); font-family: var(--mono); letter-spacing: .5px; text-transform: uppercase; }
        .empty-state   { text-align: center; padding: 40px 20px; color: var(--text3); }
        .empty-state p { font-size: 13px; margin-bottom: 12px; }
        .tabs          { display: flex; border-bottom: 1px solid var(--border); margin-bottom: 16px; gap: 0; }
        .tab           { padding: 8px 16px; font-size: 12px; color: var(--text3); cursor: pointer; border-bottom: 2px solid transparent; transition: all .15s; text-decoration: none; font-family: var(--mono); }
        .tab.active    { color: var(--accent); border-bottom-color: var(--accent); }
        .tab:hover:not(.active) { color: var(--text); }
        ::-webkit-scrollbar { width: 4px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--border2); border-radius: 2px; }

        @media (max-width: 1024px) {
            :root { --sidebar: 100%; }
            .layout { display: block; height: auto; }
            .sidebar {
                width: 100%;
                border-right: none;
                border-bottom: 1px solid var(--border);
            }
            .sidebar-logo,
            .sidebar-user,
            .sidebar-foot { padding-left: 14px; padding-right: 14px; }
            .nav {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                gap: 6px;
                padding: 8px 12px 12px;
                overflow: visible;
            }
            .nav-section {
                width: 100%;
                padding: 10px 2px 2px;
            }
            .nav-item {
                border-left: none;
                border: 1px solid var(--border2);
                border-radius: 6px;
                padding: 7px 10px;
                font-size: 11px;
                background: var(--bg3);
            }
            .nav-item.active {
                border-color: rgba(0, 229, 160, 0.35);
                background: rgba(0,229,160,.12);
            }
            .main-area {
                min-height: auto;
                overflow: visible;
            }
            .topbar {
                padding: 10px 14px;
                flex-wrap: wrap;
                gap: 10px;
            }
            .page-content {
                overflow: visible;
                padding: 16px 14px;
            }
            .stat-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .grid-3 { grid-template-columns: 1fr 1fr; }
        }

        @media (max-width: 640px) {
            .topbar-actions { width: 100%; justify-content: flex-start; }
            .btn { font-size: 11px; padding: 7px 10px; }
            .stat-grid,
            .grid-2,
            .grid-3 { grid-template-columns: 1fr; }
            .tabs { overflow-x: auto; }
            .tab { white-space: nowrap; }
            th, td { padding: 8px 10px; }
        }
    </style>
</head>
<body>
<div class="layout">

    {{-- ── Sidebar ── --}}
    <aside class="sidebar">
        <div class="sidebar-logo">
            <div class="logo-mark">ZIMSOCIALS</div>
            <div class="logo-sub">SOCIAL GROWTH PLATFORM</div>
        </div>

        <div class="sidebar-user">
            <div class="avatar">{{ strtoupper(substr(auth()->user()->name, 0, 2)) }}</div>
            <div>
                <div class="user-name">{{ auth()->user()->name }}</div>
                <div class="user-bal">{{ auth()->user()->formatted_balance }}</div>
            </div>
        </div>

        <nav class="nav">
            <div class="nav-section">{{ strtoupper(__('messages.dashboard')) }}</div>

            <a href="{{ route('dashboard') }}" class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <span class="nav-icon">◈</span> {{ __('messages.dashboard') }}
            </a>
            <a href="{{ route('orders.create') }}" class="nav-item {{ request()->routeIs('orders.create') ? 'active' : '' }}">
                <span class="nav-icon">✦</span> {{ __('messages.new_order') }}
            </a>
            <a href="{{ route('orders.index') }}" class="nav-item {{ request()->routeIs('orders.*') && !request()->routeIs('orders.create') ? 'active' : '' }}">
                <span class="nav-icon">◉</span> {{ __('messages.orders') }}
                @php $active = \App\Models\Order::forUser(auth()->id())->active()->count() @endphp
                @if($active > 0)
                    <span class="nav-badge">{{ $active }}</span>
                @endif
            </a>
            <a href="{{ route('services.index') }}" class="nav-item {{ request()->routeIs('services.*') ? 'active' : '' }}">
                <span class="nav-icon">▦</span> {{ __('messages.services') }}
            </a>

            <div class="nav-section">Account</div>

            <a href="{{ route('wallet.index') }}" class="nav-item {{ request()->routeIs('wallet.*') ? 'active' : '' }}">
                <span class="nav-icon">◎</span> {{ __('messages.wallet') }}
            </a>
            <a href="{{ route('tickets.index') }}" class="nav-item {{ request()->routeIs('tickets.*') ? 'active' : '' }}">
                <span class="nav-icon">◷</span> {{ __('messages.tickets') }}
            </a>
            <a href="{{ route('settings.index') }}" class="nav-item {{ request()->routeIs('settings.*') ? 'active' : '' }}">
                <span class="nav-icon">⚙</span> {{ __('messages.settings') }}
            </a>

            @if(auth()->user()->isMarketer())
            <div class="nav-section">Marketer</div>
            <a href="{{ route('marketer.dashboard') }}" class="nav-item {{ request()->routeIs('marketer.*') ? 'active' : '' }}">
                <span class="nav-icon">◆</span> Marketer Panel
            </a>
            @endif

            @if(auth()->user()->isAdmin())
            <div class="nav-section">Admin</div>
            <a href="{{ route('admin.dashboard') }}" class="nav-item {{ request()->routeIs('admin.*') ? 'active' : '' }}">
                <span class="nav-icon">⬡</span> Admin Panel
            </a>
            <a href="{{ route('admin.payment-details.index') }}" class="nav-item {{ request()->routeIs('admin.payment-details.*') ? 'active' : '' }}">
                <span class="nav-icon">$</span> Payment Details
            </a>
            @endif
        </nav>

        <div class="sidebar-foot">
            {{-- Language Toggle --}}
            <div class="lang-toggle" style="margin-bottom: 10px;">
                <form method="POST" action="{{ route('locale.switch') }}" style="display:contents">
                    @csrf
                    <input type="hidden" name="locale" value="sn">
                    <button type="submit" class="lang-btn {{ app()->getLocale() === 'sn' ? 'active' : '' }}" title="Shona">SN</button>
                </form>
                <form method="POST" action="{{ route('locale.switch') }}" style="display:contents">
                    @csrf
                    <input type="hidden" name="locale" value="nd">
                    <button type="submit" class="lang-btn {{ app()->getLocale() === 'nd' ? 'active' : '' }}" title="IsiNdebele">ND</button>
                </form>
                <form method="POST" action="{{ route('locale.switch') }}" style="display:contents">
                    @csrf
                    <input type="hidden" name="locale" value="en">
                    <button type="submit" class="lang-btn {{ app()->getLocale() === 'en' ? 'active' : '' }}" title="English">EN</button>
                </form>
            </div>
            {{-- Logout --}}
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn btn-secondary btn-sm btn-full">
                    ⏻ {{ __('messages.logout') }}
                </button>
            </form>
        </div>
    </aside>

    {{-- ── Main ── --}}
    <div class="main-area">
        <div class="topbar">
            <div>
                <div class="page-title">@yield('page-title', __('messages.dashboard'))</div>
                <div class="page-sub">@yield('page-sub', '')</div>
            </div>
            <div class="topbar-actions">
                @yield('topbar-actions')
                <a href="{{ route('orders.create') }}" class="btn btn-primary btn-sm">
                    + {{ __('messages.new_order') }}
                </a>
            </div>
        </div>

        <div class="page-content">
            {{-- Flash messages --}}
            @if(session('success'))
                <div class="alert alert-success">✓ <span>{{ session('success') }}</span></div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger">✕ <span>{{ session('error') }}</span></div>
            @endif
            @if(session('info'))
                <div class="alert alert-info">ℹ <span>{{ session('info') }}</span></div>
            @endif
            @if($errors->any())
                <div class="alert alert-danger">
                    <div>
                        @foreach($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                </div>
            @endif

            @yield('content')
        </div>
    </div>
</div>
</body>
</html>
