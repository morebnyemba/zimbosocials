<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Binds a WhatsApp phone number to an app user (or holds a guest until linked).
 */
class WhatsAppAccount extends Model
{
    protected $table = 'whatsapp_accounts';

    protected $fillable = [
        'wa_phone', 'user_id', 'link_status', 'display_name',
        'link_otp', 'link_otp_expires', 'link_attempts', 'opted_in',
        'agent_handoff_until', 'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'link_otp_expires' => 'datetime',
            'agent_handoff_until' => 'datetime',
            'last_seen_at' => 'datetime',
            'opted_in' => 'boolean',
            'link_attempts' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isLinked(): bool
    {
        return $this->link_status === 'linked' && $this->user_id !== null;
    }

    public function inAgentHandoff(): bool
    {
        return $this->agent_handoff_until !== null && $this->agent_handoff_until->isFuture();
    }
}
