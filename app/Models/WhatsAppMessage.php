<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Unified inbound/outbound transcript with routing metadata (how each inbound
 * message was handled, detected intent, whether AI was used).
 */
class WhatsAppMessage extends Model
{
    protected $table = 'whatsapp_messages';

    const UPDATED_AT = null; // insert-only transcript

    protected $fillable = [
        'wa_phone', 'direction', 'wa_message_id', 'msg_type', 'body',
        'payload', 'flow', 'handled_by', 'intent', 'confidence',
        'ai_used', 'delivery_status',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'confidence' => 'float',
            'ai_used' => 'boolean',
        ];
    }
}
