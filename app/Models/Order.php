<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Money columns are integer cents. Status, amounts and Stripe references are never mass assignable:
 * only the checkout/settlement actions set them, explicitly.
 *
 * @property int $id
 * @property string $uuid
 * @property string|null $number
 * @property string $public_token_hash
 * @property int|null $user_id
 * @property OrderStatus $status
 * @property string $customer_name
 * @property string $customer_email
 * @property string $customer_phone
 * @property string $fulfilment
 * @property string|null $notes
 * @property int $estimated_cents
 * @property int $hold_cents
 * @property int|null $final_cents
 * @property int $captured_cents
 * @property int $extra_charged_cents
 * @property int $balance_due_cents
 * @property int $balance_paid_cents
 * @property int $written_off_cents
 * @property string $currency
 * @property string $hold_tolerance_pct
 * @property string $overage_autocharge_pct
 * @property string $underweight_review_pct
 * @property string|null $stripe_customer_id
 * @property string|null $stripe_payment_intent_id
 * @property string|null $stripe_payment_method_id
 * @property string|null $balance_payment_url
 * @property Carbon|null $authorized_at
 * @property Carbon|null $authorization_expires_at
 * @property Carbon|null $weights_locked_at
 * @property int|null $underweight_approved_by
 * @property Carbon|null $underweight_approved_at
 * @property int|null $finalized_by
 * @property Carbon|null $settled_at
 * @property Carbon $created_at
 * @property-read Collection<int, OrderItem> $items
 * @property-read Collection<int, PaymentTransaction> $transactions
 * @property-read Invoice|null $invoice
 */
class Order extends Model
{
    /** Customer-supplied fields only. @var list<string> */
    protected $fillable = [
        'customer_name',
        'customer_email',
        'customer_phone',
        'notes',
    ];

    /** @var list<string> */
    protected $hidden = [
        'public_token_hash',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'estimated_cents' => 'integer',
            'hold_cents' => 'integer',
            'final_cents' => 'integer',
            'captured_cents' => 'integer',
            'extra_charged_cents' => 'integer',
            'balance_due_cents' => 'integer',
            'balance_paid_cents' => 'integer',
            'written_off_cents' => 'integer',
            'hold_tolerance_pct' => 'decimal:2',
            'overage_autocharge_pct' => 'decimal:2',
            'underweight_review_pct' => 'decimal:2',
            'authorized_at' => 'datetime',
            'authorization_expires_at' => 'datetime',
            'weights_locked_at' => 'datetime',
            'underweight_approved_at' => 'datetime',
            'settled_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'number';
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** @return HasMany<PaymentTransaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class)->orderBy('id');
    }

    /** @return HasManyThrough<WeightEvent, OrderItem, $this> */
    public function weightEvents(): HasManyThrough
    {
        return $this->hasManyThrough(WeightEvent::class, OrderItem::class);
    }

    /** @return HasOne<Invoice, $this> */
    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    /** @return BelongsTo<User, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Constant-time check of the private link token a guest received after checkout. */
    public function matchesPublicToken(?string $token): bool
    {
        return $token !== null && $token !== '' && hash_equals($this->public_token_hash, hash('sha256', $token));
    }

    /** Idempotency key for a Stripe call about this order (rule 03). */
    public function idempotencyKey(string $operation): string
    {
        return "order:{$this->uuid}:{$operation}";
    }

    public function allItemsWeighed(): bool
    {
        return $this->items->isNotEmpty() && $this->items->every(fn (OrderItem $item) => $item->actual_weight_lb !== null);
    }

    public function totalChargedCents(): int
    {
        return $this->captured_cents + $this->extra_charged_cents + $this->balance_paid_cents;
    }
}
