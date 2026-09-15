<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FulfilmentStatus;
use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

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
 * @property bool $marketing_sms_opt_in
 * @property bool $marketing_email_opt_in
 * @property Carbon|null $regulatory_consent_at
 * @property string|null $regulatory_consent_version
 * @property string $payment_method
 * @property string|null $payment_reference
 * @property int $tax_cents
 * @property int|null $coupon_id
 * @property int $discount_cents
 * @property int $store_credit_applied_cents
 * @property string $fulfilment
 * @property FulfilmentStatus $fulfilment_status
 * @property int|null $delivery_zone_id
 * @property int|null $delivery_slot_id
 * @property string|null $delivery_address_line1
 * @property string|null $delivery_address_line2
 * @property string|null $delivery_city
 * @property string|null $delivery_state
 * @property string|null $delivery_zip
 * @property int $delivery_fee_cents
 * @property int $delivery_attempts
 * @property string|null $delivery_otp
 * @property Carbon|null $ready_notified_at
 * @property Carbon|null $pickup_reminder_sent_at
 * @property int $refunded_cents
 * @property int|null $driver_id
 * @property string|null $notes
 * @property int $lead_time_days
 * @property Carbon|null $scheduled_date
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
 * @property-read Collection<int, QcCheck> $qcChecks
 * @property-read Collection<int, DeliveryEvent> $deliveryEvents
 * @property-read DeliveryZone|null $deliveryZone
 * @property-read DeliverySlot|null $deliverySlot
 * @property-read User|null $driver
 * @property-read Invoice|null $invoice
 * @property-read Coupon|null $coupon
 */
class Order extends Model
{
    /** Customer-supplied fields only. @var list<string> */
    protected $fillable = [
        'customer_name',
        'customer_email',
        'customer_phone',
        'notes',
        'marketing_sms_opt_in',
        'marketing_email_opt_in',
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
            'fulfilment_status' => FulfilmentStatus::class,
            'marketing_sms_opt_in' => 'boolean',
            'marketing_email_opt_in' => 'boolean',
            'regulatory_consent_at' => 'datetime',
            'tax_cents' => 'integer',
            'discount_cents' => 'integer',
            'store_credit_applied_cents' => 'integer',
            'delivery_fee_cents' => 'integer',
            'delivery_attempts' => 'integer',
            'ready_notified_at' => 'datetime',
            'pickup_reminder_sent_at' => 'datetime',
            'refunded_cents' => 'integer',
            'lead_time_days' => 'integer',
            'scheduled_date' => 'date',
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

    /** @return HasMany<QcCheck, $this> */
    public function qcChecks(): HasMany
    {
        return $this->hasMany(QcCheck::class)->orderBy('id');
    }

    /** @return HasMany<DeliveryEvent, $this> */
    public function deliveryEvents(): HasMany
    {
        return $this->hasMany(DeliveryEvent::class)->orderBy('id');
    }

    /** @return BelongsTo<DeliveryZone, $this> */
    public function deliveryZone(): BelongsTo
    {
        return $this->belongsTo(DeliveryZone::class, 'delivery_zone_id');
    }

    /** @return BelongsTo<DeliverySlot, $this> */
    public function deliverySlot(): BelongsTo
    {
        return $this->belongsTo(DeliverySlot::class, 'delivery_slot_id');
    }

    /** @return BelongsTo<User, $this> */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    /** @return HasOne<Invoice, $this> */
    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    /** @return BelongsTo<Coupon, $this> */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
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

    /**
     * A notification sent after checkout (guideline ch. 6, Sprint 06) can't reuse the original token —
     * only its hash was ever stored (rule: never store a bearer credential in plaintext). Instead each
     * one mints and stores a fresh token, so the link in the most recent message always works; an
     * older, already-delivered link stops working once superseded.
     */
    public function issueFreshAccessToken(): string
    {
        $token = Str::random(48);
        $this->public_token_hash = hash('sha256', $token);
        $this->save();

        return $token;
    }

    /** Idempotency key for a Stripe call about this order (rule 03). */
    public function idempotencyKey(string $operation): string
    {
        return "order:{$this->uuid}:{$operation}";
    }

    /**
     * The id the order's payment gateway needs for its *next* call. Stable for Stripe; a rolling
     * pointer for PayPal, which uses a different id for its order/authorization/capture stages
     * (guideline ch. 6, Sprint 06) — see App\Payments\PayPalGateway's class docblock.
     */
    public function gatewayIntentId(): ?string
    {
        return $this->payment_method === 'paypal' ? $this->payment_reference : $this->stripe_payment_intent_id;
    }

    public function allItemsWeighed(): bool
    {
        return $this->items->isNotEmpty() && $this->items->every(fn (OrderItem $item) => $item->actual_weight_lb !== null);
    }

    /** The butcher-minutes this order needs, for the day's capacity budget (owner decision S04). */
    public function totalProcessingMinutes(): int
    {
        return (int) $this->items->sum(fn (OrderItem $item) => $item->estimated_minutes ?? 0);
    }

    public function totalChargedCents(): int
    {
        return $this->captured_cents + $this->extra_charged_cents + $this->balance_paid_cents - $this->refunded_cents;
    }

    /** Constant-time check of the delivery OTP the driver was told at handoff (guideline S05 proof of delivery). */
    public function matchesDeliveryOtp(?string $otp): bool
    {
        return $otp !== null && $otp !== '' && $this->delivery_otp !== null
            && hash_equals($this->delivery_otp, $otp);
    }

    public function fullDeliveryAddress(): string
    {
        return collect([$this->delivery_address_line1, $this->delivery_address_line2, "{$this->delivery_city}, {$this->delivery_state} {$this->delivery_zip}"])
            ->filter()
            ->implode(', ');
    }
}
