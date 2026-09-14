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
 * @property-read Order $order
 * @property-read Product $product
 * @property-read Collection<int, WeightEvent> $weightEvents
 */
class OrderItem extends Model
{
    /** Line data is built by PlaceOrder from the product; nothing here comes from the client. @var list<string> */
    protected $fillable = [
        'product_id',
        'quantity',
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

    /** @return HasMany<WeightEvent, $this> */
    public function weightEvents(): HasMany
    {
        return $this->hasMany(WeightEvent::class)->orderBy('id');
    }
}
