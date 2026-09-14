<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\WeightCast;
use App\Enums\StockMovementType;
use App\Support\Weight;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Append-only ledger of everything that happens to a lot's stock (rule 04) — received, reserved,
 * released, consumed, wasted. Rows are never updated or deleted; on_hand/reserved on the Lot are a
 * cache kept in sync inside the same locked transaction as each insert (rule 06).
 *
 * @property int $id
 * @property int $lot_id
 * @property StockMovementType $type
 * @property Weight $weight_lb
 * @property int|null $order_item_id
 * @property int|null $recorded_by
 * @property string|null $note
 * @property Carbon $created_at
 * @property-read Lot $lot
 * @property-read OrderItem|null $orderItem
 */
class StockMovement extends Model
{
    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [
        'lot_id',
        'type',
        'weight_lb',
        'order_item_id',
        'recorded_by',
        'note',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'weight_lb' => WeightCast::class,
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Stock movements are append-only — record a new movement instead.'));
        static::deleting(fn () => throw new LogicException('Stock movements are append-only and cannot be deleted.'));
    }

    /** @return BelongsTo<Lot, $this> */
    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    /** @return BelongsTo<OrderItem, $this> */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
