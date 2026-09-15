<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Append-only staff-action log (guideline ch. 6, Sprint 00 core domain: "RBAC with 6 roles, audit
 * log"). Deliberately scoped to admin/staff-facing events (role changes, logins, cash payments,
 * store-credit issuance, CCPA actions) rather than every model's every field — see docs/sprint-07.md.
 *
 * @property int $id
 * @property int|null $causer_id
 * @property string $event
 * @property string|null $auditable_type
 * @property int|string|null $auditable_id
 * @property string $description
 * @property array<string, mixed>|null $meta
 * @property Carbon $created_at
 * @property-read User|null $causer
 */
class AuditLog extends Model
{
    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'causer_id',
        'event',
        'auditable_type',
        'auditable_id',
        'description',
        'meta',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['meta' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit log rows are append-only.'));
        static::deleting(fn () => throw new LogicException('Audit log rows are append-only.'));
    }

    /** @return BelongsTo<User, $this> */
    public function causer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'causer_id');
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function record(string $event, string $description, ?Model $subject = null, array $meta = [], ?User $causer = null): self
    {
        $log = new self;
        $id = $causer->id ?? auth()->id();
        $log->causer_id = is_int($id) ? $id : null;
        $log->event = $event;
        $log->auditable_type = $subject?->getMorphClass();
        $log->auditable_id = $subject?->getKey();
        $log->description = $description;
        $log->meta = $meta === [] ? null : $meta;
        $log->save();

        return $log;
    }
}
