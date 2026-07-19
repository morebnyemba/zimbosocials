<?php

// routes/web.php

use App\Http\Controllers\Admin\AdminWhatsAppController;
use App\Http\Controllers\Admin\WhatsAppTemplateController;
use App\Http\Controllers\WhatsAppWebhookController;
use App\Http\Controllers\AdminCampaignController;
use App\Http\Controllers\AdminContractController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminLeaderboardController;
use App\Http\Controllers\AdminMarketerController;
use App\Http\Controllers\AdminOrderController;
use App\Http\Controllers\AdminPaymentDetailController;
use App\Http\Controllers\AdminServiceController;
use App\Http\Controllers\AdminSettingsController;
use App\Http\Controllers\AdminTicketController;
use App\Http\Controllers\AdminTransactionController;
use App\Http\Controllers\AdminUpstreamProviderController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\ContractProofController;
use App\Http\Controllers\ContractReviewController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\MarketerController;
use App\Http\Controllers\MarketerSocialLinkController;
use App\Http\Controllers\MarketingController;
use App\Http\Controllers\MonetizerController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaynowController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReferralController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\AdminTranslationController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TranslationContributionController;
use App\Http\Controllers\WalletController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/locale', function (Request $req) {
    $locale = $req->input('locale', 'sn');
    if (! in_array($locale, ['sn', 'en', 'nd'])) {
        $locale = 'sn';
    }
    session(['locale' => $locale]);

    if (auth()->check()) {
        auth()->user()->update(['locale' => $locale]);
    }

    return back();
})->name('locale.switch');

Route::get('/', [MarketingController::class, 'home'])->name('marketing.home');
Route::get('/contact', [MarketingController::class, 'contact'])->name('marketing.contact');

// Static marketing pages — 5-minute public cache
Route::middleware('cache.headers:public;max_age=300;etag')->group(function () {
    Route::get('/our-services', [MarketingController::class, 'services'])->name('marketing.services');
    Route::get('/referral-program', [MarketingController::class, 'referralProgram'])->name('marketing.referral-program');
    Route::get('/about', [MarketingController::class, 'about'])->name('marketing.about');
    Route::get('/help-center', [MarketingController::class, 'help'])->name('marketing.help');
    Route::get('/privacy-policy', [MarketingController::class, 'privacy'])->name('marketing.privacy');
    Route::get('/terms-of-service', [MarketingController::class, 'terms'])->name('marketing.terms');
});

// Public marketer portfolio
Route::get('/marketers/{user}', [PortfolioController::class, 'show'])->name('portfolio.show');

// ─── Guest routes ─────────────────────────────────────────────────────────────

Route::middleware('guest')->group(function () {
    Route::get('/home', [MarketingController::class, 'home'])->name('home');
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

    // Admin second factor (emailed code)
    Route::get('/login/verify', [AuthController::class, 'show2fa'])->name('2fa.show');
    Route::post('/login/verify', [AuthController::class, 'verify2fa'])->middleware('throttle:10,1')->name('2fa.verify');
    Route::post('/login/verify/resend', [AuthController::class, 'resend2fa'])->middleware('throttle:3,1')->name('2fa.resend');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::get('/username-available', [AuthController::class, 'checkUsername'])
        ->middleware('throttle:30,1')->name('username.check');

    // Password reset
    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:5,1')->name('password.store');
});

// ─── Authenticated routes ─────────────────────────────────────────────────────

// Paynow Webhook (No Auth)
Route::post('/paynow/webhook', [PaynowController::class, 'webhook'])->name('paynow.update');

