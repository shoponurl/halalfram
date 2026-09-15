<?php

declare(strict_types=1);

use App\Actions\Orders\MarkOrderAuthorized;
use App\Actions\Orders\PlaceOrder;
use App\Actions\Orders\RecordQcCheck;
use App\Actions\Orders\RecordWeight;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Enums\WeightSource;
use App\Models\QcCheck;
use App\Support\Weight;
use Illuminate\Validation\ValidationException;

/*
 * Owner decision (guideline ch. 7, S04): the card is never charged straight off the scale — a QC
 * check (checklist + temperature) gates whether an order moves on to be finalized or goes back for
 * re-cutting.
 */

beforeEach(function () {
    $this->gateway = fakePayments();
    $this->butcher = staff(Role::Butcher);
    $this->product = product(priceCents: 500, estLb: '2.000');

    ['order' => $order] = app(PlaceOrder::class)->handle(
        [$this->product->id => 1],
        ['customer_name' => 'A', 'customer_email' => 'a@example.com', 'customer_phone' => '2675550123'],
        1100,
    );
    $intent = $this->gateway->authorize($order->stripe_payment_intent_id);
    app(MarkOrderAuthorized::class)->handle($order, $intent);
    $this->order = $order->fresh();
});

it('refuses a QC check before every item is weighed', function () {
    expect(fn () => app(RecordQcCheck::class)->handle($this->order, true, [], $this->butcher, '38.0'))
        ->toThrow(ValidationException::class);
});

it('passes an order once every item is weighed, appending an event without editing any prior check', function () {
    app(RecordWeight::class)->handle($this->order->items->first(), Weight::pounds('2.000'), WeightSource::Manual, $this->butcher);

    $check = app(RecordQcCheck::class)->handle($this->order->fresh(), true, ['weight_matches' => true], $this->butcher, '38.0', 'Looks good');

    expect($check->passed)->toBeTrue()
        ->and($this->order->fresh()->status)->toBe(OrderStatus::QcPassed)
        ->and($check->checked_by)->toBe($this->butcher->id);

    expect(fn () => $check->update(['passed' => false]))->toThrow(LogicException::class);
});

it('fails an order back for re-cutting, and still allows weighing again', function () {
    app(RecordWeight::class)->handle($this->order->items->first(), Weight::pounds('2.000'), WeightSource::Manual, $this->butcher);

    app(RecordQcCheck::class)->handle($this->order->fresh(), false, ['weight_matches' => false], $this->butcher, '38.0', 'Bone fragment found');
    $order = $this->order->fresh();

    expect($order->status)->toBe(OrderStatus::QcFailed)
        ->and($this->butcher->can('recordWeight', $order))->toBeTrue()
        ->and(QcCheck::query()->where('order_id', $order->id)->count())->toBe(1);

    // Re-cut, re-weigh, re-check — a fresh event, the failed one untouched
    app(RecordWeight::class)->handle($order->items->first(), Weight::pounds('1.950'), WeightSource::Manual, $this->butcher, 'Re-cut after QC fail');
    $secondCheck = app(RecordQcCheck::class)->handle($order->fresh(), true, ['weight_matches' => true], $this->butcher, '38.0');

    expect($order->fresh()->status)->toBe(OrderStatus::QcPassed)
        ->and(QcCheck::query()->where('order_id', $order->id)->count())->toBe(2)
        ->and(QcCheck::query()->find($secondCheck->id)->passed)->toBeTrue();
});

it('refuses a QC check once the order has already moved past QC', function () {
    app(RecordWeight::class)->handle($this->order->items->first(), Weight::pounds('2.000'), WeightSource::Manual, $this->butcher);
    app(RecordQcCheck::class)->handle($this->order->fresh(), true, [], $this->butcher, '38.0');

    expect(fn () => app(RecordQcCheck::class)->handle($this->order->fresh(), true, [], $this->butcher, '38.0'))
        ->toThrow(ValidationException::class);
});
