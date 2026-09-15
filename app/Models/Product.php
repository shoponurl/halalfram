<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\WeightCast;
use App\Enums\PortionType;
use App\Support\CatchWeightPricing;
use App\Support\Weight;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * A catch-weight product: sold per piece, priced per lb, charged on actual weight.
 *
 * @property int $id
 * @property int|null $category_id
 * @property string $slug
 * @property string $name
 * @property string|null $description
 * @property string|null $image_path
 * @property string $uom
 * @property PortionType|null $portion_type
 * @property string|null $yield_pct
 * @property int $price_per_lb_cents
 * @property Weight $estimated_weight_lb
 * @property string|null $tolerance_pct
 * @property bool $requires_chilled_shipping
 * @property bool $is_active
 * @property-read Category|null $category
 */
class Product extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'category_id',
        'slug',
        'name',
        'description',
        'image_path',
        'portion_type',
        'yield_pct',
        'price_per_lb_cents',
        'estimated_weight_lb',
        'tolerance_pct',
        'requires_chilled_shipping',
        'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'portion_type' => PortionType::class,
            'yield_pct' => 'decimal:2',
            'price_per_lb_cents' => 'integer',
            'estimated_weight_lb' => WeightCast::class,
            'tolerance_pct' => 'decimal:2',
            'requires_chilled_shipping' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return HasMany<Lot, $this> */
    public function lots(): HasMany
    {
        return $this->hasMany(Lot::class);
    }

    /** Custom cut/offal/packing options only apply to products in a category that offers them. */
    public function supportsCustomCuts(): bool
    {
        return $this->category->supports_custom_cuts ?? false;
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
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
