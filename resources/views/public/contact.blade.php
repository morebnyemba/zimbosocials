@extends('layouts.marketing')

@section('title', 'Zimbo Socials - Contact')

@section('content')
<section class="hero">
    <h1>Contact and Payment Info</h1>
    <p>Need help with orders or deposits? Use the channels below and follow payment instructions carefully.</p>
</section>

<section class="section">
    <h2>Support</h2>
    <div class="card">
        <p><strong>Email:</strong> support@zimsocials.co.zw</p>
        <p style="margin-top:8px;"><strong>Hours:</strong> Mon-Sun, 08:00 - 22:00 CAT</p>
    </div>
</section>

<section class="section">
    <h2>Manual Payment Details</h2>
    @if($paymentDetails->isEmpty())
        <p class="muted">Payment details are currently unavailable. Please contact support.</p>
    @else
        <div class="grid">
            @foreach($paymentDetails as $detail)
                <article class="card">
                    <span class="pill">{{ strtoupper($detail->method_key) }}</span>
                    <h3 style="font-size:16px;margin-bottom:8px;">{{ $detail->label }}</h3>
                    @if($detail->account_name)
                        <p><strong>Name:</strong> {{ $detail->account_name }}</p>
                    @endif
                    @if($detail->account_number)
                        <p><strong>Account:</strong> {{ $detail->account_number }}</p>
                    @endif
                    @if($detail->instructions)
                        <p class="muted" style="margin-top:8px;">{{ $detail->instructions }}</p>
                    @endif
                </article>
            @endforeach
        </div>
    @endif
</section>
@endsection
