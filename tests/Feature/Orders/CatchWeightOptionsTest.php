<?php

declare(strict_types=1);

use App\Actions\Orders\FinalizeOrder;
use App\Actions\Orders\MarkOrderAuthorized;
use App\Actions\Orders\PlaceOrder;
use App\Actions\Orders\QuoteCart;
use App\Actions\Orders\RecordQcCheck;
use App\Actions\Orders\RecordWeight;
use App\Enums\Role;
use App\Enums\WeightSource;
use App\Support\CartLine;
use App\Support\SettlementPlan;
use App\Support\Weight;
use Illuminate\Validation\ValidationException;

/*
 * Sprint 02 (guideline ch. 6): cut/offal/packing options fold into the catch-weight price and are
 * capped so processing time never risks outliving the card hold (owner decision, ch. 7 S02).
 */

beforeEach(function () {
    $this->gateway = fakePayments();
    $this->category = category('Goat');
    $this->product = product(priceCents: 899, estLb: '2.000', category: $this->category);   // 899 × 2 = 1798/piece
});

it('adds cut, offal and packing surcharges to the catch-weight price', function () {
    $cut = cutOption($this->category, 'Boneless', extraPriceCents: 400);
    $offal = offalOption($this->category, 'Separate pack', extraPriceCents: 300);
    $packing = packingOption('Vacuum pack', surchargeCents: 150);

    $line = new CartLine($this->product->id, 1, $cut->id, $offal->id, $packing->id);
    $quote = app(QuoteCart::class)->handle([$line->key() => $line->toArray()]);

    // base 1798 + cut 400 + offal 300 + packing 150 = 2648
    expect($quote['estimated_cents'])->toBe(2648)
        ->and($quote['lines'][0]['cut_option']->id)->toBe($cut->id)
        ->and($quote['lines'][0]['offal_option']->id)->toBe($offal->id)
        ->and($quote['lines'][0]['packing_option']->id)->toBe($packing->id);
});

it('multiplies the per-piece option surcharge by quantity, same as the base price', function () {
    $cut = cutOption($this->category, 'Boneless', extraPriceCents: 400);
    $line = new CartLine($this->product->id, 3, $cut->id);

    $quote = app(QuoteCart::class)->handle([$line->key() => $line->toArray()]);

    // (1798 + 400) × 3 = 6594
    expect($quote['estimated_cents'])->toBe(6594);
});

it('snapshots the chosen option names and prices onto the order item, not just their ids', function () {
    $cut = cutOption($this->category, 'Boneless', extraPriceCents: 400, extraLeadTimeDays: 1);
    $line = new CartLine($this->product->id, 1, $cut->id);
    $rawLines = [$line->key() => $line->toArray()];
    $quote = app(QuoteCart::class)->handle($rawLines);

    ['order' => $order] = app(PlaceOrder::class)->handle(
        lines: $rawLines,
        customer: ['customer_name' => 'A', 'customer_email' => 'a@example.com', 'customer_phone' => '2675550123'],
        expectedHoldCents: $quote['hold_cents'],
    );

    $item = $order->items->first();
    expect($item->cut_option_id)->toBe($cut->id)
        ->and($item->cut_option_name)->toBe('Boneless')
        ->and($item->cut_option_price_cents)->toBe(400)
        ->and($item->lead_time_days)->toBe(1)
        ->and($order->lead_time_days)->toBe(1);

    // The option is later renamed/repriced — the placed order must never drift (guideline: snapshot policy).
    $cut->name = 'Boneless (renamed)';
    $cut->extra_price_cents = 999;
    $cut->save();

    expect($item->fresh()->cut_option_name)->toBe('Boneless')
        ->and($item->fresh()->cut_option_price_cents)->toBe(400);
});

it('rejects a cut/packing combination that needs more lead time than the hold can cover (owner decision S02)', function () {
    $cut = cutOption($this->category, 'Boneless', extraLeadTimeDays: 3);
    $packing = packingOption('1 lb portions', extraLeadTimeDays: (int) config('catchweight.max_lead_time_days'));
    $line = new CartLine($this->product->id, 1, $cut->id, null, $packing->id);

    expect(fn () => app(QuoteCart::class)->handle([$line->key() => $line->toArray()]))
        ->toThrow(ValidationException::class);
});

it('rejects a cut option that belongs to a different category than the product', function () {
    $otherCategory = category('Beef');
    $wrongCut = cutOption($otherCategory, 'Steaks');
    $line = new CartLine($this->product->id, 1, $wrongCut->id);

    expect(fn () => app(QuoteCart::class)->handle([$line->key() => $line->toArray()]))
        ->toThrow(ValidationException::class);
});

it('rejects cut/offal options on a product whose category does not offer them', function () {
    $plainCategory = category('Processed', supportsCustomCuts: false);
    $plainProduct = product(name: 'Sausage', category: $plainCategory);
    // A cut option row that DOES belong to the same (non-cuttable) category — the category match
    // passes, so this isolates the "category doesn't offer cut/offal options" rule specifically.
    $cut = cutOption($plainCategory, 'Boneless');

    $line = new CartLine($plainProduct->id, 1, $cut->id);
    expect(fn () => app(QuoteCart::class)->handle([$line->key() => $line->toArray()]))->toThrow(ValidationException::class);
});

it('charges the option surcharge on the actual weight, not just the estimate (regression: it must not be dropped at settlement)', function () {
    $cut = cutOption($this->category, 'Boneless', extraPriceCents: 400);
    $line = new CartLine($this->product->id, 1, $cut->id);
    $rawLines = [$line->key() => $line->toArray()];
    $quote = app(QuoteCart::class)->handle($rawLines);

    ['order' => $order] = app(PlaceOrder::class)->handle(
        lines: $rawLines,
        customer: ['customer_name' => 'A', 'customer_email' => 'a@example.com', 'customer_phone' => '2675550123'],
        expectedHoldCents: $quote['hold_cents'],
    );
    $intent = $this->gateway->authorize($order->stripe_payment_intent_id);
    app(MarkOrderAuthorized::class)->handle($order, $intent);
    $order = $order->fresh();

    // Weighed at exactly the estimate, so only the surcharge should distinguish final from base price
    $inspector = staff(Role::Butcher);
    app(RecordWeight::class)->handle($order->items->first(), Weight::pounds('2.000'), WeightSource::Manual, $inspector);
    $order = $order->fresh(['items']);

    // base 1798 + cut 400 = 2198
    expect($order->items->first()->final_cents)->toBe(2198);

    app(RecordQcCheck::class)->handle($order, true, ['weight_matches' => true], $inspector, '38.0');
    $plan = app(FinalizeOrder::class)->handle($order->fresh(), staff(Role::FrontDesk));
    expect($plan->action)->toBe(SettlementPlan::CAPTURE)
        ->and($plan->finalCents)->toBe(2198);
});

it('keeps Sprint 01 simple carts (no options) working exactly as before', function () {
    $quote = app(QuoteCart::class)->handle([$this->product->id => 1]);

    expect($quote['estimated_cents'])->toBe(1798)
        ->and($quote['lines'][0]['cut_option'])->toBeNull()
        ->and($quote['lead_time_days'])->toBe(0);
});
