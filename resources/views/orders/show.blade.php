{{-- resources/views/orders/show.blade.php --}}
@extends('layouts.app')

@section('page-title', app()->getLocale()==='sn' ? 'Rondedzero yeOdha' : 'Order Details')
@section('page-sub', '#' . $order->id)

@section('topbar-actions')
    <a href="{{ route('orders.index') }}" class="btn btn-secondary btn-sm">← {{ __('messages.back') }}</a>
@endsection

@section('content')
<div style="max-width:600px">
    <div class="card" style="margin-bottom:16px">
        <div class="card-title">{{ __('messages.order_detail') }}</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0">
            @php
                $rows = [
                    [__('messages.order_id'),   '#'.$order->id, 'mono muted'],
                    [__('messages.service'),    app()->getLocale()==='sn' ? $order->service->name_sn : $order->service->name, ''],
                    [__('messages.link'),       $order->link, 'text-info'],
                    [__('messages.quantity'),   number_format($order->quantity), 'mono'],
                    [__('messages.charge'),     '$'.number_format($order->charge,4), 'mono text-accent'],
                    [__('messages.rate_per_1000'), '$'.number_format($order->rate_at_order,4).'/1000', 'mono muted'],
                    [__('messages.start_count'), $order->start_count ? number_format($order->start_count) : '—', 'mono'],
                    [__('messages.remains'),    $order->remains !== null ? number_format($order->remains) : '—', 'mono'],
                    [__('messages.date'),       $order->created_at->format('d M Y H:i'), 'muted'],
                ];
            @endphp
            @foreach($rows as [$label, $value, $cls])
            <div style="padding:10px 0;border-bottom:1px solid var(--border);font-size:11px;color:var(--text3)">{{ $label }}</div>
            <div style="padding:10px 0;border-bottom:1px solid var(--border);font-size:12px" class="{{ $cls }}">{{ $value }}</div>
            @endforeach
        </div>

        <div style="margin-top:16px;display:flex;gap:8px;align-items:center">
            @php
                $cls = match($order->status) {
                    'completed' => 'badge-success', 'pending' => 'badge-warn',
                    'processing','in_progress' => 'badge-info',
                    'cancelled','refunded' => 'badge-danger', default => 'badge-purple',
                };
            @endphp
            <span style="font-size:11px;color:var(--text3)">{{ __('messages.status') }}:</span>
            <span class="badge {{ $cls }}" style="font-size:12px;padding:4px 12px">
                {{ app()->getLocale()==='sn' ? $order->getStatusLabelSn() : $order->getStatusLabelEn() }}
            </span>
            @if($order->canCancel())
                <form method="POST" action="{{ route('orders.cancel', $order) }}" onsubmit="return confirm('{{ app()->getLocale()==='sn' ? 'Kanzura?' : 'Cancel this order?' }}')">
                    @csrf
                    <button class="btn btn-danger btn-sm">{{ __('messages.cancel_order') }}</button>
                </form>
            @endif
        </div>
    </div>
</div>
@endsection
