@extends('layouts.marketing')

@section('title', "Zimbo Social - Zimbabwe's #1 SMM Growth Platform")

@section('head')
<style>
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(30px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    @keyframes fadeInDown {
        from { opacity: 0; transform: translateY(-20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    @keyframes scaleIn {
        from { opacity: 0; transform: scale(0.95); }
        to { opacity: 1; transform: scale(1); }
    }
    
    @keyframes slideInLeft {
        from { opacity: 0; transform: translateX(-40px); }
        to { opacity: 1; transform: translateX(0); }
    }
    
    @keyframes slideInRight {
        from { opacity: 0; transform: translateX(40px); }
        to { opacity: 1; transform: translateX(0); }
    }
    
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.7; }
    }
    
    @keyframes bounce {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-8px); }
    }

    .home-section { padding: 72px 0; animation: fadeInUp 0.8s ease-out; }
    .home-section:nth-child(odd) { background: #fff; }
    .home-section:nth-child(even) { background: var(--bg-secondary); }
    
    .home-kicker {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 16px;
        border-radius: 999px;
        background: rgba(0, 158, 0, 0.12);
        color: var(--zim-green);
        font-weight: 600;
        font-size: 0.9rem;
        animation: slideInDown 0.6s ease-out;
    }

    .hero-wrap {
        padding: 90px 0 70px;
        position: relative;
        overflow: hidden;
        background:
            linear-gradient(135deg, rgba(0,158,0,0.06) 0%, rgba(206,17,38,0.06) 100%),
            repeating-linear-gradient(90deg, rgba(0,158,0,0.04) 0, rgba(0,158,0,0.04) 33.33%, rgba(255,215,0,0.04) 33.33%, rgba(255,215,0,0.04) 66.66%, rgba(206,17,38,0.04) 66.66%, rgba(206,17,38,0.04) 100%);
    }
    .hero-wrap::before {
        content: '';
        position: absolute;
        top: -120px;
        right: -120px;
        width: 360px;
        height: 360px;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(0,158,0,0.12) 0%, transparent 70%);
        pointer-events: none;
        animation: pulse 4s ease-in-out infinite;
    }
    .hero-wrap::after {
        content: '';
        position: absolute;
        bottom: -140px;
        left: -40px;
        width: 320px;
        height: 320px;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(206,17,38,0.12) 0%, transparent 70%);
        pointer-events: none;
        animation: pulse 4s ease-in-out infinite 0.5s;
    }

    .hero-inner { max-width: 940px; margin: 0 auto; text-align: center; position: relative; z-index: 1; }
    .hero-title {
        font-size: clamp(2rem, 6vw, 3.4rem);
        line-height: 1.08;
        margin: 20px 0;
        font-weight: 800;
        animation: fadeInUp 0.8s ease-out 0.1s both;
    }
    .hero-title .green { color: var(--zim-green); }
    .hero-title .blend {
        background: linear-gradient(135deg, var(--zim-red), var(--zim-gold));
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
    }
    .hero-copy {
        max-width: 760px;
        margin: 0 auto 28px;
        font-size: 1.08rem;
        color: var(--muted);
        animation: fadeInUp 0.8s ease-out 0.2s both;
    }
    .hero-actions {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        animation: fadeInUp 0.8s ease-out 0.3s both;
    }

    .trust-grid {
        margin-top: 40px;
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 16px;
    }
    .trust-card {
        background: #fff;
        border-radius: 12px;
        padding: 18px;
        border: 2px solid var(--line);
        animation: scaleIn 0.6s ease-out;
        transition: all 0.3s ease;
        cursor: pointer;
    }
    .trust-card:hover {
        box-shadow: 0 12px 24px rgba(0, 158, 0, 0.15);
        transform: translateY(-4px);
    }
    .trust-card strong { display: block; font-size: 1.9rem; font-weight: 800; animation: pulse 2s ease-in-out infinite; }
    .trust-card p { color: var(--muted); font-size: 0.92rem; }

    .section-title { text-align: center; margin-bottom: 42px; }
    .section-title h2 { font-size: clamp(1.6rem, 4.2vw, 2.5rem); margin-bottom: 8px; animation: fadeInDown 0.6s ease-out; }
    .section-title p { color: var(--muted); animation: fadeInDown 0.6s ease-out 0.1s both; }

    .feature-grid,
    .platform-grid,
    .marketer-grid,
    .business-grid { display: grid; gap: 22px; }

    .feature-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
    .feature-card {
        background: #fff;
        border-radius: 16px;
        padding: 28px;
        border-left: 4px solid var(--zim-green);
        animation: fadeInUp 0.6s ease-out;
        transition: all 0.3s ease;
        cursor: pointer;
    }
    .feature-card:hover {
        box-shadow: 0 16px 32px rgba(0, 158, 0, 0.12);
        transform: translateY(-6px);
        border-left-color: var(--zim-red);
    }
    .feature-card i { font-size: 2rem; margin-bottom: 10px; animation: bounce 2s ease-in-out infinite; }
    .feature-card p { color: var(--muted); }

    .platform-grid { grid-template-columns: repeat(6, minmax(0, 1fr)); }
    .platform-card {
        background: #fff;
        border-radius: 16px;
        text-align: center;
        border: 2px solid transparent;
        padding: 24px 14px;
        transition: all 0.3s ease;
        animation: scaleIn 0.5s ease-out;
    }
    .platform-card:hover {
        border-color: var(--zim-green);
        box-shadow: 0 12px 28px rgba(0, 158, 0, 0.2);
        transform: translateY(-6px) scale(1.05);
    }
    .platform-card i { font-size: 2rem; margin-bottom: 10px; }

    .services-wrap {
        position: relative;
        overflow: hidden;
    }
    .services-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        margin-bottom: 18px;
        animation: fadeInUp 0.6s ease-out;
    }
    .services-header > div { flex: 1; min-width: 250px; }
    .services-header h2 { font-size: clamp(1.6rem, 4vw, 2.3rem); margin-bottom: 6px; }
    .services-header p { color: var(--muted); }
    
    .services-row {
        display: flex;
        gap: 18px;
        overflow-x: auto;
        scroll-snap-type: x mandatory;
        scroll-padding-left: 4px;
        padding: 8px 0 8px;
    }
    .services-row::-webkit-scrollbar { height: 8px; }
    .services-row::-webkit-scrollbar-thumb { background: #d0d7de; border-radius: 999px; }
    .services-row.auto-scroll { overflow-x: hidden; }
    
    .service-card {
        flex: 0 0 320px;
        background: #fff;
        border-radius: 16px;
        border: 2px solid var(--line);
        padding: 22px;
        scroll-snap-align: start;
        transition: all 0.3s ease;
        position: relative;
        animation: slideInRight 0.6s ease-out;
    }
    .service-card:hover {
        border-color: var(--zim-green);
        transform: translateY(-8px);
        box-shadow: 0 16px 32px rgba(0,158,0,0.2);
    }
    .service-chip {
        display: inline-flex;
        margin-bottom: 10px;
        font-size: 0.72rem;
        font-weight: 700;
        color: #fff;
        background: linear-gradient(135deg, var(--zim-green), var(--zim-gold));
        padding: 6px 10px;
        border-radius: 999px;
        animation: pulse 2s ease-in-out infinite;
    }
    .service-card h3 { margin-bottom: 8px; font-size: 1.06rem; font-weight: 700; }
    .service-card p { color: var(--muted); margin-bottom: 14px; font-size: 0.92rem; }
    .service-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        font-size: 0.85rem;
        color: var(--muted);
        margin-bottom: 14px;
        border-top: 1px solid var(--line);
        border-bottom: 1px solid var(--line);
        padding: 10px 0;
    }

    .steps-grid { display: grid; gap: 24px; grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .step-card { text-align: center; padding: 14px; animation: fadeInUp 0.6s ease-out; transition: all 0.3s ease; }
    .step-card:hover { transform: translateY(-4px); }
    .step-number {
        width: 64px;
        height: 64px;
        margin: 0 auto 12px;
        border-radius: 50%;
        display: grid;
        place-items: center;
        color: #fff;
        font-weight: 700;
        font-size: 1.5rem;
        animation: bounce 2s ease-in-out infinite;
    }
    .step-card h3 { font-weight: 700; margin-bottom: 8px; }

    .marketer-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
    .marketer-card {
        background: #fff;
        border-radius: 16px;
        padding: 28px;
        border-left: 4px solid var(--zim-green);
        animation: fadeInUp 0.6s ease-out;
        transition: all 0.3s ease;
    }
    .marketer-card:hover {
        box-shadow: 0 16px 32px rgba(0, 158, 0, 0.12);
        transform: translateY(-6px);
        border-left-color: var(--zim-red);
    }
    .marketer-card i { font-size: 2rem; margin-bottom: 10px; animation: bounce 2s ease-in-out infinite; }
    .marketer-card p { color: var(--muted); }
    .marketer-card h3 { font-weight: 700; margin-bottom: 10px; }

    .business-grid {
        grid-template-columns: minmax(0, 1.1fr) minmax(0, 0.9fr);
        align-items: start;
        animation: fadeInUp 0.8s ease-out;
    }
    .business-panel {
        background: linear-gradient(135deg, rgba(0,158,0,0.08), rgba(206,17,38,0.08));
        border: 2px solid var(--zim-green);
        border-radius: 18px;
        padding: 24px;
        animation: slideInRight 0.8s ease-out;
    }
    .network-item {
        background: #fff;
        border-radius: 12px;
        border-left: 4px solid var(--zim-green);
        padding: 18px;
        margin-bottom: 14px;
        transition: all 0.3s ease;
        animation: fadeInUp 0.6s ease-out;
    }
    .network-item:hover {
        transform: translateX(8px);
        box-shadow: 0 8px 16px rgba(0, 158, 0, 0.1);
    }
    .network-item h4 { font-weight: 700; margin-bottom: 6px; }
    .network-item p { margin: 0; }

    .cta-wrap {
        border-radius: 18px;
        background: linear-gradient(135deg, var(--zim-green), var(--zim-red));
        color: #fff;
        text-align: center;
        padding: 54px 26px;
        animation: scaleIn 0.8s ease-out;
        position: relative;
        overflow: hidden;
    }
    .cta-wrap::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: repeating-linear-gradient(90deg, transparent, transparent 2px, rgba(255,255,255,0.05) 2px, rgba(255,255,255,0.05) 4px);
        pointer-events: none;
    }
    .cta-wrap h2 { position: relative; z-index: 1; animation: fadeInDown 0.6s ease-out 0.2s both; }
    .cta-wrap p { position: relative; z-index: 1; opacity: 0.95; margin: 10px 0 22px; animation: fadeInUp 0.6s ease-out 0.3s both; }
    .cta-wrap .btn { position: relative; z-index: 1; animation: fadeInUp 0.6s ease-out 0.4s both; }

    @media (max-width: 1080px) {
        .feature-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .platform-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .marketer-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .business-grid { grid-template-columns: 1fr; }
    }

    @media (max-width: 720px) {
        .home-section { padding: 54px 0; }
        .hero-wrap { padding: 66px 0 54px; }
        .hero-copy { font-size: 0.98rem; }
        .trust-grid { grid-template-columns: 1fr; }
        .steps-grid { grid-template-columns: 1fr; }
        .platform-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .feature-grid,
        .marketer-grid { grid-template-columns: 1fr; }
        .service-card { flex-basis: 88vw; }
    }
