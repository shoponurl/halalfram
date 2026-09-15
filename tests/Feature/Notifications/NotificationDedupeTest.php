<?php

declare(strict_types=1);

use App\Actions\Orders\PlaceOrder;
use App\Actions\Orders\QuoteCart;
use App\Actions\Sms\HandleTwilioInboundSms;
use App\Enums\NotificationEvent;
use App\Jobs\DispatchOrderNotification;
use App\Mail\OrderNotificationMail;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\SmsConsent;
use Illuminate\Support\Facades\Mail;

/*
 * Guideline DoD (S06): a retried job or a duplicate trigger sends a message only once. Dedupe is
 * enforced per (order, event, channel) via App\Models\NotificationLog, checked inside a lock —
 * see App\Jobs\DispatchOrderNotification.
 */

beforeEach(function () {
    fakePayments();
});

function orderForNotifications(): Order
{
    $product = product(priceCents: 500, estLb: '2.000');
    $quote = app(QuoteCart::class)->handle([$product->id => 1]);
    ['order' => $order] = app(PlaceOrder::class)->handle(
        lines: [$product->id => 1],
        customer: ['customer_name' => 'A', 'customer_email' => 'a@example.com', 'customer_phone' => '2675550123'],
        expectedHoldCents: $quote['hold_cents'],
    );

    return $order;
}

it('sends SMS and email via active templates and logs one dedupe row per channel', function () {
    $sms = fakeSms();
    Mail::fake();
    notificationTemplate(NotificationEvent::ReadyForPickup, 'sms', 'Order {{order_number}} is ready!');
    notificationTemplate(NotificationEvent::ReadyForPickup, 'email', 'Hi {{customer_name}}, order {{order_number}} is ready.');
    $order = orderForNotifications();

    DispatchOrderNotification::dispatchSync($order->id, NotificationEvent::ReadyForPickup->value);

    expect($sms->sent)->toHaveCount(1)
        ->and($sms->sent[0]['body'])->toContain($order->number);
    Mail::assertSent(OrderNotificationMail::class);
    expect(NotificationLog::query()->where('order_id', $order->id)->count())->toBe(2);
});

it('never sends twice for the same order, event and channel', function () {
    $sms = fakeSms();
    notificationTemplate(NotificationEvent::ReadyForPickup, 'sms');
    $order = orderForNotifications();

    DispatchOrderNotification::dispatchSync($order->id, NotificationEvent::ReadyForPickup->value);
    DispatchOrderNotification::dispatchSync($order->id, NotificationEvent::ReadyForPickup->value);   // simulated retry/duplicate trigger

    expect($sms->sent)->toHaveCount(1)
        ->and(NotificationLog::query()->where('order_id', $order->id)->where('channel', 'sms')->count())->toBe(1);
});

it('skips a channel silently when no active template exists for that event', function () {
    $sms = fakeSms();
    $order = orderForNotifications();

    DispatchOrderNotification::dispatchSync($order->id, NotificationEvent::ReadyForPickup->value);

    expect($sms->sent)->toBeEmpty()
        ->and(NotificationLog::query()->where('order_id', $order->id)->count())->toBe(0);
});

it('blocks SMS to a number that has replied STOP, even for a transactional event', function () {
    $sms = fakeSms();
    notificationTemplate(NotificationEvent::ReadyForPickup, 'sms');
    $order = orderForNotifications();
    SmsConsent::query()->create(['phone' => $order->customer_phone, 'opted_out_at' => now()]);

    DispatchOrderNotification::dispatchSync($order->id, NotificationEvent::ReadyForPickup->value);

    expect($sms->sent)->toBeEmpty();
});

it('handles an inbound STOP by recording it, then unblocks on START', function () {
    app(HandleTwilioInboundSms::class)->handle('+12675550123', 'STOP');
    expect(SmsConsent::query()->find('+12675550123')->canReceiveSms())->toBeFalse();

    app(HandleTwilioInboundSms::class)->handle('+12675550123', 'START');
    expect(SmsConsent::query()->find('+12675550123')->canReceiveSms())->toBeTrue();
});
