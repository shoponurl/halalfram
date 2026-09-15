<?php

declare(strict_types=1);

use App\Actions\Orders\ApproveUnderweight;
use App\Actions\Orders\FinalizeOrder;
use App\Actions\Orders\MarkOrderAuthorized;
use App\Actions\Orders\PlaceOrder;
use App\Actions\Orders\RecordQcCheck;
use App\Actions\Orders\RecordWeight;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Enums\WeightSource;
use App\Jobs\SettleOrderPayment;
use App\Models\Order;
use App\Support\SettlementPlan;
use App\Support\Weight;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

/*
 * Sprint 01 goal: one product, end-to-end — order → hold → weight → capture → invoice.
 * These tests run every settlement branch through the queue job and the fake Stripe gateway,
 * so nothing here is mocked away at the boundary that actually moves money.
 */

beforeEach(function () {
    $this->gateway = fakePayments();
    $this->butcher = staff(Role::Butcher);
    $this->frontDesk = staff(Role::FrontDesk);
    $this->manager = staff(Role::Manager);
    // 500¢/lb × 2.000 lb = 1000; hold = 1000 × 1.10 = 1100
    $this->product = product(priceCents: 500, estLb: '2.000');
});

/** Places, authorizes and returns a single-line order ready to weigh. */
function authorizedOrder(object $ctx): Order
{
    ['order' => $order] = app(PlaceOrder::class)->handle(
        lines: [$ctx->product->id => 1],
        customer: ['customer_name' => 'Test Buyer', 'customer_email' => 'buyer@example.com', 'customer_phone' => '2675550123'],
        expectedHoldCents: 1100,
    );
    $intent = $ctx->gateway->authorize($order->stripe_payment_intent_id);
    app(MarkOrderAuthorized::class)->handle($order, $intent);

    return $order->fresh();
}

/** Records a passing QC check (owner decision S04: capture never happens straight off the scale). */
function passQc(object $ctx, Order $order): Order
{
    app(RecordQcCheck::class)->handle($order->fresh(), true, ['weight_matches' => true], $ctx->butcher, '38.0');

    return $order->fresh();
}

it('authorizes the hold and stores the Stripe capture deadline', function () {
    $order = authorizedOrder($this);

    expect($order->status)->toBe(OrderStatus::Authorized)
        ->and($order->stripe_payment_method_id)->not->toBeNull()
        ->and($order->authorization_expires_at)->not->toBeNull()
        ->and($order->authorized_at)->not->toBeNull();
});

it('rejects a butcher weighing before the order is authorized', function () {
    ['order' => $order] = app(PlaceOrder::class)->handle(
        [$this->product->id => 1],
        ['customer_name' => 'A', 'customer_email' => 'a@example.com', 'customer_phone' => '2675550123'],
        1100,
    );

    expect($this->butcher->can('recordWeight', $order))->toBeFalse();
});

it('captures exactly the actual total when weight is within the hold (rule 05: weight locks after capture)', function () {
    $order = authorizedOrder($this);
    $item = $order->items->first();

    // 1.900 lb × 500¢ = 950
    app(RecordWeight::class)->handle($item, Weight::pounds('1.900'), WeightSource::Manual, $this->butcher);
    passQc($this, $order);

    $plan = app(FinalizeOrder::class)->handle($order->fresh(), $this->frontDesk);
    expect($plan->action)->toBe(SettlementPlan::CAPTURE);

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Completed)
        ->and($order->final_cents)->toBe(950)
        ->and($order->captured_cents)->toBe(950)
        ->and($order->weights_locked_at)->not->toBeNull()
        ->and($order->settled_at)->not->toBeNull();

    $capture = $this->gateway->callsFor('capture');
    expect($capture)->toHaveCount(1)->and($capture[0]['amount'])->toBe(950);

    // A correction after settlement is refused — the ledger, not the row, is the record of truth
    expect(fn () => app(RecordWeight::class)->handle($item->fresh(), Weight::pounds('2.000'), WeightSource::Manual, $this->butcher))
        ->toThrow(ValidationException::class);
});

