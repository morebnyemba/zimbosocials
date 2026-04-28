@extends('layouts.marketing')

@section('title', 'Help Center - Zimbo Socials')

@section('content')
<section class="hero">
    <h1>Help Center</h1>
    <p>Answers to common questions about orders, deposits, and marketer contracts.</p>
</section>

<section class="section">
    <h2>Frequently Asked Questions</h2>
    <div class="grid">
        <article class="card">
            <h3 style="font-size:16px;margin-bottom:8px;">How long does delivery take?</h3>
            <p class="muted">Most orders start within minutes. Full delivery time depends on service volume and platform conditions.</p>
        </article>
        <article class="card">
            <h3 style="font-size:16px;margin-bottom:8px;">How do I deposit funds?</h3>
            <p class="muted">Open Contact and Payment Info, choose a payment method, and follow the instructions shown.</p>
        </article>
        <article class="card">
            <h3 style="font-size:16px;margin-bottom:8px;">Can I become a marketer?</h3>
            <p class="muted">Yes. Register and use the marketer dashboard to view and accept available campaign contracts.</p>
        </article>
        <article class="card">
            <h3 style="font-size:16px;margin-bottom:8px;">Do you require my password?</h3>
            <p class="muted">No. We never ask for social media account passwords.</p>
        </article>
    </div>
</section>
@endsection
