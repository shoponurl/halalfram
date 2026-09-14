<?php

declare(strict_types=1);

use App\Actions\Orders\PlaceOrder;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\User;

/*
 * Gap 01 (IDOR): an order must be reachable only by its owner (via the private link token,
 * or a signed-in account) or by staff with orders.view — never by guessing/incrementing the number.
 */

beforeEach(function () {
    fakePayments();
    $this->product = product(priceCents: 500, estLb: '2.000');
});

function placeGuestOrder(object $ctx, ?User $user = null): array
{
    return app(PlaceOrder::class)->handle(
        [$ctx->product->id => 1],
        ['customer_name' => 'Test Buyer', 'customer_email' => 'buyer@example.com', 'customer_phone' => '2675550123'],
        1100,
        $user,
    );
}

it('lets a guest view their own order with the correct token in the URL', function () {
    ['order' => $order, 'token' => $token] = placeGuestOrder($this);

    $this->get(route('orders.show', $order).'?token='.$token)->assertOk();
});

it('refuses a guest with no token, a wrong token, or another order’s token', function () {
    ['order' => $order] = placeGuestOrder($this);
    ['order' => $other, 'token' => $otherToken] = placeGuestOrder($this);

    $this->get(route('orders.show', $order))->assertNotFound();
    $this->get(route('orders.show', $order).'?token=wrong-token-value-000000')->assertNotFound();
    $this->get(route('orders.show', $order).'?token='.$otherToken)->assertNotFound();
});

it('lets a guest back into their order via the session set at checkout, without the token in the URL', function () {
    ['order' => $order, 'token' => $token] = placeGuestOrder($this);

    $this->withSession(["order_tokens.{$order->number}" => $token])
        ->get(route('orders.show', $order))
        ->assertOk();
});

it('lets a signed-in customer view their own order without a token', function () {
    $customer = User::factory()->create();
    ['order' => $order] = placeGuestOrder($this, $customer);

    $this->actingAs($customer)->get(route('orders.show', $order))->assertOk();
});

it('never lets one signed-in customer view another customer’s order', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    ['order' => $order] = placeGuestOrder($this, $owner);

    $this->actingAs($intruder)->get(route('orders.show', $order))->assertNotFound();
});

it('lets staff with orders.view download any invoice, without a token', function () {
    ['order' => $order] = placeGuestOrder($this);
    $order->status = OrderStatus::Completed;
    $order->final_cents = $order->estimated_cents;
    $order->save();

    $this->actingAs(staff(Role::Manager))->get(route('orders.invoice', $order))->assertOk();
});

it('refuses a random guest an invoice without a valid token', function () {
    ['order' => $order] = placeGuestOrder($this);
    $order->status = OrderStatus::Completed;
    $order->final_cents = $order->estimated_cents;
    $order->save();

    $this->get(route('orders.invoice', $order))->assertNotFound();
});
