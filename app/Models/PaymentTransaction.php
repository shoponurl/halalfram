<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentTransactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Ledger of money movements. Append-only, like weight events.
 *
 * @property int $id
 * @property int $order_id
 * @property PaymentTransactionType $type
 * @property string $status
 * @property int $amount_cents
 * @property string|null $stripe_object_id
 * @property string|null $idempotency_key
 * @property string|null $failure_message
 * @property Carbon $created_at
 */
class PaymentTransaction extends Model
{
    public const UPDATED_AT = null;

    public const SUCCEEDED = 'succeeded';

    public const FAILED = 'failed';

    public const PENDING = 'pending';

    /** @var list<string> */
    protected $fillable = [
        'type',
        'status',
        'amount_cents',
        'stripe_object_id',
        'idempotency_key',
        'failure_message',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => PaymentTransactionType::class,
            'amount_cents' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Payment transactions are append-only.'));
        static::deleting(fn () => throw new LogicException('Payment transactions are append-only.'));
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
