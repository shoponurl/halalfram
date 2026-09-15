<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\WeightCast;
use App\Enums\PackageTemperature;
use App\Support\Weight;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A box/insulation/dry-ice configuration for a weight band and temperature (guideline ch. 7, S08:
 * packing rules live in the database, not code, so they change without a deploy).
 *
 * @property int $id
 * @property string $name
 * @property PackageTemperature $temperature
 * @property Weight $min_weight_lb
 * @property Weight $max_weight_lb
 * @property string $box_length_in
 * @property string $box_width_in
 * @property string $box_height_in
 * @property Weight $tare_weight_lb
 * @property Weight|null $dry_ice_lb
 * @property bool $is_active
 * @property int $sort_order
 */
class PackingRule extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'name',
        'temperature',
        'min_weight_lb',
        'max_weight_lb',
        'box_length_in',
        'box_width_in',
        'box_height_in',
        'tare_weight_lb',
        'dry_ice_lb',
        'is_active',
        'sort_order',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'temperature' => PackageTemperature::class,
            'min_weight_lb' => WeightCast::class,
            'max_weight_lb' => WeightCast::class,
            'box_length_in' => 'decimal:2',
            'box_width_in' => 'decimal:2',
            'box_height_in' => 'decimal:2',
            'tare_weight_lb' => WeightCast::class,
            'dry_ice_lb' => WeightCast::class,
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @param  Builder<PackingRule>  $query
     * @return Builder<PackingRule>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Total parcel weight EasyPost needs to quote a rate: the contents plus the box/ice tare. */
    public function totalWeightFor(Weight $contentsWeight): Weight
    {
        return $contentsWeight->plus($this->tare_weight_lb);
    }
}
