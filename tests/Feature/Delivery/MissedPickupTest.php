<?php

declare(strict_types=1);

use App\Actions\Delivery\MarkReadyForPickup;
use App\Actions\Orders\FinalizeOrder;
use App\Actions\Orders\MarkOrderAuthorized;
use App\Actions\Orders\PlaceOrder;
use App\Actions\Orders\QuoteCart;
use App\Actions\Orders\RecordQcCheck;
use App\Actions\Orders\RecordWeight;
use App\Enums\FulfilmentStatus;
use App\Enums\Role;
use App\Enums\WeightSource;
use App\Models\Order;
use App\Support\Weight;
use Illuminate\Support\Facades\Artisan;

/*
 * Owner decision (guideline ch. 7, S05): one reminder, then a partial-refund write-off for orders
 * still uncollected past the deadline.
 */

beforeEach(function () {
    $this->gateway = fakePayments();
    $this->manager = staff(Role::Manager);
});

it('sends no reminder and writes off nothing before either deadline', function () {
    $order = settledOrderForPickup($this);
    app(MarkReadyForPickup::class)->handle($order, $this->manager);

    Artisan::call('orders:expire-missed-pickups');

    $order = $order->fresh();
    expect($order->fulfilment_status)->toBe(FulfilmentStatus::ReadyForPickup)
        ->and($order->pickup_reminder_sent_at)->toBeNull();
});

it('sends the one reminder once the reminder window passes', function () {
    $order = settledOrderForPickup($this);
    app(MarkReadyForPickup::class)->handle($order, $this->manager);

    $this->travelTo(now()->addHours((int) config('catchweight.pickup_reminder_hours') + 1));
    Artisan::call('orders:expire-missed-pickups');

    $order = $order->fresh();
    expect($order->pickup_reminder_sent_at)->not->toBeNull()
        ->and($order->fulfilment_status)->toBe(FulfilmentStatus::ReadyForPickup);

    // Running it again doesn't send a second reminder or double up the ledger
    $sentAt = $order->pickup_reminder_sent_at;
    Artisan::call('orders:expire-missed-pickups');
    expect($order->fresh()->pickup_reminder_sent_at->eq($sentAt))->toBeTrue();
});

it('writes off and partially refunds once the write-off window passes', function () {
    $order = settledOrderForPickup($this);
    app(MarkReadyForPickup::class)->handle($order, $this->manager);
    $finalTotal = $order->fresh()->final_cents;

    $this->travelTo(now()->addHours((int) config('catchweight.pickup_writeoff_hours') + 1));
    Artisan::call('orders:expire-missed-pickups');

    $order = $order->fresh();
    $expectedRefund = (int) round($finalTotal * (int) config('catchweight.pickup_writeoff_refund_pct') / 100);
    expect($order->fulfilment_status)->toBe(FulfilmentStatus::Refunded)
        ->and($order->refunded_cents)->toBe($expectedRefund)
        ->and($this->gateway->callsFor('refund'))->toHaveCount(1);
});

/** Places, pays and fully settles a pickup order, ready to be marked ready for pickup. */
function settledOrderForPickup(object $ctx): Order
{
    $butcher = staff(Role::Butcher);
    $frontDesk = staff(Role::FrontDesk);
    $product = product(priceCents: 500, estLb: '2.000');
    $quote = app(QuoteCart::class)->handle([$product->id => 1]);

    ['order' => $order] = app(PlaceOrder::class)->handle(
        lines: [$product->id => 1],
        customer: ['customer_name' => 'Test Buyer', 'customer_email' => 'buyer@example.com', 'customer_phone' => '2675550123'],
        expectedHoldCents: $quote['hold_cents'],
    );
    $intent = $ctx->gateway->authorize($order->stripe_payment_intent_id);
    app(MarkOrderAuthorized::class)->handle($order, $intent);
    app(RecordWeight::class)->handle($order->fresh()->items->first(), Weight::pounds('2.000'), WeightSource::Manual, $butcher);
    app(RecordQcCheck::class)->handle($order->fresh(), true, ['weight_matches' => true], $butcher, '38.0');
    app(FinalizeOrder::class)->handle($order->fresh(), $frontDesk);

    return $order->fresh();
}
