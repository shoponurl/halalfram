<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A no-ship date (guideline ch. 6, S08: "weekend and holiday blackout"), on top of the standing
 * Monday-Thursday rule in config('catchweight.ship_weekdays') — see App\Actions\Shipping\ComputeShipDate.
 *
 * @property int $id
 * @property Carbon $date
 * @property string $reason
 */
class ShipBlackoutDate extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'date',
        'reason',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['date' => 'date'];
    }
}
