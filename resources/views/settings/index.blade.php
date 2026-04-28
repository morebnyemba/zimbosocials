{{-- resources/views/settings/index.blade.php --}}
@extends('layouts.app')

@section('page-title', __('messages.settings'))
@section('page-sub', app()->getLocale()==='sn' ? 'Gadzirisa account yako' : 'Manage your account preferences')

@section('content')
<div style="max-width:560px">

    {{-- ── Profile ── --}}
    <div class="card" style="margin-bottom:16px">
        <div class="card-title">{{ strtoupper(__('messages.profile')) }}</div>
        <form method="POST" action="{{ route('settings.update') }}">
            @csrf
            <input type="hidden" name="section" value="profile">
            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">{{ __('messages.display_name') }}</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name', auth()->user()->name) }}" required>
                </div>
                <div class="form-group">
                    <label class="form-label">{{ __('messages.phone') }}</label>
                    <input type="text" name="phone" class="form-control" value="{{ old('phone', auth()->user()->phone) }}" placeholder="+263...">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('messages.email') }}</label>
                <input type="email" class="form-control" value="{{ auth()->user()->email }}" disabled style="opacity:.5">
                <div class="form-hint">{{ app()->getLocale()==='sn' ? 'Imeri haichinji.' : 'Email cannot be changed.' }}</div>
            </div>
            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">{{ __('messages.currency') }}</label>
                    <select name="currency" class="form-control">
                        <option value="USD" {{ auth()->user()->currency==='USD'?'selected':'' }}>USD ($)</option>
                        <option value="ZWL" {{ auth()->user()->currency==='ZWL'?'selected':'' }}>ZWL (ZW$)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">{{ __('messages.language') }}</label>
                    <select name="locale" class="form-control">
                        <option value="sn" {{ auth()->user()->locale==='sn'?'selected':'' }}>Shona</option>
                        <option value="en" {{ auth()->user()->locale==='en'?'selected':'' }}>English</option>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">{{ __('messages.save_changes') }}</button>
        </form>
    </div>

    {{-- ── Security ── --}}
    <div class="card" style="margin-bottom:16px">
        <div class="card-title">{{ strtoupper(__('messages.security')) }}</div>
        <form method="POST" action="{{ route('settings.update') }}">
            @csrf
            <input type="hidden" name="section" value="password">
            <div class="form-group">
                <label class="form-label">{{ app()->getLocale()==='sn' ? 'PASIWEDHI YEKARE' : 'CURRENT PASSWORD' }}</label>
                <input type="password" name="current_password" class="form-control" required>
            </div>
            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">{{ app()->getLocale()==='sn' ? 'PASIWEDHI ITSVA' : 'NEW PASSWORD' }}</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">{{ __('messages.confirm_password') }}</label>
                    <input type="password" name="password_confirmation" class="form-control" required>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">{{ app()->getLocale()==='sn' ? 'Chinja Pasiwedhi' : 'Change Password' }}</button>
        </form>
    </div>

    {{-- ── API Key ── --}}
    <div class="card">
        <div class="card-title">{{ strtoupper(__('messages.api_key')) }}</div>
        <div style="font-size:11px;color:var(--text2);margin-bottom:12px">{{ __('messages.api_guide') }}</div>
        <div style="background:var(--bg3);border:1px solid var(--border2);border-radius:6px;padding:10px 14px;font-family:var(--mono);font-size:11px;color:var(--text2);display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:12px;word-break:break-all">
            @if(auth()->user()->api_key)
                <span id="api-key-val">{{ auth()->user()->api_key }}</span>
                <button onclick="navigator.clipboard.writeText('{{ auth()->user()->api_key }}').then(()=>this.textContent='✓')" class="btn btn-secondary btn-sm" style="flex-shrink:0">
                    {{ app()->getLocale()==='sn' ? 'Kopya' : 'Copy' }}
                </button>
            @else
                <span style="color:var(--text3)">{{ app()->getLocale()==='sn' ? 'Hapana kiyi. Gadzira itsva.' : 'No key yet. Generate one.' }}</span>
            @endif
        </div>
        <div style="background:rgba(255,165,2,.06);border:1px solid rgba(255,165,2,.2);border-radius:6px;padding:10px 12px;font-size:11px;color:var(--text2);margin-bottom:12px">
            ⚠ {{ __('messages.api_key_warning') }}
        </div>
        <form method="POST" action="{{ route('settings.api-key') }}">
            @csrf
            <button type="submit" class="btn btn-secondary" onclick="return confirm('{{ app()->getLocale()==='sn' ? 'Kiyi yekare ichadzimwa. Enderera?' : 'Old key will be invalidated. Continue?' }}')">
                ↺ {{ __('messages.api_key_regen') }}
            </button>
        </form>

        <div style="margin-top:20px;padding-top:16px;border-top:1px solid var(--border)">
            <div class="card-title">{{ strtoupper(__('messages.api_endpoint')) }}</div>
            <div style="background:var(--bg);border-radius:6px;padding:12px;font-family:var(--mono);font-size:10px;color:var(--text2);line-height:2">
                <div><span class="badge badge-success" style="margin-right:8px">GET</span> {{ url('/api/v1/services') }}</div>
                <div><span class="badge badge-info" style="margin-right:8px">POST</span> {{ url('/api/v1/order') }}</div>
                <div><span class="badge badge-warn" style="margin-right:8px">GET</span> {{ url('/api/v1/status') }}</div>
                <div><span class="badge badge-purple" style="margin-right:8px">GET</span> {{ url('/api/v1/balance') }}</div>
                <div><span class="badge badge-danger" style="margin-right:8px">POST</span> {{ url('/api/v1/cancel') }}</div>
            </div>
        </div>
    </div>
</div>
@endsection
