{{-- resources/views/orders/index.blade.php --}}
@extends('layouts.app')

@section('page-title', __('messages.orders'))
@section('page-sub', app()->getLocale()==='sn' ? 'Tarisa maodha ako ose' : 'Track and manage all your orders')

@section('topbar-actions')
    <a href="{{ route('orders.create') }}" class="btn btn-secondary btn-sm">
        + {{ __('messages.new_order') }}
    </a>
@endsection

@section('content')

{{-- ── Filters ── --}}
<form method="GET" action="{{ route('orders.index') }}" style="display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap">
    <input type="text" name="search" class="form-control" style="flex:1;min-width:180px"
        placeholder="{{ __('messages.search_orders') }}" value="{{ request('search') }}">
    <select name="status" class="form-control" style="width:160px">
        <option value="">{{ __('messages.all_statuses') }}</option>
        @foreach(['pending','processing','in_progress','completed','partial','cancelled','refunded'] as $s)
            <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>
                {{ app()->getLocale()==='sn'
                    ? __('messages.status_'.$s)
                    : ucfirst(str_replace('_',' ',$s)) }}
            </option>
        @endforeach
    </select>
    <button class="btn btn-secondary" type="submit">
        {{ app()->getLocale()==='sn' ? 'Tsvaga' : 'Search' }}
    </button>
    @if(request()->hasAny(['search','status']))
        <a href="{{ route('orders.index') }}" class="btn btn-secondary">✕</a>
    @endif
</form>

{{-- ── Table ── --}}
<div class="table-wrap">
    @if($orders->isEmpty())
        <div class="empty-state">
            <p>{{ __('messages.no_orders') }}</p>
            <a href="{{ route('orders.create') }}" class="btn btn-primary btn-sm">+ {{ __('messages.new_order') }}</a>
        </div>
    @else
        <table>
            <thead>
                <tr>
                    <th>{{ __('messages.order_id') }}</th>
                    <th>{{ __('messages.service') }}</th>
                    <th>{{ __('messages.link') }}</th>
                    <th>{{ __('messages.quantity') }}</th>
                    <th>{{ __('messages.charge') }}</th>
                    <th>{{ __('messages.status') }}</th>
                    <th>{{ __('messages.date') }}</th>
                    <th>{{ __('messages.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($orders as $order)
                @php
                    $cls = match($order->status) {
                        'completed'              => 'badge-success',
                        'pending'                => 'badge-warn',
                        'processing','in_progress' => 'badge-info',
                        'cancelled','refunded'   => 'badge-danger',
                        default                  => 'badge-purple',
                    };
                    $label = app()->getLocale()==='sn'
                        ? $order->getStatusLabelSn()
                        : $order->getStatusLabelEn();
                @endphp
                <tr>
                    <td class="mono muted">#{{ $order->id }}</td>
                    <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                        <a href="{{ route('orders.show', $order) }}" style="color:var(--text);text-decoration:none;font-size:12px">
                            {{ app()->getLocale()==='sn' ? $order->service->name_sn : $order->service->name }}
                        </a>
                    </td>
                    <td class="text-info" style="font-size:11px;max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                        {{ $order->link }}
                    </td>
                    <td class="mono">{{ number_format($order->quantity) }}</td>
                    <td class="mono text-accent">${{ number_format($order->charge, 4) }}</td>
                    <td><span class="badge {{ $cls }}">{{ $label }}</span></td>
                    <td class="muted" style="font-size:11px">{{ $order->created_at->format('d M Y') }}</td>
                    <td>
                        <a href="{{ route('orders.show', $order) }}" class="btn btn-secondary btn-sm">
                            {{ __('messages.details') }}
                        </a>
                        @if($order->canCancel())
                            <form method="POST" action="{{ route('orders.cancel', $order) }}" style="display:inline" onsubmit="return confirm('{{ app()->getLocale()==='sn' ? 'Kanzura odha iyi?' : 'Cancel this order?' }}')">
                                @csrf
                                <button class="btn btn-danger btn-sm" type="submit">✕</button>
                            </form>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div style="padding:12px 16px;border-top:1px solid var(--border)">
            {{ $orders->links() }}
        </div>
    @endif
</div>
@endsection
