<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A knowledge-base FAQ entry consulted by the assistant before AI. */
class WhatsAppKnowledge extends Model
{
    protected $table = 'whatsapp_knowledge_base';

    protected $fillable = [
        'title', 'question', 'answer', 'keywords', 'category', 'locale', 'status', 'hits',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
            'hits' => 'integer',
        ];
    }
}
