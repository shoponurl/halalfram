<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DeliveryEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Append-only fulfilment ledger (rule 04, guideline ch. 6 Sprint 05): a failed delivery attempt is a
 * fact that happened and is never edited away, even once a later re-attempt succeeds.
 *
 * @property int $id
 * @property int $order_id
 * @property DeliveryEventType $type
 * @property string|null $note
 * @property string|null $proof_photo_path
 * @property int|null $recorded_by
 * @property Carbon $created_at
 * @property-read Order $order
 * @property-read User|null $recorder
 */
class DeliveryEvent extends Model
{
    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [
        'type',
        'note',
        'proof_photo_path',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => DeliveryEventType::class,
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Delivery events are append-only — record a new event instead.'));
        static::deleting(fn () => throw new LogicException('Delivery events are append-only and cannot be deleted.'));
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
