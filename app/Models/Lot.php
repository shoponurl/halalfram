<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\WeightCast;
use App\Enums\LotStatus;
use App\Enums\StorageLocation;
use App\Support\Weight;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One receiving of stock for a product: a lot number, a storage location, a use-by date for FEFO,
 * and a cached on-hand/reserved balance kept in sync with the append-only stock_movements ledger.
 *
 * @property int $id
 * @property string|null $lot_number
 * @property int $product_id
 * @property int|null $animal_id
 * @property StorageLocation $storage_location
 * @property Carbon $pack_date
 * @property Carbon $use_by_date
 * @property LotStatus $status
 * @property Weight $on_hand_weight_lb
 * @property Weight $reserved_weight_lb
 * @property int $received_by
 * @property-read Product $product
 * @property-read Animal|null $animal
 * @property-read Collection<int, StockMovement> $movements
 */
class Lot extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'product_id',
        'animal_id',
        'storage_location',
        'pack_date',
        'use_by_date',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'storage_location' => StorageLocation::class,
            'pack_date' => 'date',
            'use_by_date' => 'date',
            'status' => LotStatus::class,
            'on_hand_weight_lb' => WeightCast::class,
            'reserved_weight_lb' => WeightCast::class,
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'lot_number';
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Animal, $this> */
    public function animal(): BelongsTo
    {
        return $this->belongsTo(Animal::class);
    }

    /** @return BelongsTo<User, $this> */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /** @return HasMany<StockMovement, $this> */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class)->orderBy('id');
    }

    /**
     * @param  Builder<Lot>  $query
     * @return Builder<Lot>
     */
    public function scopeAvailableFor(Builder $query, int $productId): Builder
    {
        return $query->where('product_id', $productId)->where('status', LotStatus::Active)->orderBy('use_by_date');
    }

    public function availableWeight(): Weight
    {
        return $this->on_hand_weight_lb->minus($this->reserved_weight_lb);
    }
}
