<?php

declare(strict_types=1);

use App\Actions\Inventory\ReceiveStock;
use App\Actions\Launch\ReconcileLedgers;
use App\Actions\Orders\FinalizeOrder;
use App\Actions\Orders\MarkOrderAuthorized;
use App\Actions\Orders\PlaceOrder;
use App\Actions\Orders\RecordQcCheck;
use App\Actions\Orders\RecordWeight;
use App\Actions\Payments\IssueStoreCredit;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Enums\StorageLocation;
use App\Enums\WeightSource;
use App\Models\Order;
use App\Support\Weight;
use Illuminate\Support\Facades\DB;

/*
 * Guideline ch. 8 launch gate: reconciliation matches. Each test drives the real actions (so every
 * cached balance and its ledger are written the normal way), then corrupts exactly one cached number
 * behind the actions' back — the kind of drift a mismatch is meant to catch.
 */

beforeEach(function () {
    $this->gateway = fakePayments();
    $this->butcher = staff(Role::Butcher);
    $this->frontDesk = staff(Role::FrontDesk);
    $this->product = product(priceCents: 500, estLb: '2.000');
    // Through the real action, so the lot's opening balance has its "received" ledger row
    $this->lot = app(ReceiveStock::class)->handle($this->product, null, StorageLocation::Chiller, now(), now()->addDays(5), Weight::pounds('20.000'), $this->butcher);

    ['order' => $order] = app(PlaceOrder::class)->handle(
        lines: [$this->product->id => 1],
        customer: ['customer_name' => 'A', 'customer_email' => 'buyer@example.com', 'customer_phone' => '2675550123'],
        expectedHoldCents: 1100,
    );
    app(MarkOrderAuthorized::class)->handle($order, $this->gateway->authorize($order->stripe_payment_intent_id));
    app(RecordWeight::class)->handle($order->fresh()->items->first(), Weight::pounds('2.100'), WeightSource::Manual, $this->butcher);
    app(RecordQcCheck::class)->handle($order->fresh(), true, ['weight_matches' => true], $this->butcher, '38.0');
    app(FinalizeOrder::class)->handle($order->fresh(), $this->frontDesk);
    $this->order = $order->fresh();

    app(IssueStoreCredit::class)->handle('buyer@example.com', 700, 'Goodwill', staff(Role::Manager));
});

it('reconciles cleanly after a normal order, stock and store-credit lifecycle', function () {
    expect($this->order->status)->toBe(OrderStatus::Completed)
        ->and($this->order->captured_cents)->toBeGreaterThan(0)
        ->and(app(ReconcileLedgers::class)->handle())->toBe([]);
});

it('catches a captured amount that no payment transaction backs', function () {
    DB::table('orders')->where('id', $this->order->id)->update(['captured_cents' => $this->order->captured_cents + 100]);

    expect(app(ReconcileLedgers::class)->handle())->toHaveCount(1)
        ->and(app(ReconcileLedgers::class)->handle()[0])->toContain("Order {$this->order->number}: captured_cents");
});

it('catches stock on hand that the movement ledger does not add up to', function () {
    DB::table('lots')->where('id', $this->lot->id)->update(['on_hand_weight_lb' => '99.000']);

    expect(app(ReconcileLedgers::class)->handle()[0] ?? '')->toContain("Lot {$this->lot->lot_number}: on hand is 99.000 lb");
});

it('catches a store credit balance that the credit ledger does not add up to — without printing the email', function () {
    DB::table('store_credit_accounts')->where('customer_email', 'buyer@example.com')->update(['balance_cents' => 5000]);

    $mismatches = app(ReconcileLedgers::class)->handle();

    expect($mismatches)->toHaveCount(1)
        ->and($mismatches[0])->toContain('balance is 5000 but the credit ledger adds up to 700')
        ->and($mismatches[0])->not->toContain('buyer@example.com');
});

it('catches an actual weight that was overwritten instead of recorded as an event', function () {
    DB::table('order_items')->where('order_id', $this->order->id)->update(['actual_weight_lb' => '1.500']);

    expect(app(ReconcileLedgers::class)->handle()[0] ?? '')->toContain('latest weight event says 2.100');
});

it('exits non-zero and raises a critical alert from the scheduled command on a mismatch', function () {
    $this->artisan('ops:reconcile')->assertSuccessful();

    DB::table('orders')->where('id', $this->order->id)->update(['refunded_cents' => 1]);

    $this->artisan('ops:reconcile')->assertFailed()->expectsOutputToContain('refunded_cents is 1');
    expect(Order::query()->count())->toBe(1);
});
