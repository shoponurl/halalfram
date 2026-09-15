<?php

declare(strict_types=1);

namespace App\Actions\Delivery;

use App\Actions\Action;
use App\Enums\OrderStatus;
use App\Models\DeliverySlot;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\ServiceZip;
use App\Support\SoftLaunch;
use Illuminate\Validation\ValidationException;

/**
 * Owner decision (guideline ch. 7, S05): the service area is an explicit zip list. Checkout must
 * refuse — with a reason — a zip outside it, and a delivery slot once its driver capacity is used up.
 */
final class ReserveDeliverySlot extends Action
{
    /** @throws ValidationException if the zip isn't in the service area */
    public function zoneForZip(string $zip): DeliveryZone
    {
        SoftLaunch::assertDeliveryZipAllowed($zip);

        /** @var ServiceZip|null $serviceZip */
        $serviceZip = ServiceZip::query()->with('zone')->find($zip);
        if ($serviceZip === null || ! $serviceZip->zone->is_active) {
            throw ValidationException::withMessages([
                'delivery_zip' => "Sorry, we don't deliver to {$zip} yet. Store pickup is available for every order.",
            ]);
        }

        return $serviceZip->zone;
    }

    /** @throws ValidationException if the slot no longer has capacity */
    public function handle(int $slotId): DeliverySlot
    {
        return $this->transaction(function () use ($slotId): DeliverySlot {
            /** @var DeliverySlot $slot */
            $slot = DeliverySlot::query()->whereKey($slotId)->lockForUpdate()->firstOrFail();

            $booked = Order::query()->where('delivery_slot_id', $slot->id)->whereNotIn('status', OrderStatus::abandoned())->count();
            if ($booked >= $slot->capacity) {
                throw ValidationException::withMessages(['delivery_slot_id' => 'That delivery window just filled up — please choose another.']);
            }

            return $slot;
        });
    }
}
