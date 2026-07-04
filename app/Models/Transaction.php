<?php

// app/Models/Transaction.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'order_id', 'type', 'amount',
        'balance_before', 'balance_after',
        'method', 'reference', 'status', 'notes', 'gateway_meta',
        'proof_url', 'processed_by', 'processed_at', 'admin_notes',
    ];

    /** Always include the computed display reference code in serialized output. */
    protected $appends = ['reference_code'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'balance_before' => 'decimal:4',
            'balance_after' => 'decimal:4',
            'gateway_meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isCredit(): bool
    {
        return $this->amount > 0;
    }

    /**
     * Human-readable, brand-prefixed reference code for display to users and
     * admins (support/reconciliation). Deliberately computed from existing
     * columns rather than stored — `reference` itself is already overloaded
     * per transaction type (the Paynow poll-tracking URL for gateway
     * deposits, a user-entered destination reference for manual deposits/
     * withdrawals, an internal REF-* code for referral bonuses), so adding
     * a distinct display code here needed no migration, no backfill, and
     * works retroactively for every transaction that already exists.
     *
     * Format: ZSD-YYYYMMDD-{user_id}-{transaction_id}
     */
    public function getReferenceCodeAttribute(): string
    {
        $date = $this->created_at?->format('Ymd') ?? now()->format('Ymd');

        return "ZSD-{$date}-{$this->user_id}-{$this->id}";
    }

    public function getTypeLabelSn(): string
    {
        return match ($this->type) {
            'deposit' => 'Dhipoziti',
            'order_charge' => 'Odha',
            'contract_payout' => 'Kontrakiti Kubhadhara',
            'contract_earning' => 'Mari yeKontrakiti',
            'refund' => 'Dzosera',
            'adjustment' => 'Kugadzirisa',
            'bonus' => 'Bhonerasi',
            default => $this->type,
        };
    }
}
