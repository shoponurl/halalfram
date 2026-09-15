<?php

declare(strict_types=1);

use App\Actions\Inventory\ReceiveStock;
use App\Actions\Inventory\RecordWastage;
use App\Actions\Orders\FinalizeOrder;
use App\Actions\Orders\MarkOrderAuthorized;
use App\Actions\Orders\PlaceOrder;
use App\Actions\Orders\QuoteCart;
use App\Actions\Orders\RecordQcCheck;
use App\Actions\Orders\RecordWeight;
use App\Actions\Payments\HandleStripeWebhook;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Enums\StockMovementType;
use App\Enums\StorageLocation;
use App\Enums\WeightSource;
use App\Models\Order;
use App\Models\StockMovement;
use App\Payments\Data\WebhookEvent;
use App\Support\CartLine;
use App\Support\Weight;
use Illuminate\Validation\ValidationException;

/*
 * Sprint 03 (guideline ch. 6): reserved vs available stock, FEFO, and the owner's S03 policy —
 * stock is deducted by raw weight including trim loss, and a reservation holds for the full order
 * lifecycle (released only on failure/expiry, converted to a consumption at finalization).
 * A product with no lots stays untracked, so Sprint 01/02 checkout is unaffected.
 */

beforeEach(function () {
    $this->gateway = fakePayments();
    $this->category = category('Goat');
    $this->product = product(priceCents: 500, estLb: '2.000', category: $this->category);   // 500 × 2 = 1000/piece
});

it('reserves from the lot with the earliest use-by date (FEFO)', function () {
    $farLot = lot($this->product, onHandLb: '50.000', useByDaysFromNow: 10);
    $nearLot = lot($this->product, onHandLb: '50.000', useByDaysFromNow: 2);

    $quote = app(QuoteCart::class)->handle([$this->product->id => 1]);
    ['order' => $order] = app(PlaceOrder::class)->handle(
        lines: [$this->product->id => 1],
        customer: ['customer_name' => 'A', 'customer_email' => 'a@example.com', 'customer_phone' => '2675550123'],
        expectedHoldCents: $quote['hold_cents'],
    );

    $item = $order->items->first();
    expect($item->lot_id)->toBe($nearLot->id)
        ->and($item->reserved_raw_weight_lb->toDecimal())->toBe('2.000')
        ->and($nearLot->fresh()->reserved_weight_lb->toDecimal())->toBe('2.000')
        ->and($farLot->fresh()->reserved_weight_lb->toDecimal())->toBe('0.000');
});

it('reserves raw weight including trim loss from the cut option yield % (owner decision S03)', function () {
    lot($this->product, onHandLb: '50.000');
    // 70% yield: 2 lb finished needs 2 / 0.70 = 2.857 lb raw
    $cut = cutOption($this->category, 'Boneless', rawYieldPct: '70.00');

    $line = new CartLine($this->product->id, 1, $cut->id);
    $rawLines = [$line->key() => $line->toArray()];
    $quote = app(QuoteCart::class)->handle($rawLines);
    ['order' => $order] = app(PlaceOrder::class)->handle(
        lines: $rawLines,
        customer: ['customer_name' => 'A', 'customer_email' => 'a@example.com', 'customer_phone' => '2675550123'],
        expectedHoldCents: $quote['hold_cents'],
    );

    expect($order->items->first()->reserved_raw_weight_lb->toDecimal())->toBe('2.857');
});

it('rejects checkout when no lot has enough available stock, and creates nothing', function () {
    lot($this->product, onHandLb: '1.000');   // needs 2.000

    $quote = app(QuoteCart::class)->handle([$this->product->id => 1]);
    expect(fn () => app(PlaceOrder::class)->handle(
        lines: [$this->product->id => 1],
        customer: ['customer_name' => 'A', 'customer_email' => 'a@example.com', 'customer_phone' => '2675550123'],
        expectedHoldCents: $quote['hold_cents'],
    ))->toThrow(ValidationException::class);

    expect(Order::query()->count())->toBe(0);
});

it('leaves a lot with no matching product untouched and a product with no lots fully untracked', function () {
    // No lot at all for this product — Sprint 01/02 checkout, unaffected by Sprint 03.
    $quote = app(QuoteCart::class)->handle([$this->product->id => 1]);
    ['order' => $order] = app(PlaceOrder::class)->handle(
        lines: [$this->product->id => 1],
        customer: ['customer_name' => 'A', 'customer_email' => 'a@example.com', 'customer_phone' => '2675550123'],
        expectedHoldCents: $quote['hold_cents'],
    );

    expect($order->items->first()->lot_id)->toBeNull();
});

