<?php

declare(strict_types=1);

use App\Actions\Orders\FinalizeOrder;
use App\Actions\Orders\MarkOrderAuthorized;
use App\Actions\Orders\PlaceOrder;
use App\Actions\Orders\QuoteCart;
use App\Actions\Orders\RecordQcCheck;
use App\Actions\Orders\RecordWeight;
use App\Actions\Shipping\MarkArrivedWarm;
use App\Enums\FulfilmentStatus;
use App\Enums\Role;
use App\Enums\WeightSource;
use App\Support\Weight;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/*
 * Owner decision (guideline ch. 7, S08, recorded 2026-09-15): a cold-chain failure (arrived warm) is
 * always a full refund — never a resend or a partial credit.
 */

beforeEach(function () {
    $this->gateway = fakePayments();
    $this->shipping = fakeShipping();
    $this->manager = staff(Role::Manager);
    $butcher = staff(Role::Butcher);
    $frontDesk = staff(Role::FrontDesk);
    packingRule();

    test()->travelTo(Carbon::parse('2026-09-15 10:00:00'));   // a Tuesday — a valid ship day
    $product = product(priceCents: 500, estLb: '2.000');
    $quote = app(QuoteCart::class)->handle([$product->id => 1]);
    ['order' => $order] = app(PlaceOrder::class)->handle(
        lines: [$product->id => 1],
        customer: ['customer_name' => 'A', 'customer_email' => 'a@example.com', 'customer_phone' => '2675550123'],
        expectedHoldCents: $quote['hold_cents'] + $this->shipping->rateCents,
        fulfilment: ['method' => 'shipping', 'address_line1' => '123 Main St', 'city' => 'New York', 'state' => 'NY', 'zip' => '10001'],
    );
    $intent = $this->gateway->authorize($order->stripe_payment_intent_id);
    app(MarkOrderAuthorized::class)->handle($order, $intent);
    app(RecordWeight::class)->handle($order->fresh()->items->first(), Weight::pounds('2.000'), WeightSource::Manual, $butcher);
    app(RecordQcCheck::class)->handle($order->fresh(), true, ['weight_matches' => true], $butcher, '38.0');
    app(FinalizeOrder::class)->handle($order->fresh(), $frontDesk);   // settles, then buys the label -> Shipped

    $this->order = $order->fresh();
});

it('issues a full refund and marks the order arrived-warm', function () {
    $finalCents = (int) $this->order->final_cents;

    $updated = app(MarkArrivedWarm::class)->handle($this->order, 'Customer says the box was warm on arrival', $this->manager);

    expect($this->gateway->callsFor('refund'))->toHaveCount(1)
        ->and($this->gateway->callsFor('refund')[0]['amount'])->toBe($finalCents)
        ->and($updated->fresh()->refunded_cents)->toBe($finalCents)
        ->and($updated->fresh()->fulfilment_status)->toBe(FulfilmentStatus::Refunded);
});

it('rejects marking an order arrived-warm before it has even shipped', function () {
    $notYetShipped = $this->order->fresh();
    $notYetShipped->fulfilment_status = FulfilmentStatus::AwaitingFulfilment;
    $notYetShipped->save();

    expect(fn () => app(MarkArrivedWarm::class)->handle($notYetShipped, 'Too early', $this->manager))
        ->toThrow(ValidationException::class);
});
