<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NotificationEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Dedupe ledger (guideline DoD, S06): a unique (order_id, event, channel) row means a retried queue job
 * or a duplicate trigger can insert-or-be-rejected before ever sending twice — see
 * App\Actions\Notifications\SendOrderNotification.
 *
 * @property int $id
 * @property int $order_id
 * @property NotificationEvent $event
 * @property string $channel
 * @property Carbon $sent_at
 * @property-read Order $order
 */
class NotificationLog extends Model
{
    protected $table = 'notification_log';

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = ['order_id', 'event', 'channel'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['event' => NotificationEvent::class, 'sent_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Notification log rows are append-only.'));
        static::deleting(fn () => throw new LogicException('Notification log rows are append-only.'));
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
