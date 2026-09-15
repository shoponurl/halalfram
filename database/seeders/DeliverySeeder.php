<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\DeliverySlot;
use App\Models\DeliveryZone;
use App\Models\ServiceZip;
use Illuminate\Database\Seeder;

/**
 * A representative starter service area around the shop (3 Kelly Street, Lansdowne, PA 19050) and a
 * few open delivery windows — guideline ch. 6, Sprint 05. Not the owner's real zones/zips/schedule;
 * those go in via /admin/delivery-zones and /admin/delivery-slots.
 */
class DeliverySeeder extends Seeder
{
    public function run(): void
    {
        $local = DeliveryZone::query()->firstOrNew(['name' => 'Lansdowne & nearby']);
        $local->fill(['flat_fee_cents' => 500, 'is_active' => true]);
        $local->save();

        foreach (['19050', '19026', '19082', '19023'] as $zip) {
            $serviceZip = ServiceZip::query()->firstOrNew(['zip_code' => $zip]);
            $serviceZip->delivery_zone_id = $local->id;
            $serviceZip->save();
        }

        // Demo delivery windows — local/staging only, so a real deploy doesn't accumulate fake slots.
        if (! app()->environment(['local', 'staging', 'testing']) || DeliverySlot::query()->exists()) {
            return;
        }

        foreach ([1, 2, 3] as $daysAhead) {
            DeliverySlot::query()->create(['date' => now()->addDays($daysAhead)->toDateString(), 'start_time' => '16:00', 'end_time' => '18:00', 'capacity' => 6]);
        }
    }
}
