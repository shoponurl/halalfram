<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One production date's butcher-minutes budget (owner decision, guideline ch. 7 S04). A date with no
 * row uses config('catchweight.daily_capacity_minutes') and is open by default. The date itself is
 * kept as a plain 'Y-m-d' string (not cast) since it's also the primary key.
 *
 * @property string $date
 * @property int|null $capacity_minutes
 * @property bool $is_open
 */
class ProductionDay extends Model
{
    protected $primaryKey = 'date';

    protected $keyType = 'string';

    public $incrementing = false;

    /** @var list<string> */
    protected $fillable = [
        'date',
        'capacity_minutes',
        'is_open',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'capacity_minutes' => 'integer',
            'is_open' => 'boolean',
        ];
    }

    public function capacityMinutes(): int
    {
        return $this->capacity_minutes ?? (int) config('catchweight.daily_capacity_minutes');
    }

    public function carbonDate(): Carbon
    {
        return Carbon::parse($this->date);
    }
}
