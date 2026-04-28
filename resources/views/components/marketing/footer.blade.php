<footer class="site-footer">
    <div class="container">
        <div class="site-footer-grid">
            <div>
                <img src="{{ asset('images/zimbosocials.png') }}" alt="Zimbo Social" class="footer-brand-logo">
                <p>Zimbabwe's trusted SMM platform for creators, businesses, and page marketers looking for real growth and measurable outcomes.</p>
                <div class="social-list">
                    <a href="#" class="social-icon" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="social-icon" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                    <a href="#" class="social-icon" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="social-icon" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
                </div>
            </div>

            <div>
                <h4>Services</h4>
                <ul>
                    <li><a href="{{ route('marketing.services') }}">Browse Services</a></li>
                    <li><a href="{{ route('marketing.services') }}">Bulk Orders</a></li>
                    <li><a href="{{ route('marketing.services') }}">Affiliate Program</a></li>
                    <li><a href="{{ route('marketing.contact') }}">API Access</a></li>
                </ul>
            </div>

            <div>
                <h4>Company</h4>
                <ul>
                    <li><a href="{{ route('marketing.about') }}">About Us</a></li>
                    <li><a href="{{ route('marketing.contact') }}">Contact</a></li>
                </ul>
            </div>

            <div>
                <h4>Support</h4>
                <ul>
                    <li><a href="{{ route('marketing.help') }}">Help Center</a></li>
                    <li><a href="{{ route('marketing.privacy') }}">Privacy Policy</a></li>
                    <li><a href="{{ route('marketing.terms') }}">Terms of Service</a></li>
                </ul>
            </div>
        </div>

        <div class="site-footer-bottom">
            <i class="fas fa-copyright"></i> {{ date('Y') }} Zimbo Social. All rights reserved.
        </div>
    </div>
</footer>