it('converts the reservation into a consumption once weights are locked, releasing the estimate and deducting the actual raw weight', function () {
    $stockedLot = lot($this->product, onHandLb: '50.000');
    $quote = app(QuoteCart::class)->handle([$this->product->id => 1]);
    ['order' => $order] = app(PlaceOrder::class)->handle(
        lines: [$this->product->id => 1],
        customer: ['customer_name' => 'A', 'customer_email' => 'a@example.com', 'customer_phone' => '2675550123'],
        expectedHoldCents: $quote['hold_cents'],
    );
    $intent = $this->gateway->authorize($order->stripe_payment_intent_id);
    app(MarkOrderAuthorized::class)->handle($order, $intent);
    $order = $order->fresh();

    // Weighed heavier than the 2.000 lb estimate
    $inspector = staff(Role::Butcher);
    app(RecordWeight::class)->handle($order->items->first(), Weight::pounds('2.400'), WeightSource::Manual, $inspector);
    app(RecordQcCheck::class)->handle($order->fresh(), true, ['weight_matches' => true], $inspector, '38.0');
    app(FinalizeOrder::class)->handle($order->fresh(['items']), staff(Role::FrontDesk));

    $lot = $stockedLot->fresh();
    $item = $order->fresh(['items'])->items->first();
    expect($lot->reserved_weight_lb->toDecimal())->toBe('0.000')
        ->and($lot->on_hand_weight_lb->toDecimal())->toBe('47.600')   // 50 - 2.4
        ->and($item->consumed_raw_weight_lb->toDecimal())->toBe('2.400')
        ->and(StockMovement::query()->where('lot_id', $lot->id)->pluck('type')->map(fn (StockMovementType $t) => $t->value)->sort()->values()->all())
        ->toBe(['consumed', 'released', 'reserved']);
});

it('releases the reservation when the card hold fails, and marks the order failed', function () {
    $stockedLot = lot($this->product, onHandLb: '50.000');
    $this->gateway->declineHold = true;

    $quote = app(QuoteCart::class)->handle([$this->product->id => 1]);
    expect(fn () => app(PlaceOrder::class)->handle(
        lines: [$this->product->id => 1],
        customer: ['customer_name' => 'A', 'customer_email' => 'a@example.com', 'customer_phone' => '2675550123'],
        expectedHoldCents: $quote['hold_cents'],
    ))->toThrow(RuntimeException::class);

    $order = Order::query()->sole();
    expect($order->status)->toBe(OrderStatus::PaymentFailed)
        ->and($stockedLot->fresh()->reserved_weight_lb->toDecimal())->toBe('0.000');
});

it('releases the reservation when the card authorization expires', function () {
    $stockedLot = lot($this->product, onHandLb: '50.000');
    $quote = app(QuoteCart::class)->handle([$this->product->id => 1]);
    ['order' => $order] = app(PlaceOrder::class)->handle(
        lines: [$this->product->id => 1],
        customer: ['customer_name' => 'A', 'customer_email' => 'a@example.com', 'customer_phone' => '2675550123'],
        expectedHoldCents: $quote['hold_cents'],
    );
    $intent = $this->gateway->authorize($order->stripe_payment_intent_id);
    app(MarkOrderAuthorized::class)->handle($order, $intent);

    $event = new WebhookEvent('evt_canceled', 'payment_intent.canceled', [
        'id' => $order->stripe_payment_intent_id,
        'metadata' => ['order_uuid' => $order->uuid, 'purpose' => 'hold'],
    ]);
    app(HandleStripeWebhook::class)->handle($event);

    expect($order->fresh()->status)->toBe(OrderStatus::AuthorizationExpired)
        ->and($stockedLot->fresh()->reserved_weight_lb->toDecimal())->toBe('0.000');
});

it('lets staff record wastage against a lot, and rejects wasting more than is on hand', function () {
    $stockedLot = lot($this->product, onHandLb: '10.000');
    $manager = staff(Role::Manager);

    app(RecordWastage::class)->handle($stockedLot, Weight::pounds('3.000'), $manager, 'Freezer power outage');
    expect($stockedLot->fresh()->on_hand_weight_lb->toDecimal())->toBe('7.000');

    expect(fn () => app(RecordWastage::class)->handle($stockedLot->fresh(), Weight::pounds('100.000'), $manager, 'Too much'))
        ->toThrow(ValidationException::class);
});

it('assigns a lot number and logs the starting weight as a received movement (rule 04)', function () {
    $cow = animal();
    $received = app(ReceiveStock::class)->handle(
        product: $this->product,
        animal: $cow,
        storageLocation: StorageLocation::Freezer,
        packDate: now(),
        useByDate: now()->addDays(30),
        weight: Weight::pounds('40.000'),
        receivedBy: staff(Role::Butcher),
    );

    expect($received->lot_number)->toStartWith('LOT-')
        ->and($received->on_hand_weight_lb->toDecimal())->toBe('40.000')
        ->and($received->animal_id)->toBe($cow->id)
        ->and(StockMovement::query()->where('lot_id', $received->id)->where('type', StockMovementType::Received)->exists())->toBeTrue();
});

it('reconciles an animal\'s dressing loss as the gap between live and dressed weight', function () {
    $cow = animal('F200', liveLb: '150.000', dressedLb: '92.500');

    expect($cow->dressingLoss()->toDecimal())->toBe('57.500');
});
