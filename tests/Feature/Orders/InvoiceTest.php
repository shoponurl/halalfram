<?php

declare(strict_types=1);

use App\Actions\Orders\FinalizeOrder;
use App\Actions\Orders\IssueInvoice;
use App\Actions\Orders\MarkOrderAuthorized;
use App\Actions\Orders\PlaceOrder;
use App\Actions\Orders\RecordQcCheck;
use App\Actions\Orders\RecordWeight;
use App\Enums\Role;
use App\Enums\WeightSource;
use App\Models\Invoice;
use App\Models\Order;
use App\Support\Weight;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->gateway = fakePayments();
    $this->butcher = staff(Role::Butcher);
    $this->frontDesk = staff(Role::FrontDesk);
    $this->product = product(priceCents: 500, estLb: '2.000');

    ['order' => $order] = app(PlaceOrder::class)->handle(
        [$this->product->id => 1],
        ['customer_name' => 'Test Buyer', 'customer_email' => 'buyer@example.com', 'customer_phone' => '2675550123'],
        1100,
    );
    app(MarkOrderAuthorized::class)->handle($order, $this->gateway->authorize($order->stripe_payment_intent_id));
    app(RecordWeight::class)->handle($order->items->first(), Weight::pounds('1.900'), WeightSource::Manual, $this->butcher);
    app(RecordQcCheck::class)->handle($order->fresh(), true, ['weight_matches' => true], $this->butcher, '38.0');
    app(FinalizeOrder::class)->handle($order->fresh(), $this->frontDesk);
    $this->order = $order->fresh();
});

it('refuses an invoice before the order is charged', function () {
    ['order' => $unpaid] = app(PlaceOrder::class)->handle(
        [$this->product->id => 1],
        ['customer_name' => 'B', 'customer_email' => 'b@example.com', 'customer_phone' => '2675550123'],
        1100,
    );

    expect(fn () => app(IssueInvoice::class)->handle($unpaid))->toThrow(ValidationException::class);
});

it('assigns exactly one invoice number to a settled order, even if issued twice', function () {
    $first = app(IssueInvoice::class)->handle($this->order);
    $second = app(IssueInvoice::class)->handle($this->order);

    expect($first->id)->toBe($second->id)
        ->and($first->number)->toStartWith('INV-')
        ->and(Invoice::query()->where('order_id', $this->order->id)->count())->toBe(1);
});

it('downloads a PDF invoice for the order owner', function () {
    $response = $this->withSession(["order_tokens.{$this->order->number}" => session_token_for($this->order)])
        ->get(route('orders.invoice', $this->order));

    $response->assertOk()->assertHeader('content-type', 'application/pdf');
});

/** Re-derives the private token the customer would have from checkout, for a test that needs it directly. */
function session_token_for(Order $order): string
{
    // The real token is only known to the customer; tests reconstruct the session the same way checkout does.
    $token = Str::random(48);
    $order->public_token_hash = hash('sha256', $token);
    $order->saveQuietly();

    return $token;
}
