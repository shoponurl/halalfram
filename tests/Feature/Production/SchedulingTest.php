<?php

declare(strict_types=1);

use App\Actions\Orders\PlaceOrder;
use App\Actions\Orders\QuoteCart;
use App\Actions\Production\ScheduleOrder;
use App\Models\Order;
use App\Models\ProductionDay;
use App\Support\CartLine;
use Illuminate\Validation\ValidationException;

/*
 * Owner decisions (guideline ch. 7, S04): capacity is measured in butcher-minutes, orders placed
 * after the daily cutoff queue for the next day, and a day is never booked past what the card
 * authorization window (7 days) can cover.
 */

beforeEach(function () {
    fakePayments();
    $this->travelTo(now()->setTime(10, 0));   // well before the 15:00 default cutoff
});

it('schedules for today when placed before the cutoff', function () {
    $date = app(ScheduleOrder::class)->handle(30);

    expect($date->toDateString())->toBe(today()->toDateString());
});

it('schedules for tomorrow when placed after the cutoff', function () {
    $this->travelTo(now()->setTime(16, 0));

    $date = app(ScheduleOrder::class)->handle(30);

    expect($date->toDateString())->toBe(today()->addDay()->toDateString());
});

it('rolls over to the next day once today\'s capacity is used up', function () {
    ProductionDay::query()->create(['date' => today()->toDateString(), 'capacity_minutes' => 50]);
    $category = category('Goat');
    $product = product(priceCents: 500, estLb: '2.000', category: $category);
    $cut = cutOption($category, 'Boneless', estimatedMinutes: 40);
    $line = new CartLine($product->id, 1, $cut->id);
    $rawLines = [$line->key() => $line->toArray()];
    $quote = app(QuoteCart::class)->handle($rawLines);

    // Books 40 of today's 50 minutes
    ['order' => $first] = app(PlaceOrder::class)->handle(
        lines: $rawLines,
        customer: ['customer_name' => 'A', 'customer_email' => 'a@example.com', 'customer_phone' => '2675550123'],
        expectedHoldCents: $quote['hold_cents'],
    );
    expect($first->scheduled_date->toDateString())->toBe(today()->toDateString());

    // Only 10 minutes left today; a 20-minute order must roll to tomorrow
    $second = app(ScheduleOrder::class)->handle(20);
    expect($second->toDateString())->toBe(today()->addDay()->toDateString());
});

it('skips a day marked closed entirely', function () {
    ProductionDay::query()->create(['date' => today()->toDateString(), 'is_open' => false]);

    $date = app(ScheduleOrder::class)->handle(10);

    expect($date->toDateString())->toBe(today()->addDay()->toDateString());
});

it('rejects an order that needs more capacity than the whole authorization window has left', function () {
    // Close every day in the window with an override so nothing is ever available
    for ($i = 0; $i <= (int) config('catchweight.authorization_fallback_days'); $i++) {
        ProductionDay::query()->create(['date' => today()->addDays($i)->toDateString(), 'is_open' => false]);
    }

    expect(fn () => app(ScheduleOrder::class)->handle(10))->toThrow(ValidationException::class);
});

it('actually blocks checkout — not just the scheduler in isolation — when capacity runs out', function () {
    for ($i = 0; $i <= (int) config('catchweight.authorization_fallback_days'); $i++) {
        ProductionDay::query()->create(['date' => today()->addDays($i)->toDateString(), 'is_open' => false]);
    }
    $product = product();
    $quote = app(QuoteCart::class)->handle([$product->id => 1]);

    expect(fn () => app(PlaceOrder::class)->handle(
        lines: [$product->id => 1],
        customer: ['customer_name' => 'A', 'customer_email' => 'a@example.com', 'customer_phone' => '2675550123'],
        expectedHoldCents: $quote['hold_cents'],
    ))->toThrow(ValidationException::class);

    expect(Order::query()->count())->toBe(0);
});

it('stores the scheduled date on the order, sized to its actual butcher-minutes', function () {
    $category = category('Goat');
    $product = product(priceCents: 500, estLb: '2.000', category: $category);
    $cut = cutOption($category, 'Boneless', estimatedMinutes: 45);
    $line = new CartLine($product->id, 1, $cut->id);
    $rawLines = [$line->key() => $line->toArray()];
    $quote = app(QuoteCart::class)->handle($rawLines);

    ['order' => $order] = app(PlaceOrder::class)->handle(
        lines: $rawLines,
        customer: ['customer_name' => 'A', 'customer_email' => 'a@example.com', 'customer_phone' => '2675550123'],
        expectedHoldCents: $quote['hold_cents'],
    );

    expect($order->scheduled_date->toDateString())->toBe(today()->toDateString())
        ->and($order->items->first()->estimated_minutes)->toBe(45)
        ->and($order->totalProcessingMinutes())->toBe(45);
});
