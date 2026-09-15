<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Append-only (rule 04): one row per order, so a coupon's redeemed_count can never drift from what
 * actually happened.
 *
 * @property int $id
 * @property int $coupon_id
 * @property int $order_id
 * @property int $amount_cents
 * @property Carbon $created_at
 * @property-read Coupon $coupon
 * @property-read Order $order
 */
class CouponRedemption extends Model
{
    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = ['coupon_id', 'order_id', 'amount_cents'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['amount_cents' => 'integer', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Coupon redemptions are append-only.'));
        static::deleting(fn () => throw new LogicException('Coupon redemptions are append-only.'));
    }

    /** @return BelongsTo<Coupon, $this> */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
