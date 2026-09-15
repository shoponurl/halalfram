<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NotificationEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Guideline task (S06): a template manager, so notification copy changes without a deploy.
 * Body/subject support {{placeholder}} tokens — see App\Actions\Notifications\SendOrderNotification.
 *
 * @property int $id
 * @property NotificationEvent $event
 * @property string $channel
 * @property string|null $subject
 * @property string $body
 * @property bool $active
 */
class NotificationTemplate extends Model
{
    /** @var list<string> */
    protected $fillable = ['event', 'channel', 'subject', 'body', 'active'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['event' => NotificationEvent::class, 'active' => 'boolean'];
    }

    /**
     * @param  Builder<NotificationTemplate>  $query
     * @return Builder<NotificationTemplate>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /** @param  array<string, string>  $placeholders */
    public function render(array $placeholders): string
    {
        return strtr($this->body, self::tokens($placeholders));
    }

    /** @param  array<string, string>  $placeholders */
    public function renderSubject(array $placeholders): ?string
    {
        return $this->subject === null ? null : strtr($this->subject, self::tokens($placeholders));
    }

    /** @param  array<string, string>  $placeholders
     * @return array<string, string> */
    private static function tokens(array $placeholders): array
    {
        $tokens = [];
        foreach ($placeholders as $key => $value) {
            $tokens["{{{$key}}}"] = $value;
        }

        return $tokens;
    }
}