it('auto-charges the difference when weight is above the hold but within +25% of estimate', function () {
    $order = authorizedOrder($this);
    $item = $order->items->first();
    // 2.400 lb × 500 = 1200 (hold 1100, ceiling estimate×1.25 = 1250) → auto-charge 100
    app(RecordWeight::class)->handle($item, Weight::pounds('2.400'), WeightSource::Manual, $this->butcher);
    passQc($this, $order);

    app(FinalizeOrder::class)->handle($order->fresh(), $this->frontDesk);
    $order->refresh();

    expect($order->status)->toBe(OrderStatus::Completed)
        ->and($order->captured_cents)->toBe(1100)
        ->and($order->extra_charged_cents)->toBe(100)
        ->and($order->balance_due_cents)->toBe(0);

    expect($this->gateway->callsFor('capture'))->toHaveCount(1);
    $extra = $this->gateway->callsFor('off_session');
    expect($extra)->toHaveCount(1)->and($extra[0]['amount'])->toBe(100);
});

it('falls back to a payment link when the off-session overage charge is declined', function () {
    $this->gateway->declineOffSession = true;
    $order = authorizedOrder($this);
    $item = $order->items->first();
    app(RecordWeight::class)->handle($item, Weight::pounds('2.400'), WeightSource::Manual, $this->butcher);
    passQc($this, $order);

    app(FinalizeOrder::class)->handle($order->fresh(), $this->frontDesk);
    $order->refresh();

    expect($order->status)->toBe(OrderStatus::AwaitingBalance)
        ->and($order->balance_due_cents)->toBe(100)
        ->and($order->balance_payment_url)->toStartWith('https://checkout.stripe.test/');

    expect($this->gateway->callsFor('off_session'))->toHaveCount(1)
        ->and($this->gateway->callsFor('payment_link'))->toHaveCount(1);
});

it('sends a payment link (no auto-charge attempted) when weight is far above +25% of estimate', function () {
    $order = authorizedOrder($this);
    $item = $order->items->first();
    // 3.000 lb × 500 = 1500, above ceiling 1250 → capture hold, link the rest
    app(RecordWeight::class)->handle($item, Weight::pounds('3.000'), WeightSource::Manual, $this->butcher);
    passQc($this, $order);

    app(FinalizeOrder::class)->handle($order->fresh(), $this->frontDesk);
    $order->refresh();

    expect($order->status)->toBe(OrderStatus::AwaitingBalance)
        ->and($order->captured_cents)->toBe(1100)
        ->and($order->balance_due_cents)->toBe(400);

    expect($this->gateway->callsFor('off_session'))->toBeEmpty();   // never attempted — straight to a link
});

it('cancels the hold and writes off a below-minimum actual total', function () {
    $order = authorizedOrder($this);
    $item = $order->items->first();
    // 0.050 lb × 500 = 25¢, below the 50¢ card minimum, and also >20% under estimate → needs review first
    app(RecordWeight::class)->handle($item, Weight::pounds('0.050'), WeightSource::Manual, $this->butcher);
    passQc($this, $order);

    app(FinalizeOrder::class)->handle($order->fresh(), $this->frontDesk);
    expect($order->fresh()->status)->toBe(OrderStatus::NeedsReview);

    app(ApproveUnderweight::class)->handle($order->fresh(), $this->manager);
    $order->refresh();

    expect($order->status)->toBe(OrderStatus::Completed)
        ->and($order->written_off_cents)->toBe(25)
        ->and($order->captured_cents)->toBe(0);

    expect($this->gateway->callsFor('cancel'))->toHaveCount(1)->and($this->gateway->callsFor('capture'))->toBeEmpty();
});

