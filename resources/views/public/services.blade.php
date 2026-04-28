@extends('layouts.marketing')

@section('title', 'Zimbo Social - Our Services')

@section('content')
<section class="hero">
    <h1>Our Services</h1>
    <p>Explore social media growth services by platform and choose what fits your campaign goals.</p>
</section>

@forelse($services as $category => $group)
<section class="section">
    <h2>{{ ucfirst($category) }}</h2>
    <div class="grid">
        @foreach($group as $service)
            <article class="card">
                <h3 style="font-size:16px;margin-bottom:8px;">{{ app()->getLocale()==='sn' ? $service->name_sn : $service->name }}</h3>
                <p style="font-weight:800;color:var(--brand-dark);">${{ number_format($service->rate, 4) }} / 1000</p>
                <p class="muted" style="margin-top:8px;">{{ number_format($service->min_qty) }} - {{ number_format($service->max_qty) }}</p>
            </article>
        @endforeach
    </div>
</section>
@empty
<section class="section"><p class="muted">No services are currently available.</p></section>
@endforelse
@endsection
