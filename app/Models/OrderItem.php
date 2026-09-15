<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\WeightCast;
use App\Support\Weight;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $order_id
 * @property int $product_id
 * @property string $product_name
 * @property int $quantity
 * @property int $price_per_lb_cents
 * @property Weight $estimated_weight_lb
 * @property int $estimated_cents
 * @property Weight|null $actual_weight_lb
 * @property int|null $final_cents
 * @property int|null $cut_option_id
 * @property string|null $cut_option_name
 * @property int $cut_option_price_cents
 * @property int|null $offal_option_id
 * @property string|null $offal_option_name
 * @property int $offal_option_price_cents
 * @property int|null $packing_option_id
 * @property string|null $packing_option_name
 * @property int $packing_option_price_cents
 * @property int $lead_time_days
 * @property int|null $estimated_minutes
 * @property int|null $lot_id
 * @property Weight|null $reserved_raw_weight_lb
 * @property Weight|null $consumed_raw_weight_lb
 * @property-read Order $order
 * @property-read Product $product
 * @property-read Lot|null $lot
 * @property-read Collection<int, WeightEvent> $weightEvents
 */
class OrderItem extends Model
{
    /** Line data is built by PlaceOrder from the product/options; nothing here comes from the client. @var list<string> */
    protected $fillable = [
        'product_id',
        'quantity',
        'cut_option_id',
        'cut_option_name',
        'cut_option_price_cents',
        'offal_option_id',
        'offal_option_name',
        'offal_option_price_cents',
        'packing_option_id',
        'packing_option_name',
        'packing_option_price_cents',
        'lead_time_days',
        'estimated_minutes',
        'lot_id',
        'reserved_raw_weight_lb',
        'consumed_raw_weight_lb',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'price_per_lb_cents' => 'integer',
            'estimated_weight_lb' => WeightCast::class,
            'estimated_cents' => 'integer',
            'actual_weight_lb' => WeightCast::class,
            'final_cents' => 'integer',
            'cut_option_price_cents' => 'integer',
            'offal_option_price_cents' => 'integer',
            'packing_option_price_cents' => 'integer',
            'lead_time_days' => 'integer',
            'estimated_minutes' => 'integer',
            'reserved_raw_weight_lb' => WeightCast::class,
            'consumed_raw_weight_lb' => WeightCast::class,
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Lot, $this> */
    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    /** The combined cut/offal/packing surcharge already folded into estimated_cents/final_cents. */
    public function optionSurchargeCents(): int
    {
        return $this->cut_option_price_cents + $this->offal_option_price_cents + $this->packing_option_price_cents;
    }

    /** @return HasMany<WeightEvent, $this> */
    public function weightEvents(): HasMany
    {
        return $this->hasMany(WeightEvent::class)->orderBy('id');
    }
}
