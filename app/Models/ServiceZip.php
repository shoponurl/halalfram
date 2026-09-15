<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One zip code the shop delivers to, and which zone (and so which flat fee) it belongs to.
 *
 * @property string $zip_code
 * @property int $delivery_zone_id
 * @property-read DeliveryZone $zone
 */
class ServiceZip extends Model
{
    protected $primaryKey = 'zip_code';

    protected $keyType = 'string';

    public $incrementing = false;

    /** @var list<string> */
    protected $fillable = [
        'zip_code',
        'delivery_zone_id',
    ];

    /** @return BelongsTo<DeliveryZone, $this> */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(DeliveryZone::class, 'delivery_zone_id');
    }
}