</style>
@endsection

@section('content')
<section class="hero-wrap">
    <div class="container hero-inner">
        <span class="home-kicker"><i class="fas fa-certificate"></i>Zimbabwe's Trusted SMM Platform</span>
        <h1 class="hero-title">Accelerate Your <span class="green">Social Media Growth</span></h1>
        <p class="hero-copy">
            Professional social media services for creators, agencies, brands, and businesses. Real followers, authentic engagement, and proven results across Instagram, YouTube, TikTok, Facebook, Twitter/X, and Telegram.
        </p>
        <div class="hero-actions">
            <a class="btn btn-primary" href="{{ route('marketing.services') }}"><i class="fas fa-shopping-bag"></i>Explore Services</a>
            <a class="btn btn-secondary" href="{{ route('register') }}"><i class="fas fa-user-plus"></i>Start Free</a>
        </div>

        <div class="trust-grid">
            <article class="trust-card" style="border-color: var(--zim-green);"><strong style="color: var(--zim-green);">10K+</strong><p>Successful Orders</p></article>
            <article class="trust-card" style="border-color: var(--zim-gold);"><strong style="color: var(--zim-gold);">24/7</strong><p>Expert Support</p></article>
            <article class="trust-card" style="border-color: var(--zim-red);"><strong style="color: var(--zim-red);">99%</strong><p>Client Satisfaction</p></article>
        </div>
    </div>
