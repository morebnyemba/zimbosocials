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

    protected function casts(): array
    {
        return [
            'amount'         => 'decimal:4',
            'balance_before' => 'decimal:4',
            'balance_after'  => 'decimal:4',
            'gateway_meta'   => 'array',
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

    public function getTypeLabelSn(): string
    {
        return match ($this->type) {
            'deposit'      => 'Dhipoziti',
            'order_charge' => 'Odha',
            'contract_payout' => 'Kontrakiti Kubhadhara',
            'contract_earning' => 'Mari yeKontrakiti',
            'refund'       => 'Dzosera',
            'adjustment'   => 'Kugadzirisa',
            'bonus'        => 'Bhonerasi',
            default        => $this->type,
        };
    }
}
