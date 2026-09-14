<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\WeightCast;
use App\Enums\WeightSource;
use App\Support\Weight;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Append-only record of a weighing (rule 04). Corrections are new events; rows are never updated or deleted,
 * so a pricing dispute can always be answered with who weighed what, when, on which scale.
 *
 * @property int $id
 * @property int $order_item_id
 * @property Weight $weight_lb
 * @property WeightSource $source
 * @property string|null $scale_id
 * @property int $recorded_by
 * @property string|null $note
 * @property Carbon $created_at
 * @property-read User $recorder
 * @property-read OrderItem $orderItem
 */
class WeightEvent extends Model
{
    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [
        'weight_lb',
        'source',
        'scale_id',
        'note',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'weight_lb' => WeightCast::class,
            'source' => WeightSource::class,
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Weight events are append-only — record a new event instead.'));
        static::deleting(fn () => throw new LogicException('Weight events are append-only and cannot be deleted.'));
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
