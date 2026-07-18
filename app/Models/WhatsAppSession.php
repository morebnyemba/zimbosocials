<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Persisted conversation state for one phone number. The in-memory working copy
 * used during a request is App\WhatsApp\Session\SessionContext, which hydrates
 * from / saves to this row.
 */
class WhatsAppSession extends Model
{
    protected $table = 'whatsapp_sessions';

    public $timestamps = false;

    protected $fillable = [
        'wa_phone', 'current_flow', 'current_state', 'state_stack',
        'context', 'status', 'started_at', 'last_activity', 'expires_at', 'nudged_at',
    ];

    protected function casts(): array
    {
        return [
            'state_stack' => 'array',
            'context' => 'array',
            'started_at' => 'datetime',
            'last_activity' => 'datetime',
            'expires_at' => 'datetime',
            'nudged_at' => 'datetime',
        ];
    }
}
