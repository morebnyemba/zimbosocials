<?php

// app/Models/User.php

namespace App\Models;

use App\Notifications\LocalizedResetPasswordNotification;
use App\Notifications\LocalizedVerifyEmailNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'balance',
        'api_key',
        'referral_code',
        'referred_by',
        'referred_bonus_awarded_at',
        'role',
        'marketer_status',
        'locale',
        'currency',
        'is_active',
        'phone',
        'whatsapp_number',
        'notification_prefs',
        'company_name',
        'admin_notes',
        'last_login_at',
        'last_login_ip',
        'slug',
        'bio',
        'admin_role',
        'profile_image_url',
        'account_type',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'api_key',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'balance' => 'decimal:4',
            'is_active' => 'boolean',
            'notification_prefs' => 'array',
            'last_login_at' => 'datetime',
            'referred_bonus_awarded_at' => 'datetime',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function businessContracts(): HasMany
    {
        return $this->hasMany(BusinessContract::class);
    }

    public function contractApplications(): HasMany
    {
        return $this->hasMany(ContractApplication::class, 'marketer_id');
    }

    public function socialLinks(): HasMany
    {
        return $this->hasMany(MarketerSocialLink::class);
    }

    public function contractProofSubmissions(): HasMany
    {
        return $this->hasMany(ContractProofSubmission::class, 'marketer_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function portfolios(): HasMany
    {
        return $this->hasMany(MarketerPortfolio::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function receivedReviews(): HasMany
    {
        return $this->hasMany(MarketerReview::class, 'marketer_id');
    }

    public function givenReviews(): HasMany
    {
        return $this->hasMany(MarketerReview::class, 'reviewer_id');
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by');
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(User::class, 'referred_by');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function hasFullAdminAccess(): bool
    {
        return $this->isAdmin() && $this->admin_role === 'full';
    }

    public function canManageFinances(): bool
    {
        return $this->isAdmin() && in_array($this->admin_role, ['full', 'finance'], true);
    }

    public function canManageSupport(): bool
    {
        return $this->isAdmin() && in_array($this->admin_role, ['full', 'support'], true);
    }

    public function canManageCompliance(): bool
    {
        return $this->isAdmin() && in_array($this->admin_role, ['full', 'compliance'], true);
    }

    public function isMarketer(): bool
    {
        return in_array($this->role, ['marketer', 'admin'], true);
    }

    public function isBusiness(): bool
    {
        return $this->account_type === 'business';
    }

    public function isReseller(): bool
    {
        return in_array($this->role, ['reseller', 'admin'], true);
    }

    /**
     * Returns true if the user should see the marketer dashboard (marketer OR reseller).
     */
    public function hasMarketerAccess(): bool
    {
        return in_array($this->role, ['marketer', 'reseller'], true);
    }

    public function scopeByRole(Builder $query, string $role): Builder
    {
        return $query->where('role', $role);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function generateApiKey(): string
    {
        $key = 'zvk_live_'.Str::random(32);
        $this->update(['api_key' => $key]);

        return $key;
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new LocalizedResetPasswordNotification($token));
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new LocalizedVerifyEmailNotification);
    }

    public static function generateReferralCode(): string
    {
        do {
            $code = 'ZIM'.strtoupper(Str::random(8));
        } while (static::where('referral_code', $code)->exists());

        return $code;
    }

    /**
     * Deduct from balance and record a transaction.
     * Returns false if insufficient funds.
     */
    public function deductBalance(float $amount, ?Order $order = null, string $notes = ''): bool
    {
        if ($this->balance < $amount) {
            return false;
        }

        $before = $this->balance;
        $this->decrement('balance', $amount);

        Transaction::create([
            'user_id' => $this->id,
            'order_id' => $order?->id,
            'type' => 'order_charge',
            'amount' => -$amount,
            'balance_before' => $before,
            'balance_after' => $before - $amount,
            'status' => 'completed',
            'notes' => $notes,
        ]);

        return true;
    }

    /**
     * Credit balance and record a deposit transaction.
     */
    public function creditBalance(float $amount, string $method, string $reference = '', string $type = 'deposit'): Transaction
    {
        $before = $this->balance;
        $this->increment('balance', $amount);

        return Transaction::create([
            'user_id' => $this->id,
            'type' => $type,
            'amount' => $amount,
            'balance_before' => $before,
            'balance_after' => $before + $amount,
            'method' => $method,
            'reference' => $reference,
            'status' => 'completed',
        ]);
    }

    public function getFormattedBalanceAttribute(): string
    {
        return '$'.number_format($this->balance, 2);
    }
}