</section>

<section class="home-section" style="background: #fff;">
    <div class="container">
        <div class="section-title">
            <h2>Why Trust Zimbo Social?</h2>
            <p>Platform built for Zimbabwe's creators, businesses, and growth professionals</p>
        </div>

        <div class="feature-grid">
            <article class="feature-card" style="border-left-color: var(--zim-green);">
                <i class="fas fa-bolt" style="color: var(--zim-green);"></i>
                <h3>Fast Delivery</h3>
                <p>Most services begin delivery within minutes. Get real results quickly and efficiently.</p>
            </article>
            <article class="feature-card" style="border-left-color: var(--zim-red);">
                <i class="fas fa-shield-alt" style="color: var(--zim-red);"></i>
                <h3>Secure & Private</h3>
                <p>Your account security is our priority. We never request passwords or sensitive data.</p>
            </article>
            <article class="feature-card" style="border-left-color: var(--zim-gold);">
                <i class="fas fa-dollar-sign" style="color: var(--zim-gold);"></i>
                <h3>Transparent Pricing</h3>
                <p>Competitive rates for all businesses and creators. No hidden fees or surprise charges.</p>
            </article>
            <article class="feature-card" style="border-left-color: var(--zim-black);">
                <i class="fas fa-users" style="color: var(--zim-black);"></i>
                <h3>Local Support</h3>
                <p>Zimbabwe-based team available to assist with orders, deposits, and account issues.</p>
            </article>
        </div>
    </div>
