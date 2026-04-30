<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingCampaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'created_by',
        'name',
        'subjects',
        'bodies',
        'channels',
        'filters',
        'status',
        'recipients_total',
        'sent_email',
        'sent_whatsapp',
        'sent_in_app',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'subjects' => 'array',
            'bodies' => 'array',
            'channels' => 'array',
            'filters' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
