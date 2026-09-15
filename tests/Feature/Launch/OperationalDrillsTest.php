<?php

declare(strict_types=1);

use App\Actions\Orders\PlaceOrder;
use App\Enums\LaunchGateItem;
use App\Mail\AlertMail;
use App\Models\LaunchGateEvidence;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/*
 * Guideline ch. 8 launch gate: alerts reach a person, the recall drill runs within 30 seconds, and the
 * load test records its result as evidence.
 */

it('emails a critical alert to the alert inbox, once per identical message', function () {
    Mail::fake();
    config(['launch.alerts.email' => 'owner@shop.test', 'logging.channels.slack.url' => null]);

    Log::channel('alerts')->critical('Order settlement failed after retries — needs manual attention', ['order_id' => 7]);
    Log::channel('alerts')->critical('Order settlement failed after retries — needs manual attention', ['order_id' => 7]);
    Log::channel('alerts')->warning('Just a warning');

    Mail::assertSent(AlertMail::class, 1);
    Mail::assertSent(AlertMail::class, fn (AlertMail $mail) => $mail->hasTo('owner@shop.test') && str_contains($mail->contextJson, '"order_id": 7'));
});

it('never lets a broken mail setup break the code that raised the alert', function () {
    config(['launch.alerts.email' => 'owner@shop.test', 'mail.default' => 'does-not-exist']);

    Log::channel('alerts')->critical('Something bad');

    expect(true)->toBeTrue();
});

it('times a recall of a lot to every order it touched, and records it', function () {
    fakePayments();
    $product = product(priceCents: 500, estLb: '2.000');
    $lot = lot($product, onHandLb: '10.000');
    ['order' => $order] = app(PlaceOrder::class)->handle(
        lines: [$product->id => 1],
        customer: ['customer_name' => 'A', 'customer_email' => 'a@example.com', 'customer_phone' => '2675550123'],
        expectedHoldCents: 1100,
    );

    $this->artisan('ops:recall-drill', ['lot' => $lot->lot_number])
        ->assertSuccessful()
        ->expectsOutputToContain((string) $order->number);

    $evidence = LaunchGateEvidence::query()->sole();
    expect($evidence->item)->toBe(LaunchGateItem::RecallDrill)
        ->and($evidence->passed)->toBeTrue()
        ->and($evidence->evidence)->toContain('1 order(s)');
});

it('records a passing load test and a failing one', function () {
    Http::fake(['staging.test/*' => Http::response('ok')]);

    $this->artisan('ops:load-test', ['base-url' => 'https://staging.test', '--requests' => 40, '--concurrency' => 10, '--normal-rps' => 1, '--path' => ['/', '/cart']])
        ->assertSuccessful();

    Http::fake(['loadtest-down.example/*' => Http::response('down', 500)]);
    $this->artisan('ops:load-test', ['base-url' => 'https://loadtest-down.example', '--requests' => 20, '--concurrency' => 10, '--normal-rps' => 1, '--path' => ['/']])
        ->assertFailed();

    expect(LaunchGateEvidence::query()->orderBy('id')->pluck('passed')->all())->toBe([true, false]);
});

it('refuses the oversell drill on production', function () {
    config(['app.env' => 'production']);
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('ops:oversell-drill')->assertFailed();
});