</section>

<section class="home-section" style="background: var(--bg-secondary);">
    <div class="container">
        <div class="section-title">
            <h2>Popular Platforms</h2>
            <p>Pick a channel and launch your growth strategy.</p>
        </div>

        <div class="platform-grid">
            @foreach($categories as $category)
                <a href="{{ route('marketing.services', ['category' => $category]) }}" class="platform-card">
                    @if($category === 'instagram')
                        <i class="fab fa-instagram" style="color:#E4405F;"></i>
                    @elseif($category === 'youtube')
                        <i class="fab fa-youtube" style="color:#FF0000;"></i>
                    @elseif($category === 'tiktok')
                        <i class="fab fa-tiktok" style="color:#000000;"></i>
                    @elseif($category === 'facebook')
                        <i class="fab fa-facebook" style="color:#1877F2;"></i>
                    @elseif($category === 'twitter')
                        <i class="fab fa-x-twitter" style="color:#000000;"></i>
                    @elseif($category === 'telegram')
                        <i class="fab fa-telegram" style="color:#0088cc;"></i>
                    @else
                        <i class="fas fa-globe"></i>
                    @endif
                    <h3 style="font-size: 1rem;">{{ ucfirst($category) }}</h3>
                    <p style="font-size: 0.85rem; color: var(--muted);">Followers, Likes & Views</p>
                </a>
            @endforeach
        </div>
    </div>
</section>

