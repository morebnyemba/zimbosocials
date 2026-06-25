<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ContractApplication extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_DENIED = 'denied';

    public const STATUS_IGNORED = 'ignored';

    protected $fillable = [
        'business_contract_id',
        'marketer_id',
        'pitch',
        'status',
        'decided_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(BusinessContract::class, 'business_contract_id');
    }

    public function marketer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marketer_id');
    }

    public function decisionMaker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /** Alias used by ContractController::show() eager loading. */
    public function decider(): BelongsTo
    {
        return $this->decisionMaker();
    }

    public function proofSubmissions(): HasMany
    {
        return $this->hasMany(ContractProofSubmission::class);
    }

    /** Alias used by ContractController::show() eager loading. */
    public function proofs(): HasMany
    {
        return $this->proofSubmissions();
    }

    public function review(): HasOne
    {
        return $this->hasOne(MarketerReview::class, 'contract_application_id');
    }

    /**
     * Statuses that consume a funded contract slot.
     *
     * @return array<int, string>
     */
    public static function slotConsumingStatuses(): array
    {
        return [
            self::STATUS_APPROVED,
            self::STATUS_COMPLETED,
        ];
    }
}
