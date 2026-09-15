<?php

declare(strict_types=1);

namespace App\Shipping;

use App\Shipping\Data\PurchasedLabel;
use App\Shipping\Data\ShippingRate;
use App\Shipping\Data\TrackingUpdate;
use App\Shipping\Data\VerifiedAddress;

/** Guideline ch. 6, Sprint 08: EasyPost — rate quote, label, tracking webhook. */
interface ShippingGateway
{
    public function isConfigured(): bool;

    /**
     * @param  array{name: string, address1: string, address2?: string|null, city: string, state: string, zip: string}  $to
     */
    public function verifyAddress(array $to): VerifiedAddress;

    /**
     * @param  array{name: string, address1: string, address2?: string|null, city: string, state: string, zip: string}  $to
     * @param  array{length_in: string, width_in: string, height_in: string, weight_lb: string}  $parcel
     */
    public function quoteOvernightRate(array $to, array $parcel): ?ShippingRate;

    /**
     * EasyPost has no client-supplied idempotency key for buying a label — the caller (see
     * App\Jobs\PurchaseShippingLabel) must itself check the order doesn't already have a
     * tracking number before calling this, so a queue retry never buys a second label.
     *
     * @param  array{name: string, address1: string, address2?: string|null, city: string, state: string, zip: string}  $to
     * @param  array{length_in: string, width_in: string, height_in: string, weight_lb: string}  $parcel
     */
    public function buyOvernightLabel(array $to, array $parcel): PurchasedLabel;

    public function parseTrackingWebhook(string $payload, string $signatureHeader): TrackingUpdate;
}
