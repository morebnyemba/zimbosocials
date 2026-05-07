<?php
// app/Models/Order.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Builder;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'service_id', 'link', 'quantity',
        'charge', 'rate_at_order', 'status',
        'start_count', 'remains', 'external_order_id',
        'push_attempts', 'pushed_to_upstream', 'pushed_at', 'upstream_last_error',
        'notes', 'started_at', 'completed_at', 'upstream_provider_id',
    ];

    protected function casts(): array
    {
        return [
            'charge'        => 'decimal:4',
            'rate_at_order' => 'decimal:4',
            'pushed_to_upstream' => 'boolean',
            'pushed_at'     => 'datetime',
            'started_at'    => 'datetime',
            'completed_at'  => 'datetime',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function transaction(): HasOne
    {
        return $this->hasOne(Transaction::class, 'order_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(UpstreamProvider::class, 'upstream_provider_id');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', ['pending', 'processing', 'in_progress']);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    public function isActive(): bool
    {
        return in_array($this->status, ['pending', 'processing', 'in_progress']);
    }

    public function canCancel(): bool
    {
        // Users can only cancel orders that are still pending
        // AND have not yet been sent to the external SMM provider.
        return $this->status === 'pending' && ! $this->pushed_to_upstream;
    }

    /** Shona status label */
    public function getStatusLabelSn(): string
    {
        return match ($this->status) {
            'pending'     => 'Yakamirira',
            'processing'  => 'Inobatwa',
            'in_progress' => 'Iri Kufamba',
            'completed'   => 'Yakwana',
            'partial'     => 'Yakasikirwa',
            'cancelled'   => 'Yakanzurwa',
            'refunded'    => 'Yadzoswa',
            default       => $this->status,
        };
    }

    public function getStatusLabelEn(): string
    {
        return match ($this->status) {
            'pending'     => 'Pending',
            'processing'  => 'Processing',
            'in_progress' => 'In Progress',
            'completed'   => 'Completed',
            'partial'     => 'Partial',
            'cancelled'   => 'Cancelled',
            'refunded'    => 'Refunded',
            default       => ucfirst($this->status),
        };
    }
}
