<header class="container" style="position: sticky; top: 0; background: white; z-index: 100; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
    <nav class="nav">
        <a href="{{ route('marketing.home') }}" class="brand" aria-label="Zimbo Socials Home">
            <img src="{{ asset('images/zimbosocials.png') }}" alt="Zimbo Socials" class="brand-logo">
        </a>

        <!-- Mobile Menu Button -->
        <button class="nav-mobile-toggle" id="nav-mobile-toggle" aria-label="Toggle menu">
            <span></span>
            <span></span>
            <span></span>
        </button>

        <!-- Desktop Navigation -->
        <div class="nav-desktop">
            <a class="nav-link" href="{{ route('marketing.services') }}"><i class="fas fa-shopping-bag"></i>Our Services</a>
            <a class="nav-link" href="{{ route('marketing.contact') }}"><i class="fas fa-phone-alt"></i>Contact</a>
            <a class="btn btn-secondary" href="{{ route('login') }}"><i class="fas fa-sign-in-alt"></i>Login</a>
            <a class="btn btn-primary" href="{{ route('register') }}"><i class="fas fa-rocket"></i>Get Started</a>
        </div>

        <!-- Mobile Navigation -->
        <div class="nav-mobile" id="nav-mobile">
            <a class="nav-link" href="{{ route('marketing.services') }}"><i class="fas fa-shopping-bag"></i>Our Services</a>
            <a class="nav-link" href="{{ route('marketing.contact') }}"><i class="fas fa-phone-alt"></i>Contact</a>
            <div class="nav-mobile-actions">
                <a class="btn btn-secondary" href="{{ route('login') }}"><i class="fas fa-sign-in-alt"></i>Login</a>
                <a class="btn btn-primary" href="{{ route('register') }}"><i class="fas fa-rocket"></i>Get Started</a>
            </div>
        </div>
    </nav>
</header>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const toggle = document.getElementById('nav-mobile-toggle');
    const mobileNav = document.getElementById('nav-mobile');
    
    if (toggle && mobileNav) {
        toggle.addEventListener('click', function() {
            mobileNav.classList.toggle('active');
            toggle.classList.toggle('active');
        });
        
        // Close menu when clicking on a link
        mobileNav.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', function() {
                mobileNav.classList.remove('active');
                toggle.classList.remove('active');
            });
        });
    }
});
</script>
