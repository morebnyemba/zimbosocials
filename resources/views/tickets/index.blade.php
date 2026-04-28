{{-- resources/views/tickets/index.blade.php --}}
@extends('layouts.app')

@section('page-title', __('messages.tickets'))
@section('page-sub', app()->getLocale()==='sn' ? 'Tuma uye tarisa mibvunzo yako' : 'Submit and track your support requests')

@section('content')
<div class="grid-2" style="gap:20px">

    {{-- ── New Ticket ── --}}
    <div>
        <div class="section-header">
            <div class="section-title">{{ strtoupper(__('messages.new_ticket')) }}</div>
        </div>
        <div class="card">
            <form method="POST" action="{{ route('tickets.store') }}">
                @csrf
                <div class="form-group">
                    <label class="form-label">{{ __('messages.subject') }}</label>
                    <input type="text" name="subject" class="form-control"
                        placeholder="{{ app()->getLocale()==='sn' ? 'Mhinduro pfupi yedambudziko...' : 'Brief description of issue...' }}"
                        value="{{ old('subject') }}" required>
                    @error('subject') <div class="form-error">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label class="form-label">{{ __('messages.message') }}</label>
                    <textarea name="message" class="form-control" rows="6"
                        placeholder="{{ app()->getLocale()==='sn' ? 'Nyora dambudziko rako pazhinji pano...' : 'Describe your issue in detail...' }}"
                        required>{{ old('message') }}</textarea>
                    @error('message') <div class="form-error">{{ $message }}</div> @enderror
                </div>
                <button type="submit" class="btn btn-primary btn-full">
                    {{ __('messages.submit') }}
                </button>
            </form>
        </div>
    </div>

    {{-- ── Ticket List ── --}}
    <div>
        <div class="section-header">
            <div class="section-title">{{ strtoupper(__('messages.my_tickets')) }}</div>
        </div>
        <div class="table-wrap">
            @if($tickets->isEmpty())
                <div class="empty-state"><p>{{ __('messages.no_tickets') }}</p></div>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>{{ __('messages.subject') }}</th>
                            <th>{{ __('messages.status') }}</th>
                            <th>{{ __('messages.date') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($tickets as $ticket)
                        @php
                            $cls = match($ticket->status) {
                                'open'    => 'badge-warn',
                                'pending' => 'badge-info',
                                'closed'  => 'badge-success',
                                default   => 'badge-info',
                            };
                            $lbl = app()->getLocale()==='sn' ? $ticket->getStatusLabelSn() : ucfirst($ticket->status);
                        @endphp
                        <tr>
                            <td class="mono muted">#{{ $ticket->id }}</td>
                            <td>
                                <a href="{{ route('tickets.show', $ticket) }}"
                                   style="color:var(--text);text-decoration:none;font-size:12px">
                                    {{ Str::limit($ticket->subject, 40) }}
                                </a>
                            </td>
                            <td><span class="badge {{ $cls }}">{{ $lbl }}</span></td>
                            <td class="muted" style="font-size:11px">{{ $ticket->created_at->format('d M Y') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                <div style="padding:10px 14px;border-top:1px solid var(--border)">
                    {{ $tickets->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