it('holds for manager review on a large underweight and only a manager can approve it', function () {
    $order = authorizedOrder($this);
    $item = $order->items->first();
    // 1.000 lb × 500 = 500; estimate 1000, floor = 800 → 500 is under the floor
    app(RecordWeight::class)->handle($item, Weight::pounds('1.000'), WeightSource::Manual, $this->butcher);
    passQc($this, $order);

    app(FinalizeOrder::class)->handle($order->fresh(), $this->frontDesk);
    $order->refresh();
    expect($order->status)->toBe(OrderStatus::NeedsReview);

    expect($this->frontDesk->can('approveUnderweight', $order))->toBeFalse()
        ->and($this->manager->can('approveUnderweight', $order))->toBeTrue();

    app(ApproveUnderweight::class)->handle($order, $this->manager);
    $order->refresh();

    expect($order->status)->toBe(OrderStatus::Completed)
        ->and($order->final_cents)->toBe(500)
        ->and($order->underweight_approved_by)->toBe($this->manager->id);
});

it('will not finalize twice or finalize before every item is weighed', function () {
    $order = authorizedOrder($this);

    expect(fn () => app(FinalizeOrder::class)->handle($order, $this->frontDesk))->toThrow(ValidationException::class);

    app(RecordWeight::class)->handle($order->items->first(), Weight::pounds('2.000'), WeightSource::Manual, $this->butcher);
    passQc($this, $order);
    app(FinalizeOrder::class)->handle($order->fresh(), $this->frontDesk);

    expect(fn () => app(FinalizeOrder::class)->handle($order->fresh(), $this->frontDesk))->toThrow(ValidationException::class);
});

it('refuses to finalize before QC passes, even once every item is weighed', function () {
    $order = authorizedOrder($this);
    app(RecordWeight::class)->handle($order->items->first(), Weight::pounds('2.000'), WeightSource::Manual, $this->butcher);

    expect(fn () => app(FinalizeOrder::class)->handle($order->fresh(), $this->frontDesk))->toThrow(ValidationException::class);

    app(RecordQcCheck::class)->handle($order->fresh(), false, ['weight_matches' => false], $this->butcher, '38.0', 'Cut looked off, re-checking');
    expect($order->fresh()->status)->toBe(OrderStatus::QcFailed)
        ->and($this->butcher->can('recordWeight', $order->fresh()))->toBeTrue();   // still correctable after a failed check

    passQc($this, $order);
    app(FinalizeOrder::class)->handle($order->fresh(), $this->frontDesk);
    expect($order->fresh()->status)->toBe(OrderStatus::Completed);
});

it('refuses to finalize once the authorization has expired', function () {
    $order = authorizedOrder($this);
    $order->authorization_expires_at = now()->subMinute();
    $order->save();
    app(RecordWeight::class)->handle($order->items->first(), Weight::pounds('2.000'), WeightSource::Manual, $this->butcher);
    passQc($this, $order);

    expect(fn () => app(FinalizeOrder::class)->handle($order->fresh(), $this->frontDesk))->toThrow(ValidationException::class);
});

it('settlement is idempotent under retry: each Stripe call happens once per idempotency key', function () {
    $order = authorizedOrder($this);
    app(RecordWeight::class)->handle($order->items->first(), Weight::pounds('1.900'), WeightSource::Manual, $this->butcher);
    passQc($this, $order);
    app(FinalizeOrder::class)->handle($order->fresh(), $this->frontDesk);

    // Simulate a queue retry of the same settlement job after it already succeeded
    app(SettleOrderPayment::class, ['orderId' => $order->id])->handle($this->gateway);

    expect($this->gateway->callsFor('capture'))->toHaveCount(1);
});

it('dispatches settlement to the queue rather than running it inline in the request', function () {
    Queue::fake();
    $order = authorizedOrder($this);
    app(RecordWeight::class)->handle($order->items->first(), Weight::pounds('1.900'), WeightSource::Manual, $this->butcher);
    passQc($this, $order);

    app(FinalizeOrder::class)->handle($order->fresh(), $this->frontDesk);

    Queue::assertPushed(SettleOrderPayment::class, fn ($job) => $job->orderId === $order->id);
    // Without the queue worker running, the fake gateway never sees the capture call
    expect($this->gateway->callsFor('capture'))->toBeEmpty();
});
