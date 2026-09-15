<?php

declare(strict_types=1);

use App\Actions\Orders\FinalizeOrder;
use App\Actions\Orders\MarkOrderAuthorized;
use App\Actions\Orders\PlaceOrder;
use App\Actions\Orders\QuoteCart;
use App\Actions\Orders\RecordQcCheck;
use App\Actions\Orders\RecordWeight;
use App\Actions\Shipping\RecordTrackingUpdate;
use App\Enums\FulfilmentStatus;
use App\Enums\NotificationEvent;
use App\Enums\Role;
use App\Enums\WeightSource;
use App\Models\Order;
use App\Shipping\Data\TrackingUpdate;
use App\Support\Weight;
use Illuminate\Support\Carbon;

/** Guideline ch. 6, S08: EasyPost's tracking webhook marks a shipped order delivered. */
beforeEach(function () {
    $this->gateway = fakePayments();
    $this->shipping = fakeShipping();
    packingRule();
    $this->manager = staff(Role::Manager);

    test()->travelTo(Carbon::parse('2026-09-15 10:00:00'));   // a Tuesday — a valid ship day
    $butcher = staff(Role::Butcher);
    $frontDesk = staff(Role::FrontDesk);
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
    app(FinalizeOrder::class)->handle($order->fresh(), $frontDesk);   // settles and buys the label -> Shipped

    $this->order = $order->fresh();
});

it('marks a shipped order delivered when tracking says so', function () {
    notificationTemplate(NotificationEvent::Delivered, 'sms');
    $sms = fakeSms();

    app(RecordTrackingUpdate::class)->handle(new TrackingUpdate((string) $this->order->easypost_shipment_id, 'delivered'));

    expect($this->order->fresh()->fulfilment_status)->toBe(FulfilmentStatus::Delivered)
        ->and($sms->sent)->toHaveCount(1);
});

it('records a carrier delivery exception without changing the order\'s own status', function () {
    app(RecordTrackingUpdate::class)->handle(new TrackingUpdate((string) $this->order->easypost_shipment_id, 'failure'));

    expect($this->order->fresh()->fulfilment_status)->toBe(FulfilmentStatus::Shipped)
        ->and($this->order->fresh()->deliveryEvents()->count())->toBeGreaterThan(0);
});

it('ignores a tracking update for an unknown shipment', function () {
    $before = $this->order->fresh()->fulfilment_status;

    app(RecordTrackingUpdate::class)->handle(new TrackingUpdate('shp_unknown', 'delivered'));

    expect($this->order->fresh()->fulfilment_status)->toBe($before)
        ->and(Order::query()->count())->toBe(1);
});
