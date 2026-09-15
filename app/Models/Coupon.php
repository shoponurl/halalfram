<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Owner decision (guideline ch. 7, S06): a coupon discounts the final amount the customer actually
 * pays, not the pre-weighing estimate — checkout only validates the code; FinalizeOrder computes and
 * locks in the actual discount, against the real weighed total.
 *
 * @property int $id
 * @property string $code
 * @property string $type
 * @property int $value
 * @property int|null $min_order_cents
 * @property int|null $max_redemptions
 * @property int $redeemed_count
 * @property Carbon|null $starts_at
 * @property Carbon|null $expires_at
 * @property bool $active
 */
class Coupon extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'code',
        'type',
        'value',
        'min_order_cents',
        'max_redemptions',
        'starts_at',
        'expires_at',
        'active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'value' => 'integer',
            'min_order_cents' => 'integer',
            'max_redemptions' => 'integer',
            'redeemed_count' => 'integer',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'active' => 'boolean',
        ];
    }

    /** Checkout-time validation, against the pre-weighing subtotal — re-checked for real at FinalizeOrder. */
    public function assertUsableFor(int $subtotalCents): void
    {
        if (! $this->active) {
            throw ValidationException::withMessages(['coupon_code' => 'This coupon is no longer active.']);
        }
        if ($this->starts_at !== null && $this->starts_at->isFuture()) {
            throw ValidationException::withMessages(['coupon_code' => 'This coupon isn\'t active yet.']);
        }
        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            throw ValidationException::withMessages(['coupon_code' => 'This coupon has expired.']);
        }
        if ($this->max_redemptions !== null && $this->redeemed_count >= $this->max_redemptions) {
            throw ValidationException::withMessages(['coupon_code' => 'This coupon has already been fully redeemed.']);
        }
        if ($this->min_order_cents !== null && $subtotalCents < $this->min_order_cents) {
            throw ValidationException::withMessages(['coupon_code' => 'This order is below the minimum for this coupon.']);
        }
    }

    /** The discount for a given final amount, never more than the amount itself. */
    public function discountFor(int $amountCents): int
    {
        $discount = $this->type === 'percent' ? (int) round($amountCents * $this->value / 100) : $this->value;

        return min($discount, $amountCents);
    }
}
