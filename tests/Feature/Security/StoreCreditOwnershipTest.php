<?php

declare(strict_types=1);

use App\Actions\Orders\QuoteCart;
use App\Http\Controllers\Shop\StoreCreditVerificationController;
use App\Mail\StoreCreditLinkMail;
use App\Models\Order;
use App\Models\StoreCreditAccount;
use Illuminate\Support\Facades\Mail;

/*
 * S09 self-audit SA-01 (High): store credit was looked up and spent by whatever email was typed at
 * checkout, so anyone who knew a customer's email could read their balance (?credit_email=) and
 * spend it. Credit now requires proving the email is yours via a link sent to that inbox.
 */

beforeEach(function () {
    fakePayments();
    Mail::fake();
    $this->product = product(priceCents: 500, estLb: '2.000');
    storeCredit('victim@example.com', 800);
    $this->cart = ['cart.lines' => [$this->product->id => 1]];
    $this->payload = fn (string $email, int $expectedHold) => [
        'customer_name' => 'Someone',
        'customer_email' => $email,
        'customer_phone' => '267-555-0123',
        'agree_catch_weight' => '1',
        'agree_regulatory_notice' => '1',
        'expected_hold_cents' => $expectedHold,
        'fulfilment_method' => 'pickup',
        'payment_method' => 'card',
        'apply_store_credit' => '1',
    ];
});

it('never reveals a balance for an email typed into the URL', function () {
    $this->withSession($this->cart)
        ->get(route('checkout.create', ['credit_email' => 'victim@example.com']))
        ->assertOk()
        ->assertDontSee('$8.00')
        ->assertDontSee('victim@example.com');
});

it('refuses to spend store credit for an email this browser has not verified', function () {
    $hold = app(QuoteCart::class)->handle([$this->product->id => 1])['hold_cents'];

    $this->withSession($this->cart)
        ->post(route('checkout.store'), ($this->payload)('victim@example.com', $hold - 800))
        ->assertSessionHasErrors('apply_store_credit');

    expect(Order::query()->count())->toBe(0)
        ->and(StoreCreditAccount::query()->find('victim@example.com')->balance_cents)->toBe(800);
});

it('refuses credit verified for one email on an order placed under another', function () {
    $hold = app(QuoteCart::class)->handle([$this->product->id => 1])['hold_cents'];

    $this->withSession($this->cart + [StoreCreditVerificationController::SESSION_KEY => 'attacker@example.com'])
        ->post(route('checkout.store'), ($this->payload)('victim@example.com', $hold - 800))
        ->assertSessionHasErrors('apply_store_credit');

    expect(StoreCreditAccount::query()->find('victim@example.com')->balance_cents)->toBe(800);
});

it('lets the real owner verify by email link, then see and spend their credit', function () {
    $this->withSession($this->cart)
        ->post(route('store-credit.send'), ['credit_email' => 'Victim@Example.com'])
        ->assertRedirect(route('checkout.create'));

    $link = null;
    Mail::assertQueued(StoreCreditLinkMail::class, function (StoreCreditLinkMail $mail) use (&$link) {
        $link = $mail->url;

        return $mail->hasTo('victim@example.com');
    });

    $this->get((string) $link)->assertRedirect(route('checkout.create'))->assertSessionHas(StoreCreditVerificationController::SESSION_KEY, 'victim@example.com');
    $this->withSession($this->cart)->get(route('checkout.create'))->assertSee('$8.00 available');

    $hold = app(QuoteCart::class)->handle([$this->product->id => 1])['hold_cents'];
    $this->withSession($this->cart)->post(route('checkout.store'), ($this->payload)('victim@example.com', $hold - 800))->assertSessionHasNoErrors();

    expect(Order::query()->sole()->store_credit_applied_cents)->toBe(800)
        ->and(StoreCreditAccount::query()->find('victim@example.com')->balance_cents)->toBe(0);
});

it('answers identically whether or not an email holds credit, and only emails real accounts', function () {
    $withCredit = $this->post(route('store-credit.send'), ['credit_email' => 'victim@example.com']);
    $withoutCredit = $this->post(route('store-credit.send'), ['credit_email' => 'nobody@example.com']);

    expect($withCredit->getSession()->get('status'))->toBe($withoutCredit->getSession()->get('status'));
    Mail::assertQueued(StoreCreditLinkMail::class, 1);
});

it('rejects an unknown or expired link', function () {
    $this->get(route('store-credit.confirm', 'not-a-real-token'))
        ->assertRedirect(route('checkout.create'))
        ->assertSessionMissing(StoreCreditVerificationController::SESSION_KEY);

    $this->post(route('store-credit.send'), ['credit_email' => 'victim@example.com']);
    $link = null;
    Mail::assertQueued(StoreCreditLinkMail::class, function (StoreCreditLinkMail $mail) use (&$link) {
        $link = $mail->url;

        return true;
    });

    $this->travel(31)->minutes();

    $this->get((string) $link)->assertSessionMissing(StoreCreditVerificationController::SESSION_KEY);
});
