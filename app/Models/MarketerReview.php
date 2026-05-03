<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketerReview extends Model
{
    protected $fillable = [
        'business_contract_id',
        'contract_application_id',
        'reviewer_id',
        'marketer_id',
        'rating',
        'comment',
    ];

    protected function casts(): array
    {
        return ['rating' => 'integer'];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(BusinessContract::class, 'business_contract_id');
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(ContractApplication::class, 'contract_application_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function marketer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marketer_id');
    }
}
