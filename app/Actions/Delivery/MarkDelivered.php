<?php

declare(strict_types=1);

namespace App\Actions\Delivery;

use App\Actions\Action;
use App\Enums\DeliveryEventType;
use App\Enums\FulfilmentStatus;
use App\Enums\NotificationEvent;
use App\Jobs\DispatchOrderNotification;
use App\Models\DeliveryEvent;
use App\Models\Order;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/** Guideline ch. 6, Sprint 05: proof of delivery — a matching OTP, a photo, or both. */
final class MarkDelivered extends Action
{
    public function handle(Order $order, User $driver, ?string $otp = null, ?string $proofPhotoPath = null): Order
    {
        $order = $this->transaction(function () use ($order, $driver, $otp, $proofPhotoPath): Order {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->fulfilment_status !== FulfilmentStatus::OutForDelivery) {
                throw ValidationException::withMessages(['fulfilment' => 'This order isn’t out for delivery.']);
            }
            if ($otp === null && $proofPhotoPath === null) {
                throw ValidationException::withMessages(['proof' => 'Enter the customer’s code or attach a photo.']);
            }
            if ($otp !== null && ! $locked->matchesDeliveryOtp($otp)) {
                throw ValidationException::withMessages(['otp' => 'That code doesn’t match — ask the customer to check their order page.']);
            }

            $locked->fulfilment_status = FulfilmentStatus::Delivered;
            $locked->save();

            $event = new DeliveryEvent;
            $event->fill(['type' => DeliveryEventType::Delivered, 'proof_photo_path' => $proofPhotoPath]);
            $event->recorded_by = $driver->id;
            $locked->deliveryEvents()->save($event);

            $order->setRawAttributes($locked->getAttributes(), true);

            return $order;
        });

        DispatchOrderNotification::dispatch($order->id, NotificationEvent::Delivered->value);

        return $order;
    }
}
