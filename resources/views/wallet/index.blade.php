{{-- resources/views/wallet/index.blade.php --}}
@extends('layouts.app')

@section('page-title', __('messages.wallet'))
@section('page-sub', app()->getLocale()==='sn' ? 'Wedzera mari uye tarisa mabiko' : 'Add funds and view transactions')

@section('content')

{{-- ── Balance Card ── --}}
<div style="background:linear-gradient(135deg,var(--bg3),var(--bg4));border:1px solid var(--border2);border-radius:10px;padding:24px;margin-bottom:20px;position:relative;overflow:hidden">
    <div style="position:absolute;right:-16px;top:-16px;font-size:100px;color:rgba(0,229,160,.04);font-family:var(--mono);font-weight:700;line-height:1;pointer-events:none">$</div>
    <div style="font-size:10px;color:var(--text3);font-family:var(--mono);letter-spacing:1.5px;text-transform:uppercase">
        {{ __('messages.my_balance') }}
    </div>
    <div style="font-size:36px;font-weight:600;color:var(--accent);font-family:var(--mono);margin:8px 0 6px">
        ${{ number_format(auth()->user()->balance, 2) }}
    </div>
    <div style="font-size:12px;color:var(--text2)">
        {{ __('messages.total_deposited') }}: <span class="mono text-accent">${{ number_format($totals['deposited'], 2) }}</span>
        &nbsp;|&nbsp;
        {{ __('messages.total_spent_lbl') }}: <span class="mono" style="color:var(--accent2)">${{ number_format($totals['spent'], 2) }}</span>
    </div>
</div>

<div class="grid-2" style="gap:20px">

    {{-- ── Add Funds ── --}}
    <div>
        <div class="section-header">
            <div class="section-title">{{ strtoupper(__('messages.add_funds')) }}</div>
        </div>
        <div class="card">
            @if($manualPaymentDetails->isNotEmpty())
                <div style="background:rgba(255,255,255,.02);border:1px solid var(--border);border-radius:8px;padding:10px 12px;margin-bottom:14px;">
                    <div style="font-size:10px;color:var(--text3);font-family:var(--mono);letter-spacing:.5px;margin-bottom:8px;">MANUAL PAYMENT DETAILS</div>
                    @foreach($manualPaymentDetails as $detail)
                        <div style="padding:8px 0;border-bottom:1px solid var(--border);">
                            <div style="font-size:12px;font-weight:600;color:var(--text);">{{ $detail->label }}</div>
                            @if($detail->account_name)
                                <div style="font-size:11px;color:var(--text2)">Name: {{ $detail->account_name }}</div>
                            @endif
                            @if($detail->account_number)
                                <div style="font-size:11px;color:var(--text2)">Account: {{ $detail->account_number }}</div>
                            @endif
                            @if($detail->instructions)
                                <div style="font-size:11px;color:var(--text3);margin-top:3px">{{ $detail->instructions }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('wallet.add') }}">
                @csrf

                <div class="form-group">
                    <label class="form-label">{{ __('messages.payment_method') }}</label>
                    <select name="method" class="form-control" required>
                        @foreach($availableMethods as $methodKey => $methodLabel)
                            <option value="{{ $methodKey }}" {{ old('method') === $methodKey ? 'selected' : '' }}>{{ $methodLabel }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">{{ __('messages.amount') }} (USD)</label>
                    <input type="number" name="amount" id="fund-amount" class="form-control"
                        placeholder="0.00" min="1" max="10000" step="0.01"
                        value="{{ old('amount') }}" required>
                </div>

                {{-- Quick amounts --}}
                <div style="display:flex;gap:6px;margin-bottom:14px">
                    @foreach([5, 10, 20, 50, 100] as $a)
                        <button type="button" class="btn btn-secondary btn-sm" style="flex:1"
                            onclick="document.getElementById('fund-amount').value='{{ $a }}'">
                            ${{ $a }}
                        </button>
                    @endforeach
                </div>

                <div style="background:rgba(78,205,196,.06);border:1px solid rgba(78,205,196,.2);border-radius:6px;padding:10px 12px;margin-bottom:14px;font-size:11px;color:var(--text2)">
                    ℹ {{ app()->getLocale()==='sn'
                        ? 'Kubhadhara kuchaitwa kuburikidza nenzira yaunosarudza. Bharariro ichawedzerwa nekuchinjika.'
                        : 'Payment will be processed via your selected method. Balance will be credited after confirmation.' }}
                </div>

                <button type="submit" class="btn btn-primary btn-full" style="padding:11px">
                    {{ __('messages.add_funds') }}
                </button>
            </form>
        </div>
    </div>

    {{-- ── Transaction History ── --}}
    <div>
        <div class="section-header">
            <div class="section-title">{{ strtoupper(__('messages.tx_history')) }}</div>
        </div>
        <div class="table-wrap" style="max-height:400px;overflow-y:auto">
            @if($transactions->isEmpty())
                <div class="empty-state"><p>{{ app()->getLocale()==='sn' ? 'Hamuna mabiko.' : 'No transactions yet.' }}</p></div>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>{{ app()->getLocale()==='sn' ? 'Mhando' : 'Type' }}</th>
                            <th>{{ __('messages.amount') }}</th>
                            <th>{{ app()->getLocale()==='sn' ? 'Nzira' : 'Method' }}</th>
                            <th>{{ __('messages.status') }}</th>
                            <th>{{ __('messages.date') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($transactions as $tx)
                        <tr>
                            <td>
                                <span class="badge {{ $tx->type==='deposit' ? 'badge-success' : ($tx->type==='refund' ? 'badge-info' : 'badge-warn') }}">
                                    {{ app()->getLocale()==='sn' ? $tx->getTypeLabelSn() : ucfirst(str_replace('_',' ',$tx->type)) }}
                                </span>
                            </td>
                            <td class="mono {{ $tx->amount > 0 ? 'text-accent' : 'text-accent2' }}" style="font-weight:500">
                                {{ $tx->amount > 0 ? '+' : '' }}${{ number_format(abs($tx->amount), 2) }}
                            </td>
                            <td class="muted" style="font-size:11px">{{ $tx->method ?? '—' }}</td>
                            <td>
                                <span class="badge {{ $tx->status==='completed' ? 'badge-success' : ($tx->status==='pending' ? 'badge-warn' : 'badge-danger') }}">
                                    {{ $tx->status }}
                                </span>
                            </td>
                            <td class="muted" style="font-size:11px">{{ $tx->created_at->format('d M Y') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                <div style="padding:10px 14px;border-top:1px solid var(--border)">
                    {{ $transactions->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
