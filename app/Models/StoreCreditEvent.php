<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Append-only ledger (rule 04) behind App\Models\StoreCreditAccount's cached balance.
 *
 * @property int $id
 * @property string $customer_email
 * @property string $type
 * @property int $amount_cents
 * @property string|null $reason
 * @property int|null $order_id
 * @property int|null $created_by
 * @property Carbon $created_at
 * @property-read Order|null $order
 * @property-read User|null $issuedBy
 */
class StoreCreditEvent extends Model
{
    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = ['customer_email', 'type', 'amount_cents', 'reason', 'order_id', 'created_by'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['amount_cents' => 'integer', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Store credit events are append-only.'));
        static::deleting(fn () => throw new LogicException('Store credit events are append-only.'));
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<User, $this> */
    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
