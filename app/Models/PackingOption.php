<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A packing choice available across the whole catalog (vacuum pack, portion size, ...).
 *
 * @property int $id
 * @property string $name
 * @property int $surcharge_cents
 * @property int $extra_lead_time_days
 * @property bool $is_active
 * @property int $sort_order
 */
class PackingOption extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'name',
        'surcharge_cents',
        'extra_lead_time_days',
        'is_active',
        'sort_order',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'surcharge_cents' => 'integer',
            'extra_lead_time_days' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @param  Builder<PackingOption>  $query
     * @return Builder<PackingOption>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
