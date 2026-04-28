{{-- resources/views/orders/create.blade.php --}}
@extends('layouts.app')

@section('page-title', __('messages.new_order'))
@section('page-sub', app()->getLocale()==='sn' ? 'Gadzira odha ichangoburwa' : 'Place a new social media order')

@section('content')
<div style="max-width:560px">
    <div class="card">
        <form method="POST" action="{{ route('orders.store') }}" id="order-form">
            @csrf

            {{-- Category --}}
            <div class="form-group">
                <label class="form-label">{{ app()->getLocale()==='sn' ? 'MHANDO YE SOCIAL MEDIA' : 'SOCIAL MEDIA CATEGORY' }}</label>
                <select class="form-control" id="sel-cat">
                    <option value="">{{ app()->getLocale()==='sn' ? '— Sarudza Mhando —' : '— Select Category —' }}</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat }}" {{ old('category') === $cat ? 'selected' : '' }}>
                            {{ ucfirst($cat) }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Service --}}
            <div class="form-group">
                <label class="form-label">{{ __('messages.service') }}</label>
                <select class="form-control" name="service_id" id="sel-service" required>
                    <option value="">{{ app()->getLocale()==='sn' ? '— Sarudza Sevhisi —' : '— Select Service —' }}</option>
                    @foreach($services as $svc)
                        <option value="{{ $svc->id }}"
                            data-cat="{{ $svc->category }}"
                            data-rate="{{ $svc->rate }}"
                            data-min="{{ $svc->min_qty }}"
                            data-max="{{ $svc->max_qty }}"
                            {{ old('service_id', $selected?->id) == $svc->id ? 'selected' : '' }}>
                            {{ app()->getLocale()==='sn' ? $svc->name_sn : $svc->name }}
                        </option>
                    @endforeach
                </select>
                @error('service_id') <div class="form-error">{{ $message }}</div> @enderror
            </div>

            {{-- Service info bar --}}
            <div id="service-info" style="display:none;background:var(--bg3);border:1px solid var(--border2);border-radius:6px;padding:10px 14px;margin-bottom:14px;font-size:11px">
                <div style="display:flex;gap:16px;flex-wrap:wrap">
                    <div><span style="color:var(--text3)">{{ __('messages.rate_per_1000') }}: </span><span class="mono text-accent" id="info-rate">—</span></div>
                    <div><span style="color:var(--text3)">{{ __('messages.min_qty') }}: </span><span class="mono" id="info-min">—</span></div>
                    <div><span style="color:var(--text3)">{{ __('messages.max_qty') }}: </span><span class="mono" id="info-max">—</span></div>
                </div>
            </div>

            {{-- Link --}}
            <div class="form-group">
                <label class="form-label">{{ __('messages.link') }}</label>
                <input type="url" name="link" class="form-control"
                    placeholder="https://instagram.com/yourpage"
                    value="{{ old('link') }}" required>
                @error('link') <div class="form-error">{{ $message }}</div> @enderror
            </div>

            {{-- Quantity --}}
            <div class="form-group">
                <label class="form-label" id="qty-label">{{ __('messages.quantity') }}</label>
                <input type="number" name="quantity" id="inp-qty" class="form-control"
                    placeholder="0" value="{{ old('quantity') }}" min="1" required>
                @error('quantity') <div class="form-error">{{ $message }}</div> @enderror
            </div>

            {{-- Quick quantity buttons --}}
            <div style="display:flex;gap:6px;margin-bottom:16px" id="qty-presets" style="display:none">
                @foreach([100, 500, 1000, 5000, 10000] as $preset)
                    <button type="button" class="btn btn-secondary btn-sm"
                        onclick="document.getElementById('inp-qty').value={{ $preset }};calcCharge()">
                        {{ number_format($preset) }}
                    </button>
                @endforeach
            </div>

            {{-- Charge display --}}
            <div style="background:var(--bg3);border:1px solid var(--border2);border-radius:6px;padding:12px 16px;display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
                <div>
                    <div style="font-size:10px;color:var(--text3);font-family:var(--mono)">{{ strtoupper(__('messages.charge')) }}</div>
                    <div style="font-size:10px;color:var(--text3);margin-top:2px">
                        {{ app()->getLocale()==='sn' ? 'Bharariro yangu:' : 'Your balance:' }}
                        <span class="mono text-accent">${{ number_format(auth()->user()->balance, 2) }}</span>
                    </div>
                </div>
                <div class="mono" style="font-size:24px;font-weight:600;color:var(--accent)" id="charge-out">$0.0000</div>
            </div>

            {{-- Warning --}}
            <div style="display:flex;gap:8px;background:rgba(255,165,2,.06);border:1px solid rgba(255,165,2,.2);border-radius:6px;padding:10px 12px;margin-bottom:16px;font-size:11px;color:var(--text2)">
                <span style="color:var(--warn);flex-shrink:0">⚠</span>
                {{ app()->getLocale()==='sn'
                    ? 'Tarisa kuti link yakakwana uye huwandu hwakakwana pamberi pokutumira.'
                    : 'Ensure the link is correct and quantity is within the allowed range before placing.' }}
            </div>

            <button type="submit" class="btn btn-primary btn-full" style="padding:12px">
                {{ __('messages.place_order') }}
            </button>
        </form>
    </div>
</div>

<script>
const services = @json($services);
const locale   = '{{ app()->getLocale() }}';

const selCat     = document.getElementById('sel-cat');
const selService = document.getElementById('sel-service');
const inpQty     = document.getElementById('inp-qty');
const infoBox    = document.getElementById('service-info');
const chargeOut  = document.getElementById('charge-out');
const qtyLabel   = document.getElementById('qty-label');
const infoRate   = document.getElementById('info-rate');
const infoMin    = document.getElementById('info-min');
const infoMax    = document.getElementById('info-max');

// Filter services when category changes
selCat.addEventListener('change', function () {
    const cat = this.value;
    Array.from(selService.options).forEach(opt => {
        if (!opt.value) return;
        opt.hidden = cat ? opt.dataset.cat !== cat : false;
    });
    selService.value = '';
    infoBox.style.display = 'none';
    chargeOut.textContent = '$0.0000';
});

// Update info bar and charge when service changes
selService.addEventListener('change', calcCharge);
inpQty.addEventListener('input', calcCharge);

function calcCharge() {
    const opt = selService.options[selService.selectedIndex];
    if (!opt || !opt.value) { infoBox.style.display='none'; chargeOut.textContent='$0.0000'; return; }

    const rate = parseFloat(opt.dataset.rate);
    const min  = parseInt(opt.dataset.min);
    const max  = parseInt(opt.dataset.max);
    const qty  = parseInt(inpQty.value) || 0;

    infoBox.style.display = 'block';
    infoRate.textContent  = '$' + rate.toFixed(4);
    infoMin.textContent   = min.toLocaleString();
    infoMax.textContent   = max.toLocaleString();
    qtyLabel.textContent  = (locale === 'sn' ? 'Huwandu' : 'Quantity') + ' (' + min.toLocaleString() + ' – ' + max.toLocaleString() + ')';

    const charge = (qty / 1000) * rate;
    chargeOut.textContent = '$' + charge.toFixed(4);
}

// Init if service pre-selected
if (selService.value) calcCharge();
</script>
@endsection
