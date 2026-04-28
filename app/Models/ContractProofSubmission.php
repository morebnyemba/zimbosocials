<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractProofSubmission extends Model
{
    protected $fillable = [
        'contract_application_id',
        'marketer_id',
        'proof_url',
        'notes',
        'status',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    public function contractApplication(): BelongsTo
    {
        return $this->belongsTo(ContractApplication::class);
    }

    public function marketer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marketer_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
