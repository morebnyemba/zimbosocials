{{-- resources/views/services/index.blade.php --}}
@extends('layouts.app')

@section('page-title', __('messages.services'))
@section('page-sub', app()->getLocale()==='sn' ? 'Masevhisi ese atinawo' : 'All available services')

@section('content')

{{-- ── Category tabs ── --}}
<div class="tabs">
    <a href="{{ route('services.index') }}"
       class="tab {{ !request('category') ? 'active' : '' }}">
        {{ __('messages.all_categories') }}
    </a>
    @foreach($categories as $cat)
        <a href="{{ route('services.index', ['category' => $cat]) }}"
           class="tab {{ request('category') === $cat ? 'active' : '' }}">
            {{ ucfirst($cat) }}
        </a>
    @endforeach
</div>

<div class="table-wrap">
    @if($services->isEmpty())
        <div class="empty-state"><p>{{ app()->getLocale()==='sn' ? 'Hamuna masevhisi ariipo.' : 'No services found.' }}</p></div>
    @else
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>{{ __('messages.service') }}</th>
                    <th>{{ __('messages.category') }}</th>
                    <th>{{ __('messages.rate_per_1000') }}</th>
                    <th>{{ __('messages.min_qty') }}</th>
                    <th>{{ __('messages.max_qty') }}</th>
                    <th>{{ app()->getLocale()==='sn' ? 'Refill' : 'Refill' }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($services as $svc)
                @php
                    $catColors = ['instagram'=>'badge-danger','youtube'=>'badge-danger','tiktok'=>'badge-info','facebook'=>'badge-purple','twitter'=>'badge-info','telegram'=>'badge-purple'];
                @endphp
                <tr>
                    <td class="mono muted">{{ $svc->id }}</td>
                    <td style="max-width:220px">
                        <div style="font-size:12px;font-weight:500">{{ app()->getLocale()==='sn' ? $svc->name_sn : $svc->name }}</div>
                        @if($svc->is_dripfeed)
                            <div style="font-size:10px;color:var(--accent3);margin-top:2px">⟳ Dripfeed</div>
                        @endif
                    </td>
                    <td><span class="badge {{ $catColors[$svc->category] ?? 'badge-info' }}">{{ $svc->category }}</span></td>
                    <td class="mono text-accent">${{ number_format($svc->rate, 4) }}</td>
                    <td class="mono">{{ number_format($svc->min_qty) }}</td>
                    <td class="mono">{{ number_format($svc->max_qty) }}</td>
                    <td>
                        @if($svc->is_refill)
                            <span class="badge badge-success">✓</span>
                        @else
                            <span style="color:var(--text3)">—</span>
                        @endif
                    </td>
                    <td>
                        <a href="{{ route('orders.create', ['service_id' => $svc->id]) }}"
                           class="btn btn-primary btn-sm">
                            {{ __('messages.order_this') }}
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection
