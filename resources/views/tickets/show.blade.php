{{-- resources/views/tickets/show.blade.php --}}
@extends('layouts.app')

@section('page-title', app()->getLocale()==='sn' ? 'Mubvunzo' : 'Support Ticket')
@section('page-sub', '#' . $ticket->id . ' — ' . Str::limit($ticket->subject, 50))

@section('topbar-actions')
    <a href="{{ route('tickets.index') }}" class="btn btn-secondary btn-sm">← {{ __('messages.back') }}</a>
@endsection

@section('content')
<div style="max-width:680px">

    {{-- ── Original message ── --}}
    <div class="card" style="margin-bottom:12px">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px">
            <div>
                <div style="font-size:14px;font-weight:600">{{ $ticket->subject }}</div>
                <div style="font-size:11px;color:var(--text3);margin-top:3px">
                    {{ $ticket->user->name }} · {{ $ticket->created_at->format('d M Y H:i') }}
                </div>
            </div>
            @php
                $cls = match($ticket->status) { 'open'=>'badge-warn','pending'=>'badge-info','closed'=>'badge-success', default=>'badge-info' };
                $lbl = app()->getLocale()==='sn' ? $ticket->getStatusLabelSn() : ucfirst($ticket->status);
            @endphp
            <span class="badge {{ $cls }}">{{ $lbl }}</span>
        </div>
        <div style="font-size:13px;color:var(--text2);line-height:1.7;padding-top:10px;border-top:1px solid var(--border)">
            {{ $ticket->message }}
        </div>
    </div>

    {{-- ── Replies ── --}}
    @foreach($ticket->replies as $reply)
    <div class="card" style="margin-bottom:10px;border-color:{{ $reply->is_admin ? 'rgba(0,229,160,.3)' : 'var(--border)' }};background:{{ $reply->is_admin ? 'rgba(0,229,160,.03)' : 'var(--bg2)' }}">
        <div style="display:flex;justify-content:space-between;margin-bottom:8px">
            <div style="font-size:11px;font-weight:600;color:{{ $reply->is_admin ? 'var(--accent)' : 'var(--text2)' }}">
                {{ $reply->is_admin ? 'Support Team' : $reply->user->name }}
            </div>
            <div style="font-size:11px;color:var(--text3)">{{ $reply->created_at->format('d M Y H:i') }}</div>
        </div>
        <div style="font-size:13px;color:var(--text2);line-height:1.7">{{ $reply->message }}</div>
    </div>
    @endforeach

    {{-- ── Reply form ── --}}
    @if($ticket->status !== 'closed')
    <div class="card" style="margin-top:16px">
        <div class="card-title">{{ __('messages.reply') }}</div>
        <form method="POST" action="{{ route('tickets.reply', $ticket) }}">
            @csrf
            <div class="form-group">
                <textarea name="message" class="form-control" rows="4"
                    placeholder="{{ app()->getLocale()==='sn' ? 'Nyora mhinduro yako...' : 'Type your reply...' }}"
                    required>{{ old('message') }}</textarea>
                @error('message') <div class="form-error">{{ $message }}</div> @enderror
            </div>
            <button type="submit" class="btn btn-primary">{{ __('messages.submit') }}</button>
        </form>
    </div>
    @else
        <div class="alert alert-info" style="margin-top:12px">
            ℹ {{ app()->getLocale()==='sn' ? 'Tikiti iyi yakavharwa. Tuma itsva kana une dambudziko.' : 'This ticket is closed. Submit a new one if you need further help.' }}
        </div>
    @endif
</div>
@endsection