<section class="home-section" style="background: #fff;">
    <div class="container">
        <div class="services-header">
            <div>
                <h2 style="font-size: clamp(1.6rem, 4vw, 2.3rem); margin-bottom: 6px;">Featured Services</h2>
                <p style="color: var(--muted);">Carefully curated services across platforms. Hover to explore details.</p>
            </div>
            <a href="{{ route('marketing.services') }}" class="btn btn-primary"><i class="fas fa-arrow-right"></i>View All</a>
        </div>

        <div class="services-wrap">
            <div class="services-row auto-scroll" id="servicesCarousel">
                @forelse($featuredServices as $service)
                    <article class="service-card">
                        @if($loop->first)
                            <span class="service-chip"><i class="fas fa-fire"></i>Popular</span>
                        @endif
                        <h3>{{ app()->getLocale() === 'sn' ? $service->name_sn : $service->name }}</h3>
                        <span class="pill">{{ strtoupper($service->category) }}</span>
                        <p>Boost your {{ strtolower($service->category) }} presence with high-quality engagement and steady delivery.</p>
                        <div class="service-meta">
                            <span><i class="fas fa-cube" style="color: var(--zim-green);"></i>Min: {{ number_format($service->min_qty) }}</span>
                            <span><i class="fas fa-cube" style="color: var(--zim-red);"></i>Max: {{ number_format($service->max_qty) }}</span>
                        </div>
                        <a href="{{ route('login') }}" class="btn btn-primary" style="width:100%;text-align:center;"><i class="fas fa-cart-shopping"></i>Order Now</a>
                    </article>
                @empty
                    <p class="muted">No featured services are available right now.</p>
                @endforelse
            </div>
        </div>
    </div>
</section>

<section class="home-section" style="background: var(--bg-secondary);">
    <div class="container">
        <div class="section-title">
            <h2>Getting Started</h2>
            <p>Three simple steps to begin growing your social presence</p>
        </div>

        <div class="steps-grid">
            <article class="step-card">
                <div class="step-number" style="background: linear-gradient(135deg, var(--zim-green), var(--zim-gold));">1</div>
                <h3>Register Your Account</h3>
                <p class="muted">Create a free account in seconds with basic information.</p>
            </article>
            <article class="step-card">
                <div class="step-number" style="background: linear-gradient(135deg, var(--zim-red), var(--zim-gold));">2</div>
                <h3>Select Services</h3>
                <p class="muted">Choose platforms, services, and packages that fit your goals.</p>
            </article>
            <article class="step-card">
                <div class="step-number" style="background: linear-gradient(135deg, var(--zim-green), var(--zim-red));">3</div>
                <h3>Track & Grow</h3>
                <p class="muted">Monitor real-time progress and achieve sustainable growth.</p>
            </article>
        </div>
    </div>
</section>

<section class="home-section" style="background: var(--bg-secondary);">
    <div class="container">
        <div class="section-title">
            <h2>Earn as a Marketer</h2>
            <p>Monetize your audience by taking marketing contracts through our dedicated marketer platform</p>
        </div>

        <div class="marketer-grid">
            <article class="marketer-card" style="border-left-color: var(--zim-green);">
                <i class="fas fa-briefcase" style="color: var(--zim-green);"></i>
                <h3>Discover Contracts</h3>
                <p>Access active campaigns that match your page audience and engagement levels.</p>
            </article>
            <article class="marketer-card" style="border-left-color: var(--zim-red);">
                <i class="fas fa-money-bill-wave" style="color: var(--zim-red);"></i>
                <h3>Guaranteed Payment</h3>
                <p>Get paid per approved post with transparent rates and escrow protection.</p>
            </article>
            <article class="marketer-card" style="border-left-color: var(--zim-gold);">
                <i class="fas fa-chart-line" style="color: var(--zim-gold);"></i>
                <h3>Manage Performance</h3>
                <p>Track metrics, earnings, and page statistics from your marketer dashboard.</p>
            </article>
            <article class="marketer-card" style="border-left-color: var(--zim-black);">
                <i class="fas fa-handshake" style="color: var(--zim-black);"></i>
                <h3>Professional Support</h3>
                <p>Dedicated platform and team to ensure smooth campaign execution.</p>
            </article>
        </div>

        <div style="text-align:center;margin-top:28px;">
            <a href="{{ route('register') }}" class="btn btn-primary"><i class="fas fa-sign-in-alt"></i>Become a Marketer</a>
        </div>
    </div>
