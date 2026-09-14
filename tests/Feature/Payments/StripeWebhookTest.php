<?php

declare(strict_types=1);

use App\Actions\Orders\MarkOrderAuthorized;
use App\Actions\Orders\PlaceOrder;
use App\Enums\OrderStatus;
use App\Models\PaymentTransaction;
use App\Payments\FakePaymentGateway;
use App\Payments\PaymentGateway;
use App\Payments\StripeGateway;
use Illuminate\Testing\TestResponse;

/*
 * Gap 04 (webhook signature) and rule 08 (external calls / idempotent processing).
 * The webhook route is exempt from CSRF (bootstrap/app.php) but must never skip signature checks.
 */

beforeEach(function () {
    $this->gateway = fakePayments();
    $this->product = product(priceCents: 500, estLb: '2.000');
    ['order' => $this->order] = app(PlaceOrder::class)->handle(
        [$this->product->id => 1],
        ['customer_name' => 'Test Buyer', 'customer_email' => 'buyer@example.com', 'customer_phone' => '2675550123'],
        1100,
    );
});

function stripeEvent(string $id, string $type, array $object): string
{
    return json_encode(['id' => $id, 'type' => $type, 'data' => ['object' => $object]], JSON_THROW_ON_ERROR);
}

/** Posts a raw webhook body with the given Stripe-Signature header value. */
function postStripeWebhook(string $payload, string $signature): TestResponse
{
    return test()->call('POST', '/stripe/webhook', server: ['HTTP_STRIPE_SIGNATURE' => $signature], content: $payload);
}

it('returns 503, not a stack trace, when Stripe isn’t configured', function () {
    app()->instance(PaymentGateway::class, new StripeGateway(null, null, null, 'usd'));

    $this->postJson('/stripe/webhook', [])->assertStatus(503);
});

it('rejects a webhook whose signature does not match its body', function () {
    $payload = stripeEvent('evt_1', 'payment_intent.amount_capturable_updated', [
        'id' => $this->order->stripe_payment_intent_id,
        'metadata' => ['order_uuid' => $this->order->uuid, 'purpose' => 'hold'],
    ]);

    postStripeWebhook($payload, 'not-a-real-signature')->assertStatus(400);
    // Signed for different bytes than were sent
    postStripeWebhook($payload, FakePaymentGateway::sign($payload.'tampered'))->assertStatus(400);

    expect($this->order->fresh()->status)->toBe(OrderStatus::PendingPayment);
});

it('authorizes the order once the hold is placed, via a correctly signed webhook', function () {
    $this->gateway->authorize($this->order->stripe_payment_intent_id);
    $payload = stripeEvent('evt_authorized', 'payment_intent.amount_capturable_updated', [
        'id' => $this->order->stripe_payment_intent_id,
        'metadata' => ['order_uuid' => $this->order->uuid, 'order_number' => $this->order->number, 'purpose' => 'hold'],
    ]);

    postStripeWebhook($payload, FakePaymentGateway::sign($payload))
        ->assertOk()
        ->assertJson(['result' => 'authorized']);

    expect($this->order->fresh()->status)->toBe(OrderStatus::Authorized);
});

it('processes each Stripe event exactly once, even if Stripe retries delivery', function () {
    $this->gateway->authorize($this->order->stripe_payment_intent_id);
    $payload = stripeEvent('evt_dup', 'payment_intent.amount_capturable_updated', [
        'id' => $this->order->stripe_payment_intent_id,
        'metadata' => ['order_uuid' => $this->order->uuid, 'order_number' => $this->order->number, 'purpose' => 'hold'],
    ]);
    $signature = FakePaymentGateway::sign($payload);

    postStripeWebhook($payload, $signature)->assertJson(['result' => 'authorized']);
    postStripeWebhook($payload, $signature)->assertJson(['result' => 'duplicate']);

    expect(PaymentTransaction::query()->where('order_id', $this->order->id)->where('status', 'succeeded')->count())->toBe(1);
});

it('ignores an event for an order it cannot find, without error', function () {
    $payload = stripeEvent('evt_unknown', 'payment_intent.amount_capturable_updated', ['id' => 'pi_none', 'metadata' => ['order_uuid' => 'not-a-real-uuid', 'purpose' => 'hold']]);

    postStripeWebhook($payload, FakePaymentGateway::sign($payload))
        ->assertOk()
        ->assertJson(['result' => 'ignored']);
});

it('expires the authorization when Stripe cancels an uncaptured hold', function () {
    $intent = $this->gateway->authorize($this->order->stripe_payment_intent_id);
    app(MarkOrderAuthorized::class)->handle($this->order, $intent);

    $payload = stripeEvent('evt_canceled', 'payment_intent.canceled', [
        'id' => $this->order->stripe_payment_intent_id,
        'metadata' => ['order_uuid' => $this->order->uuid, 'purpose' => 'hold'],
    ]);

    postStripeWebhook($payload, FakePaymentGateway::sign($payload))->assertJson(['result' => 'authorization_expired']);

    expect($this->order->fresh()->status)->toBe(OrderStatus::AuthorizationExpired);
});
