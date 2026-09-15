<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Owner decision (guideline ch. 7, S05): the service area is an explicit zip list, grouped into
 * zones, each with its own flat delivery fee.
 *
 * @property int $id
 * @property string $name
 * @property int $flat_fee_cents
 * @property bool $is_active
 * @property-read Collection<int, ServiceZip> $zips
 */
class DeliveryZone extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'name',
        'flat_fee_cents',
        'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'flat_fee_cents' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<ServiceZip, $this> */
    public function zips(): HasMany
    {
        return $this->hasMany(ServiceZip::class);
    }

    /**
     * @param  Builder<DeliveryZone>  $query
     * @return Builder<DeliveryZone>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