</section>

<section class="home-section" style="background: #fff;">
    <div class="container business-grid">
        <div>
            <span class="home-kicker" style="margin-bottom: 12px;"><i class="fas fa-building"></i>B2B MARKETING</span>
            <h2 style="font-size: clamp(1.8rem, 4vw, 2.6rem); margin-bottom: 12px;">Launch Campaigns on <span style="color: var(--zim-red);">Premium Pages</span></h2>
            <p style="color: var(--muted); margin-bottom: 16px;">Reach qualified audiences through Zimbabwe's most engaged social media pages and content creators. Measurable results with transparent reporting.</p>

            <div style="display:grid;gap:12px;">
                <p><i class="fas fa-check-circle" style="color: var(--zim-green);"></i>Access to verified high-traffic pages</p>
                <p><i class="fas fa-check-circle" style="color: var(--zim-red);"></i>Partnerships with established content creators</p>
                <p><i class="fas fa-check-circle" style="color: var(--zim-gold);"></i>Targeted reach by audience and platform</p>
                <p><i class="fas fa-check-circle" style="color: var(--zim-black);"></i>Complete analytics and ROI reporting</p>
            </div>

            <div style="margin-top: 18px;">
                <a href="{{ route('register') }}" class="btn btn-primary"><i class="fas fa-rocket"></i>Start B2B Campaign</a>
            </div>
        </div>

        <div class="business-panel">
            <article class="network-item" style="border-left-color:#1877F2;">
                <h4><i class="fab fa-facebook" style="color:#1877F2;"></i>Facebook Pages</h4>
                <p class="muted">500K+ combined followers</p>
            </article>
            <article class="network-item" style="border-left-color:#E4405F;">
                <h4><i class="fab fa-instagram" style="color:#E4405F;"></i>Instagram Accounts</h4>
                <p class="muted">400K+ verified followers</p>
            </article>
            <article class="network-item" style="border-left-color:#000;">
                <h4><i class="fab fa-tiktok" style="color:#000;"></i>TikTok Creators</h4>
                <p class="muted">600K+ active followers</p>
            </article>
            <article class="network-item" style="border-left-color:#FF0000; margin-bottom: 0;">
                <h4><i class="fab fa-youtube" style="color:#FF0000;"></i>YouTube Channels</h4>
                <p class="muted">300K+ subscriber network</p>
            </article>
        </div>
    </div>
</section>

<section class="home-section">
    <div class="container">
        <div class="cta-wrap">
            <h2 style="font-size: clamp(1.8rem, 4vw, 2.5rem);">Start Growing Today</h2>
            <p>Join thousands of creators, businesses, and marketers on Zimbo Social.</p>
            <a href="{{ route('register') }}" class="btn" style="background:#fff;color:var(--zim-green);font-weight:700;">
                <i class="fas fa-arrow-right"></i>Get Started Free
            </a>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-scrolling carousel for services
    const carousel = document.getElementById('servicesCarousel');
    if (carousel && carousel.children.length > 0) {
        let scrollIndex = 0;
        const cardWidth = 320 + 18; // card width + gap
        const visibleCards = Math.floor(carousel.parentElement.offsetWidth / cardWidth);
        
        setInterval(() => {
            scrollIndex = (scrollIndex + 1) % (carousel.children.length - visibleCards + 1);
            carousel.scrollTo({
                left: scrollIndex * cardWidth,
                behavior: 'smooth'
            });
        }, 5000);
    }

    // Intersection Observer for fade-in animations on scroll
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.animation = 'fadeInUp 0.8s ease-out forwards';
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    // Apply observer to specific elements
    document.querySelectorAll('.service-card, .feature-card, .step-card, .marketer-card, .platform-card').forEach(el => {
        observer.observe(el);
    });

    // CTA button interactions
    document.querySelectorAll('.btn').forEach(btn => {
        btn.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
        });
        btn.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });

    // Smooth scroll for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            if (href !== '#') {
                e.preventDefault();
                const target = document.querySelector(href);
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth' });
                }
            }
        });
    });
});
</script>
@endsection
