<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Append-only QC decision (rule 04, guideline ch. 6 Sprint 04): a failed check is never edited — the
 * order goes back for re-cutting and re-weighing, then gets a fresh check.
 *
 * @property int $id
 * @property int $order_id
 * @property bool $passed
 * @property array<string, bool> $checklist
 * @property string|null $temperature_f
 * @property string|null $note
 * @property int $checked_by
 * @property Carbon $created_at
 * @property-read Order $order
 * @property-read User $inspector
 */
class QcCheck extends Model
{
    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [
        'passed',
        'checklist',
        'temperature_f',
        'note',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'passed' => 'boolean',
            'checklist' => 'array',
            'temperature_f' => 'decimal:1',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('QC checks are append-only — record a new check instead.'));
        static::deleting(fn () => throw new LogicException('QC checks are append-only and cannot be deleted.'));
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<User, $this> */
    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }
}
