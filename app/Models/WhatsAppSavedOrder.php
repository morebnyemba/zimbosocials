<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A durable "finish this order after you top up" stash for a WhatsApp customer
 * who hit the confirm step without enough balance. One per user (the deposit
 * resume + the top-up reminder both key off it).
 */
class WhatsAppSavedOrder extends Model
{
    protected $table = 'whatsapp_saved_orders';

    protected $fillable = [
        'user_id', 'wa_phone', 'service_id', 'link', 'quantity', 'reminded_at',
    ];

    protected $casts = [
        'reminded_at' => 'datetime',
        'quantity' => 'integer',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