Route::middleware('auth')->group(function () {
    // Email Verification
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');
    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    // Password confirmation & change
    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])->name('password.confirm');
    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);
    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    // Community translations — customers propose, admins approve
    Route::get('/translations', [TranslationContributionController::class, 'index'])->name('translations.index');
    Route::post('/translations', [TranslationContributionController::class, 'store'])
        ->middleware('throttle:30,1')->name('translations.store');

    // Account profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // ─── Admin panel ──────────────────────────────────────────────────────────
    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'index'])->name('dashboard');
        Route::get('/analytics/summary', [AdminController::class, 'aiSummary'])
            ->middleware('throttle:ai-drafts')->name('analytics.summary');

        // Payment details
        Route::get('/payment-details', [AdminPaymentDetailController::class, 'index'])->name('payment-details.index');
        Route::post('/payment-details', [AdminPaymentDetailController::class, 'store'])->name('payment-details.store');
        Route::put('/payment-details/{manualPaymentDetail}', [AdminPaymentDetailController::class, 'update'])->name('payment-details.update');
        Route::delete('/payment-details/{manualPaymentDetail}', [AdminPaymentDetailController::class, 'destroy'])->name('payment-details.destroy');

        // User management
        Route::get('/users/create', [AdminUserController::class, 'create'])->name('users.create');
        Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::get('/users/{user}', [AdminUserController::class, 'show'])->name('users.show');
        Route::post('/users/{user}/toggle', [AdminUserController::class, 'toggleActive'])->name('users.toggle');
        Route::post('/users/{user}/role', [AdminUserController::class, 'changeRole'])->name('users.role');
        Route::post('/users/{user}/balance', [AdminUserController::class, 'adjustBalance'])->name('users.balance');
        Route::post('/users/{user}/impersonate', [AdminUserController::class, 'impersonate'])->name('users.impersonate');
        Route::post('/users/{user}/reset-password', [AdminUserController::class, 'sendPasswordReset'])->name('users.reset-password');
        Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');

        // Community translation review
        Route::get('/translations', [AdminTranslationController::class, 'index'])->name('translations.index');
        Route::post('/translations/{suggestion}/approve', [AdminTranslationController::class, 'approve'])->name('translations.approve');
        Route::post('/translations/{suggestion}/reject', [AdminTranslationController::class, 'reject'])->name('translations.reject');

        // App Settings
        Route::get('/settings', [AdminSettingsController::class, 'index'])->name('settings.index');
        Route::post('/settings', [AdminSettingsController::class, 'update'])->name('settings.update');
        Route::post('/settings/test-mail', [AdminSettingsController::class, 'testMail'])
            ->middleware('throttle:6,1')->name('settings.test-mail');
        Route::get('/seo', [AdminSettingsController::class, 'seoGenerator'])->name('seo.index');
        Route::post('/seo/generate', [AdminSettingsController::class, 'generateSeo'])
            ->middleware('throttle:ai-drafts')->name('seo.generate');

        // Service management
        Route::get('/services', [AdminServiceController::class, 'index'])->name('services.index');
        Route::post('/services', [AdminServiceController::class, 'store'])->name('services.store');
        // Static path registered before the {service} wildcard so it can't be
        // mistaken for a route-model-bound service id.
        Route::delete('/services/inactive', [AdminServiceController::class, 'bulkDeleteInactive'])->name('services.bulk-delete-inactive');
        Route::post('/services/merge-categories', [AdminServiceController::class, 'mergeCategories'])->name('services.merge-categories');
        Route::post('/services/enhance-names', [AdminServiceController::class, 'enhanceNames'])->middleware('throttle:ai-drafts')->name('services.enhance-names');
        Route::get('/services/export-list', [AdminServiceController::class, 'exportList'])->name('services.export-list');
        Route::post('/services/export-list/ai', [AdminServiceController::class, 'exportListAi'])->middleware('throttle:ai-drafts')->name('services.export-list-ai');
        Route::put('/services/{service}', [AdminServiceController::class, 'update'])->name('services.update');
        Route::delete('/services/{service}', [AdminServiceController::class, 'destroy'])->name('services.destroy');

        // Sponsored advert bookings (human-fulfilled campaigns)
        Route::get('/adverts', [\App\Http\Controllers\AdminAdvertController::class, 'index'])->name('adverts.index');
        Route::post('/adverts/{advert}/status', [\App\Http\Controllers\AdminAdvertController::class, 'updateStatus'])->name('adverts.status');
        Route::post('/adverts/{advert}/refund', [\App\Http\Controllers\AdminAdvertController::class, 'refund'])->name('adverts.refund');
        Route::post('/adverts/{advert}/message', [\App\Http\Controllers\AdminAdvertController::class, 'message'])->name('adverts.message');

        // Order management
        Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders.index');
        Route::post('/orders', [AdminOrderController::class, 'store'])->name('orders.store');
        Route::get('/orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
        Route::post('/orders/{order}/status', [AdminOrderController::class, 'updateStatus'])->name('orders.status');
        Route::post('/orders/{order}/sync', [AdminOrderController::class, 'forceSync'])->middleware('throttle:20,1')->name('orders.sync');
        Route::post('/orders/{order}/refund', [AdminOrderController::class, 'refund'])->name('orders.refund');

        // Transaction management
        Route::get('/transactions', [AdminTransactionController::class, 'index'])->name('transactions.index');
        Route::post('/transactions/{transaction}/approve', [AdminTransactionController::class, 'approveDeposit'])->name('transactions.approve');
        Route::post('/transactions/{transaction}/reject', [AdminTransactionController::class, 'rejectDeposit'])->name('transactions.reject');
        Route::post('/transactions/{transaction}/process-withdrawal', [AdminTransactionController::class, 'processWithdrawal'])->name('transactions.process-withdrawal');
        Route::post('/transactions/{transaction}/reject-withdrawal', [AdminTransactionController::class, 'rejectWithdrawal'])->name('transactions.reject-withdrawal');

        // Revenue analytics
        Route::get('/revenue', [AdminTransactionController::class, 'revenue'])->name('revenue');

        // Ticket management
        Route::get('/tickets', [AdminTicketController::class, 'index'])->name('tickets.index');
        Route::get('/tickets/{ticket}', [AdminTicketController::class, 'show'])->name('tickets.show');
        Route::post('/tickets/{ticket}/reply', [AdminTicketController::class, 'reply'])->name('tickets.reply');
        Route::post('/tickets/{ticket}/close', [AdminTicketController::class, 'close'])->name('tickets.close');
        Route::post('/tickets/{ticket}/draft-reply', [AdminTicketController::class, 'draftReply'])
            ->middleware('throttle:ai-drafts')->name('tickets.draft-reply');

        // WhatsApp Management
        Route::get('/whatsapp/templates', [WhatsAppTemplateController::class, 'index'])->name('whatsapp.templates');
        Route::post('/whatsapp/templates', [WhatsAppTemplateController::class, 'store'])->name('whatsapp.templates.store');
        Route::put('/whatsapp/templates/{template}', [WhatsAppTemplateController::class, 'update'])->name('whatsapp.templates.update');
        Route::post('/whatsapp/templates/{template}/push', [WhatsAppTemplateController::class, 'push'])->name('whatsapp.templates.push');
        Route::delete('/whatsapp/templates/{template}/local', [WhatsAppTemplateController::class, 'destroyLocal'])->name('whatsapp.templates.destroy-local');
        Route::post('/whatsapp/templates/sync', [WhatsAppTemplateController::class, 'sync'])->name('whatsapp.sync');
        Route::delete('/whatsapp/templates/{name}', [WhatsAppTemplateController::class, 'delete'])->name('whatsapp.delete');

        // WhatsApp assistant — conversations, agent takeover, knowledge base
        Route::get('/whatsapp/conversations', [AdminWhatsAppController::class, 'conversations'])->name('whatsapp.conversations');
        Route::get('/whatsapp/conversations/{account}', [AdminWhatsAppController::class, 'conversation'])->name('whatsapp.conversation');
        Route::post('/whatsapp/conversations/{account}/reply', [AdminWhatsAppController::class, 'reply'])->name('whatsapp.conversation.reply');
        Route::post('/whatsapp/conversations/{account}/handoff', [AdminWhatsAppController::class, 'toggleHandoff'])->name('whatsapp.conversation.handoff');
        Route::post('/whatsapp/conversations/{account}/reset', [AdminWhatsAppController::class, 'resetSession'])->name('whatsapp.conversation.reset');
        Route::get('/whatsapp/knowledge-base', [AdminWhatsAppController::class, 'knowledgeBase'])->name('whatsapp.kb');
        Route::post('/whatsapp/knowledge-base', [AdminWhatsAppController::class, 'storeKb'])->name('whatsapp.kb.store');
        Route::put('/whatsapp/knowledge-base/{kb}', [AdminWhatsAppController::class, 'updateKb'])->name('whatsapp.kb.update');
        Route::delete('/whatsapp/knowledge-base/{kb}', [AdminWhatsAppController::class, 'destroyKb'])->name('whatsapp.kb.destroy');

        // Marketing Campaigns (email/whatsapp/in-app)
        Route::get('/campaigns', [AdminCampaignController::class, 'index'])->name('campaigns.index');
        Route::post('/campaigns', [AdminCampaignController::class, 'store'])->name('campaigns.store');
        Route::post('/campaigns/generate-copy', [AdminCampaignController::class, 'generateCopy'])
            ->middleware('throttle:ai-drafts')->name('campaigns.generate-copy');

        // Marketer management
        Route::get('/marketers', [AdminMarketerController::class, 'index'])->name('marketers.index');
        Route::get('/partner-view/{id}', [AdminMarketerController::class, 'show'])->name('marketers.show');
        Route::post('/marketers/{user}/approve', [AdminMarketerController::class, 'approve'])->name('marketers.approve');
        Route::post('/marketers/{user}/reject', [AdminMarketerController::class, 'reject'])->name('marketers.reject');
        Route::post('/marketers/{user}/suspend', [AdminMarketerController::class, 'suspend'])->name('marketers.suspend');
        Route::post('/marketers/{user}/demote', [AdminMarketerController::class, 'demote'])->name('marketers.demote');
        Route::delete('/marketers/{user}', [AdminMarketerController::class, 'terminate'])->name('marketers.terminate');
        Route::post('/marketers/{user}/resend-email', [AdminMarketerController::class, 'resendEmailVerification'])->name('marketers.resend-email');
        Route::post('/marketers/{user}/verify-email', [AdminMarketerController::class, 'manualVerifyEmail'])->name('marketers.verify-email');
        Route::post('/marketers/{user}/resend-phone', [AdminMarketerController::class, 'resendPhoneVerification'])->name('marketers.resend-phone');
        Route::post('/moderation/portfolios/{portfolio}', [AdminMarketerController::class, 'moderatePortfolio'])
            ->middleware('throttle:ai-drafts')->name('moderation.portfolio');

        // Contract management
        Route::get('/contracts', [AdminContractController::class, 'index'])->name('contracts.index');
        Route::get('/contracts/{contract}', [AdminContractController::class, 'show'])->name('contracts.show');
        Route::delete('/contracts/{contract}', [AdminContractController::class, 'destroy'])->name('contracts.destroy');
        Route::post('/moderation/proofs/{proof}', [AdminContractController::class, 'moderateProof'])
            ->middleware('throttle:ai-drafts')->name('moderation.proof');
        Route::post('/moderation/reviews/{review}', [AdminContractController::class, 'moderateReview'])
            ->middleware('throttle:ai-drafts')->name('moderation.review');

        // Upstream Providers
        Route::get('/upstream-providers', [AdminUpstreamProviderController::class, 'index'])->name('upstream-providers.index');
        Route::post('/upstream-providers', [AdminUpstreamProviderController::class, 'store'])->name('upstream-providers.store');
        Route::put('/upstream-providers/{upstreamProvider}', [AdminUpstreamProviderController::class, 'update'])->name('upstream-providers.update');
        Route::delete('/upstream-providers/{upstreamProvider}', [AdminUpstreamProviderController::class, 'destroy'])->name('upstream-providers.destroy');
        Route::post('/upstream-providers/{upstreamProvider}/sync-balance', [AdminUpstreamProviderController::class, 'syncBalance'])->name('upstream-providers.sync-balance');
        Route::get('/upstream-providers/{upstreamProvider}/available-services', [AdminUpstreamProviderController::class, 'availableServices'])->name('upstream-providers.available-services');
        Route::post('/upstream-providers/{upstreamProvider}/import-services', [AdminUpstreamProviderController::class, 'importServices'])->name('upstream-providers.import-services');

        // Leaderboard management
        Route::get('/leaderboard/prizes', [AdminLeaderboardController::class, 'prizes'])->name('leaderboard.prizes');
        Route::post('/leaderboard/prizes', [AdminLeaderboardController::class, 'storePrize'])->name('leaderboard.prizes.store');
        Route::put('/leaderboard/prizes/{prize}', [AdminLeaderboardController::class, 'updatePrize'])->name('leaderboard.prizes.update');
        Route::delete('/leaderboard/prizes/{prize}', [AdminLeaderboardController::class, 'destroyPrize'])->name('leaderboard.prizes.destroy');
        Route::get('/leaderboard/results/{year}/{month}', [AdminLeaderboardController::class, 'results'])->name('leaderboard.results');
        Route::post('/leaderboard/snapshots/{snapshot}/award', [AdminLeaderboardController::class, 'awardPrize'])->name('leaderboard.award');
    });

    // ─── Marketer panel ───────────────────────────────────────────────────────
    Route::middleware('marketer')->prefix('marketer')->name('marketer.')->group(function () {
        Route::get('/dashboard', [MarketerController::class, 'index'])->name('dashboard');
        Route::get('/content-calendar', [MarketerController::class, 'contentCalendar'])->name('content-calendar');
        Route::post('/content-calendar/generate', [MarketerController::class, 'generateCalendar'])
            ->middleware('throttle:ai-drafts')->name('content-calendar.generate');

        Route::get('/portfolio-caption', [MarketerController::class, 'portfolioCaption'])->name('portfolio-caption');
        Route::post('/portfolio-caption/generate', [PortfolioController::class, 'generateCaption'])
            ->middleware('throttle:ai-drafts')->name('portfolio-caption.generate');
    });

    Route::middleware('marketer')->prefix('monetizer')->name('monetizer.')->group(function () {
        Route::get('/', [MonetizerController::class, 'index'])->name('index');
        Route::post('/unlock', [MonetizerController::class, 'unlock'])->name('unlock');
        Route::post('/profiles', [MonetizerController::class, 'updateProfiles'])->name('profiles.update');
        Route::post('/manual-stats', [MonetizerController::class, 'updateManualStats'])->name('manual-stats.update');
    });

    // Orders
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/new', [OrderController::class, 'create'])->name('orders.create');
    Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{order}/sync', [OrderController::class, 'syncStatus'])->middleware('throttle:12,1')->name('orders.sync-status');
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');

    // Services (read-only list)
    Route::get('/services', [ServiceController::class, 'index'])->name('services.index');

    // Wallet
    Route::get('/wallet', [WalletController::class, 'index'])->name('wallet.index');
    Route::post('/wallet/add-funds', [WalletController::class, 'manualDeposit'])->middleware('throttle:wallet-manual-deposit')->name('wallet.add');
    Route::post('/wallet/submit-proof', [WalletController::class, 'submitProof'])->middleware('throttle:wallet-proof-submit')->name('wallet.submit-proof');
    Route::post('/wallet/withdraw', [WalletController::class, 'withdraw'])->middleware('throttle:wallet-withdraw')->name('wallet.withdraw');

    // Referrals
    Route::get('/referrals', [ReferralController::class, 'index'])->name('referrals.index');
    Route::post('/referrals/share-message', [ReferralController::class, 'shareMessage'])
        ->middleware('throttle:referral-share-draft')->name('referrals.share-message');

    // Leaderboard
    Route::get('/leaderboard', [LeaderboardController::class, 'index'])->name('leaderboard.index');

    // Paynow — web redirect (credit card / Paynow balance)
    Route::post('/paynow/init', [PaynowController::class, 'init'])->middleware('throttle:paynow-init')->name('paynow.init');
    // Paynow — O'mari OTP submission (must be before the {provider} wildcard)
    Route::post('/paynow/omari/otp/{transaction}', [PaynowController::class, 'submitOmariOtp'])
        ->middleware('throttle:5,1')
        ->name('paynow.omari.otp');
    // Paynow — express checkout, one route per mobile-money provider
    Route::post('/paynow/mobile/{provider}', [PaynowController::class, 'initMobile'])
        ->middleware('throttle:paynow-init')
        ->name('paynow.mobile')
        ->where('provider', 'ecocash|onemoney|telecash|innbucks|omari');
    Route::get('/paynow/return', [PaynowController::class, 'returnUrl'])->name('paynow.return');
    Route::get('/paynow/poll/{transaction}', [PaynowController::class, 'pollStatus'])->middleware('throttle:30,1')->name('paynow.poll');

    // Tickets
    Route::get('/tickets', [TicketController::class, 'index'])->name('tickets.index');
    Route::post('/tickets', [TicketController::class, 'store'])->name('tickets.store');
    Route::get('/tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
    Route::post('/tickets/{ticket}/reply', [TicketController::class, 'reply'])->name('tickets.reply');

    // Settings
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::post('/settings/api-key', [SettingsController::class, 'regenerateApiKey'])->name('settings.api-key');

    // Developer API Docs
    Route::get('/developer/api', function () {
        return inertia('Developer/ApiDocs');
    })->name('developer.api');

    // Contracts
    Route::get('/contracts', [ContractController::class, 'index'])->name('contracts.index');
    Route::get('/contracts/{contract}', [ContractController::class, 'show'])->name('contracts.show');
    Route::post('/contracts', [ContractController::class, 'store'])->name('contracts.store');
    Route::put('/contracts/{contract}', [ContractController::class, 'update'])->name('contracts.update');
    Route::delete('/contracts/{contract}', [ContractController::class, 'closeContract'])->name('contracts.destroy');
    Route::post('/contracts/{contract}/apply', [ContractController::class, 'apply'])->name('contracts.apply');
    Route::post('/contracts/{contract}/applications/{application}/decision', [ContractController::class, 'decide'])->name('contracts.applications.decision');
    Route::post('/contracts/{contract}/applications/{application}/revoke', [ContractController::class, 'revoke'])->name('contracts.applications.revoke');

    // Marketer social links
    Route::post('/social-links', [MarketerSocialLinkController::class, 'store'])->name('social-links.store');
    Route::delete('/social-links/{socialLink}', [MarketerSocialLinkController::class, 'destroy'])->name('social-links.destroy');

    // Contract proof submissions
    Route::post('/contract-applications/{application}/proof', [ContractProofController::class, 'store'])->name('proof.store');
    Route::post('/contract-proof/{proof}/review', [ContractProofController::class, 'review'])->name('proof.review');
    Route::post('/contracts/{contract}/applications/{application}/review', [ContractReviewController::class, 'store'])->name('contracts.review.store');

    // Portfolio management
    Route::post('/portfolio', [PortfolioController::class, 'store'])->name('portfolio.store');
    Route::delete('/portfolio/{portfolio}', [PortfolioController::class, 'destroy'])->name('portfolio.destroy');

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');

    // Impersonation
    Route::post('/impersonate/leave', [AdminUserController::class, 'leaveImpersonation'])->name('admin.users.impersonate.leave');

    // Logout
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});

// ─── Payment webhooks (no CSRF) ───────────────────────────────────────────────

Route::post('/webhooks/payment', [WalletController::class, 'handleWebhook'])
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('webhooks.payment');

// ─── WhatsApp assistant webhook (Meta Cloud API, no CSRF) ─────────────────────

Route::get('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify'])
    ->name('webhooks.whatsapp.verify');
Route::post('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'receive'])
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('webhooks.whatsapp');
