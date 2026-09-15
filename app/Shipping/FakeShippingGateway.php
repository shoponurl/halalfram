<?php

declare(strict_types=1);

namespace App\Shipping;

use App\Shipping\Data\PurchasedLabel;
use App\Shipping\Data\ShippingRate;
use App\Shipping\Data\TrackingUpdate;
use App\Shipping\Data\VerifiedAddress;

/** In-memory stand-in for tests and local development without EasyPost credentials. */
final class FakeShippingGateway implements ShippingGateway
{
    public bool $addressInvalid = false;

    public bool $noRateAvailable = false;

    public int $rateCents = 4500;

    /** @var list<array{to: array<string, mixed>, parcel: array<string, mixed>}> */
    public array $labelsBought = [];

    public function isConfigured(): bool
    {
        return true;
    }

    public function verifyAddress(array $to): VerifiedAddress
    {
        return $this->addressInvalid
            ? new VerifiedAddress(false, ['Address could not be verified.'])
            : new VerifiedAddress(true);
    }

    public function quoteOvernightRate(array $to, array $parcel): ?ShippingRate
    {
        return $this->noRateAvailable ? null : new ShippingRate('FedEx', 'FIRST_OVERNIGHT', $this->rateCents, 1);
    }

    /**
     * @param  array{name: string, address1: string, address2?: string|null, city: string, state: string, zip: string}  $to
     * @param  array{length_in: string, width_in: string, height_in: string, weight_lb: string}  $parcel
     */
    public function buyOvernightLabel(array $to, array $parcel): PurchasedLabel
    {
        $this->labelsBought[] = ['to' => $to, 'parcel' => $parcel];
        $n = count($this->labelsBought);

        return new PurchasedLabel(
            shipmentId: "shp_fake_{$n}",
            carrier: 'FedEx',
            service: 'FIRST_OVERNIGHT',
            rateCents: $this->rateCents,
            trackingNumber: "TRACK_FAKE_{$n}",
            trackingUrl: "https://track.example.test/{$n}",
            labelUrl: "https://label.example.test/{$n}.pdf",
        );
    }

    public function parseTrackingWebhook(string $payload, string $signatureHeader): TrackingUpdate
    {
        /** @var array{shipment_id?: string, status?: string} $data */
        $data = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);

        return new TrackingUpdate((string) ($data['shipment_id'] ?? ''), (string) ($data['status'] ?? 'unknown'));
    }
}
