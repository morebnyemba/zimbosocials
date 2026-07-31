<?php

// app/Models/ManualPaymentDetail.php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ManualPaymentDetail extends Model
{
    protected $fillable = [
        'method_key',
        'label',
        'account_name',
        'account_number',
        'instructions',
        'ussd_template',
        'is_active',
        'sort_order',
        'gateway_type',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * The dialable shortcode for this amount, e.g. *151*1*1*0787211325*10.50#
     *
     * Returns null when no template is set — better to show nothing than a code
     * that dials the wrong thing. Whole amounts lose their decimals because
     * that is how people type them into a phone.
     */
    public function ussdFor(float $amount): ?string
    {
        $template = trim((string) $this->ussd_template);
        if ($template === '' || $amount <= 0) {
            return null;
        }

        $formatted = fmod($amount, 1.0) === 0.0
            ? (string) (int) $amount
            : rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.');

        return str_replace(
            ['{number}', '{amount}'],
            [preg_replace('/\s+/', '', (string) $this->account_number), $formatted],
            $template
        );
    }
}
