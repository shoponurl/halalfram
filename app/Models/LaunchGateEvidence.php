<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LaunchGateItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Append-only proof for the launch gate: a drill result or a manual sign-off. A later failed drill
 * supersedes an earlier pass — `launch:check` always reads the most recent row per item.
 *
 * @property int $id
 * @property LaunchGateItem $item
 * @property bool $passed
 * @property string $evidence
 * @property int|null $duration_ms
 * @property string $environment
 * @property int|null $recorded_by
 * @property Carbon $recorded_at
 * @property-read User|null $recorder
 */
class LaunchGateEvidence extends Model
{
    public $timestamps = false;

    protected $table = 'launch_gate_evidence';

    /** @var list<string> */
    protected $fillable = ['item', 'passed', 'evidence', 'duration_ms'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'item' => LaunchGateItem::class,
            'passed' => 'boolean',
            'duration_ms' => 'integer',
            'recorded_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Launch gate evidence is append-only — record a new row instead.'));
        static::deleting(fn () => throw new LogicException('Launch gate evidence is append-only.'));
    }

    public static function record(LaunchGateItem $item, bool $passed, string $evidence, ?int $durationMs = null, ?User $by = null): self
    {
        $row = new self;
        $row->fill(['item' => $item, 'passed' => $passed, 'evidence' => $evidence, 'duration_ms' => $durationMs]);
        $row->environment = (string) config('app.env');
        $row->recorded_by = $by?->id;
        $row->recorded_at = now();
        $row->save();

        return $row;
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
