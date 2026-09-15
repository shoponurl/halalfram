<?php

declare(strict_types=1);

namespace App\Shipping;

use App\Shipping\Data\PurchasedLabel;
use App\Shipping\Data\ShippingRate;
use App\Shipping\Data\TrackingUpdate;
use App\Shipping\Data\VerifiedAddress;
use App\Shipping\Exceptions\NoOvernightRateAvailable;
use App\Shipping\Exceptions\ShippingNotConfigured;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * EasyPost, called directly via Laravel's HTTP client (same rationale as Sprint 06/07's
 * PayPalGateway/EasyPost-adjacent choices: a plain JSON REST API, kept symmetric with the other
 * gateways rather than pulling in the official SDK).
 *
 * Owner decision (guideline ch. 7, S08): overnight only. "Overnight" here means any rate EasyPost
 * quotes with a 1-day estimated transit — the cheapest such rate across whichever carriers the
 * EasyPost account has enabled, not one specific carrier's named service.
 */
final class EasyPostGateway implements ShippingGateway
{
    private const BASE_URL = 'https://api.easypost.com/v2';

    public function __construct(
        private readonly ?string $apiKey,
        private readonly ?string $webhookSecret,
        private readonly string $fromName,
        private readonly string $fromAddress1,
        private readonly string $fromCity,
        private readonly string $fromState,
        private readonly string $fromZip,
    ) {}

    public function isConfigured(): bool
    {
        return filled($this->apiKey);
    }

    public function verifyAddress(array $to): VerifiedAddress
    {
        $response = $this->request()->post('/addresses', [
            'address' => $this->addressPayload($to),
            'verify' => ['delivery'],
        ])->throw()->json();

        $verification = $response['verifications']['delivery'] ?? [];
        if (($verification['success'] ?? false) === true) {
            return new VerifiedAddress(true);
        }

        /** @var list<array<string, mixed>> $errors */
        $errors = is_array($verification['errors'] ?? null) ? $verification['errors'] : [];

        return new VerifiedAddress(false, array_map(fn (array $e) => (string) ($e['message'] ?? 'Address could not be verified.'), $errors));
    }

    public function quoteOvernightRate(array $to, array $parcel): ?ShippingRate
    {
        $shipment = $this->createShipment($to, $parcel);
        $rate = $this->cheapestOvernightRate($shipment);

        return $rate === null ? null : new ShippingRate(
            carrier: (string) $rate['carrier'],
            service: (string) $rate['service'],
            rateCents: (int) round(((float) $rate['rate']) * 100),
            estimatedDays: (int) ($rate['delivery_days'] ?? 1),
        );
    }

    /**
     * @param  array{name: string, address1: string, address2?: string|null, city: string, state: string, zip: string}  $to
     * @param  array{length_in: string, width_in: string, height_in: string, weight_lb: string}  $parcel
     */
    public function buyOvernightLabel(array $to, array $parcel): PurchasedLabel
    {
        $shipment = $this->createShipment($to, $parcel);
        $rate = $this->cheapestOvernightRate($shipment);
        if ($rate === null) {
            throw new NoOvernightRateAvailable('No overnight rate is available for this address.');
        }

        $bought = $this->request()->post("/shipments/{$shipment['id']}/buy", [
            'rate' => ['id' => $rate['id']],
        ])->throw()->json();

        $postageLabel = $bought['postage_label'] ?? [];
        $tracker = $bought['tracker'] ?? [];

        return new PurchasedLabel(
            shipmentId: (string) $bought['id'],
            carrier: (string) $rate['carrier'],
            service: (string) $rate['service'],
            rateCents: (int) round(((float) $rate['rate']) * 100),
            trackingNumber: (string) ($tracker['tracking_code'] ?? ''),
            trackingUrl: (string) ($tracker['public_url'] ?? ''),
            labelUrl: (string) ($postageLabel['label_url'] ?? ''),
        );
    }

    /**
     * EasyPost signs webhooks with HMAC-SHA256 over the raw body, header `X-Hmac-Signature` shaped
     * like "hmac-sha256-hex=<hex>".
     */
    public function parseTrackingWebhook(string $payload, string $signatureHeader): TrackingUpdate
    {
        if ($this->webhookSecret === null) {
            throw new ShippingNotConfigured('EasyPost webhook secret is not configured.');
        }

        $expected = 'hmac-sha256-hex='.hash_hmac('sha256', $payload, $this->webhookSecret);
        if (! hash_equals($expected, $signatureHeader)) {
            throw new RuntimeException('Invalid EasyPost webhook signature.');
        }

        /** @var array<string, mixed> $event */
        $event = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
        $result = $event['result'] ?? [];
        $shipmentId = (string) ($result['shipment_id'] ?? $result['id'] ?? '');
        $status = (string) ($result['status'] ?? '');

        return new TrackingUpdate($shipmentId, match ($status) {
            'delivered' => 'delivered',
            'in_transit', 'out_for_delivery', 'pre_transit' => 'in_transit',
            'failure', 'error', 'cancelled', 'return_to_sender' => 'failure',
            default => 'unknown',
        });
    }

    /**
     * @param  array{name: string, address1: string, address2?: string|null, city: string, state: string, zip: string}  $to
     * @param  array{length_in: string, width_in: string, height_in: string, weight_lb: string}  $parcel
     * @return array<string, mixed>
     */
    private function createShipment(array $to, array $parcel): array
    {
        return $this->request()->post('/shipments', [
            'shipment' => [
                'to_address' => $this->addressPayload($to),
                'from_address' => [
                    'name' => $this->fromName,
                    'street1' => $this->fromAddress1,
                    'city' => $this->fromCity,
                    'state' => $this->fromState,
                    'zip' => $this->fromZip,
                    'country' => 'US',
                ],
                'parcel' => [
                    'length' => $parcel['length_in'],
                    'width' => $parcel['width_in'],
                    'height' => $parcel['height_in'],
                    'weight' => (string) (((float) $parcel['weight_lb']) * 16),   // EasyPost parcel weight is in ounces
                ],
                'options' => ['delivery_confirmation' => 'SIGNATURE'],
            ],
        ])->throw()->json();
    }

    /**
     * @param  array<string, mixed>  $shipment
     * @return array<string, mixed>|null
     */
    private function cheapestOvernightRate(array $shipment): ?array
    {
        /** @var list<array<string, mixed>> $rates */
        $rates = is_array($shipment['rates'] ?? null) ? $shipment['rates'] : [];
        $overnight = array_values(array_filter($rates, fn (array $r) => (int) ($r['delivery_days'] ?? 99) <= 1));
        if ($overnight === []) {
            return null;
        }

        usort($overnight, fn (array $a, array $b) => ((float) $a['rate']) <=> ((float) $b['rate']));

        return $overnight[0];
    }

    /**
     * @param  array{name: string, address1: string, address2?: string|null, city: string, state: string, zip: string}  $to
     * @return array<string, mixed>
     */
    private function addressPayload(array $to): array
    {
        return array_filter([
            'name' => $to['name'],
            'street1' => $to['address1'],
            'street2' => $to['address2'] ?? null,
            'city' => $to['city'],
            'state' => $to['state'],
            'zip' => $to['zip'],
            'country' => 'US',
        ], fn ($v) => $v !== null);
    }

    private function request(): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw new ShippingNotConfigured('EasyPost API key is not configured.');
        }

        return Http::baseUrl(self::BASE_URL)->withBasicAuth((string) $this->apiKey, '')->acceptJson();
    }
}
