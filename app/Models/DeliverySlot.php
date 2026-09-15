<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A delivery date + time-window with its own driver capacity (owner decision, guideline ch. 7 S05):
 * the window itself is capped at config('catchweight.cold_chain_max_hours') so nothing sits outside
 * refrigeration too long.
 *
 * @property int $id
 * @property Carbon $date
 * @property string $start_time
 * @property string $end_time
 * @property int $capacity
 */
class DeliverySlot extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'date',
        'start_time',
        'end_time',
        'capacity',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'capacity' => 'integer',
        ];
    }

    public function label(): string
    {
        $time = fn (string $t) => Carbon::parse($t)->format('g:i A');

        return $this->date->format('D, M j').' · '.$time($this->start_time).'–'.$time($this->end_time);
    }

    /**
     * @param  Builder<DeliverySlot>  $query
     * @return Builder<DeliverySlot>
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('date', '>=', now()->toDateString())->orderBy('date')->orderBy('start_time');
    }
}
