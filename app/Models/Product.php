<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\WeightCast;
use App\Support\CatchWeightPricing;
use App\Support\Weight;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A catch-weight product: sold per piece, priced per lb, charged on actual weight.
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string|null $description
 * @property string|null $image_path
 * @property string $uom
 * @property int $price_per_lb_cents
 * @property Weight $estimated_weight_lb
 * @property string|null $tolerance_pct
 * @property bool $is_active
 */
class Product extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'slug',
        'name',
        'description',
        'image_path',
        'price_per_lb_cents',
        'estimated_weight_lb',
        'tolerance_pct',
        'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'price_per_lb_cents' => 'integer',
            'estimated_weight_lb' => WeightCast::class,
            'tolerance_pct' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function estimatedPieceCents(): int
    {
        return CatchWeightPricing::lineCents($this->price_per_lb_cents, $this->estimated_weight_lb);
    }

    /** The hold tolerance for this product: its own value, or the store-wide policy. */
    public function holdTolerancePct(): string
    {
        return $this->tolerance_pct ?? number_format((float) config('catchweight.hold_tolerance_pct'), 2, '.', '');
    }
}
