<?php

declare(strict_types=1);

namespace App\Actions\Shipping;

use App\Actions\Action;
use App\Enums\PackageTemperature;
use App\Models\PackingRule;
use App\Models\Product;
use App\Shipping\ShippingGateway;
use App\Support\Weight;
use Illuminate\Validation\ValidationException;

/**
 * Prices a nationwide shipment for checkout: picks the package temperature (owner decision,
 * guideline ch. 7, S08 — frozen by default, chilled only where a product needs it), the matching
 * database-driven packing rule for the order's weight, and quotes the overnight rate from EasyPost.
 */
final class PriceShipment extends Action
{
    public function __construct(private readonly ShippingGateway $gateway) {}

    /**
     * @param  list<array{product: Product, quantity: int, weight: Weight}>  $quoteLines
     * @param  array{name: string, address1: string, address2?: string|null, city: string, state: string, zip: string}  $toAddress
     * @return array{temperature: PackageTemperature, packing_rule: PackingRule, rate_cents: int}
     */
    public function handle(array $quoteLines, array $toAddress): array
    {
        $temperature = PackageTemperature::Frozen;
        $totalWeight = Weight::zero();
        foreach ($quoteLines as $line) {
            if ($line['product']->requires_chilled_shipping) {
                $temperature = PackageTemperature::Chilled;
            }
            $totalWeight = $totalWeight->plus($line['weight']);
        }

        /** @var PackingRule|null $packingRule */
        $packingRule = PackingRule::query()
            ->active()
            ->where('temperature', $temperature->value)
            ->where('min_weight_lb', '<=', $totalWeight->toDecimal())
            ->where('max_weight_lb', '>=', $totalWeight->toDecimal())
            ->orderBy('sort_order')
            ->first();

        if ($packingRule === null) {
            throw ValidationException::withMessages([
                'cart' => 'This order is too large or too small to ship — please call us to arrange a custom shipment.',
            ]);
        }

        $parcel = [
            'length_in' => (string) $packingRule->box_length_in,
            'width_in' => (string) $packingRule->box_width_in,
            'height_in' => (string) $packingRule->box_height_in,
            'weight_lb' => $packingRule->totalWeightFor($totalWeight)->toDecimal(),
        ];

        $rate = $this->gateway->quoteOvernightRate($toAddress, $parcel);
        if ($rate === null) {
            throw ValidationException::withMessages([
                'shipping_zip' => 'We can\'t get an overnight shipping rate for that address — please double check it or call us.',
            ]);
        }

        return ['temperature' => $temperature, 'packing_rule' => $packingRule, 'rate_cents' => $rate->rateCents];
    }
}
