<?php

declare(strict_types=1);

use App\Actions\Orders\FinalizeOrder;
use App\Actions\Orders\PlaceOrder;
use App\Actions\Orders\QuoteCart;
use App\Actions\Orders\RecordCashPayment;
use App\Actions\Orders\RecordQcCheck;
use App\Actions\Orders\RecordWeight;
use App\Actions\Payments\IssueStoreCredit;
use App\Enums\Role;
use App\Enums\WeightSource;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\AuditLog;
use App\Support\Weight;
use Illuminate\Auth\Events\Login;
use Livewire\Livewire;

/*
 * Guideline ch. 6, Sprint 00 core domain ("RBAC with 6 roles, audit log"), built out in Sprint 07:
 * a scoped v1 log of staff/admin actions — role changes, logins, cash payments, store credit.
 */

it('logs a staff role change', function () {
    $owner = staff(Role::Owner);
    $target = staff(Role::Butcher);
    Filament\Facades\Filament::setCurrentPanel('admin');
    test()->actingAs($owner);

    Livewire::test(EditUser::class, ['record' => $target->getRouteKey()])
        ->fillForm(['role' => Role::Manager->value])
        ->call('save')
        ->assertHasNoFormErrors();

    $log = AuditLog::query()->where('event', 'staff.updated')->latest('id')->first();
    expect($log)->not->toBeNull()
        ->and($log->causer_id)->toBe($owner->id)
        ->and($log->meta['role']['to'])->toBe(Role::Manager->value);
});

it('logs a successful staff login', function () {
    $user = staff(Role::Manager);

    event(new Login('web', $user, false));

    $log = AuditLog::query()->where('event', 'staff.login')->latest('id')->first();
    expect($log)->not->toBeNull()
        ->and($log->causer_id)->toBe($user->id);
});

it('logs a cash payment', function () {
    fakePayments();
    $frontDesk = staff(Role::FrontDesk);
    $butcher = staff(Role::Butcher);
    $product = product(priceCents: 500, estLb: '2.000');
    $quote = app(QuoteCart::class)->handle([$product->id => 1]);

    ['order' => $order] = app(PlaceOrder::class)->handle(
        lines: [$product->id => 1],
        customer: ['customer_name' => 'A', 'customer_email' => 'a@example.com', 'customer_phone' => '2675550123'],
        expectedHoldCents: $quote['hold_cents'],
        paymentMethod: 'cash',
    );
    app(RecordWeight::class)->handle($order->fresh()->items->first(), Weight::pounds('2.000'), WeightSource::Manual, $butcher);
    app(RecordQcCheck::class)->handle($order->fresh(), true, ['weight_matches' => true], $butcher, '38.0');
    app(FinalizeOrder::class)->handle($order->fresh(), $frontDesk);

    app(RecordCashPayment::class)->handle($order->fresh(), 1000, $frontDesk);

    $log = AuditLog::query()->where('event', 'order.cash_payment_recorded')->latest('id')->first();
    expect($log)->not->toBeNull()
        ->and($log->causer_id)->toBe($frontDesk->id)
        ->and($log->description)->toContain($order->number);
});

it('logs store credit issuance', function () {
    $manager = staff(Role::Manager);

    $account = app(IssueStoreCredit::class)->handle('buyer@example.com', 1000, 'Goodwill', $manager);

    $log = AuditLog::query()->where('event', 'store_credit.issued')->latest('id')->first();
    expect($log)->not->toBeNull()
        ->and($log->causer_id)->toBe($manager->id)
        ->and($log->auditable_id)->toBe($account->customer_email);
});

it('never lets an audit log row be updated or deleted', function () {
    $log = AuditLog::record('test.event', 'A test event');

    expect(fn () => $log->update(['description' => 'changed']))->toThrow(LogicException::class)
        ->and(fn () => $log->delete())->toThrow(LogicException::class);
});
