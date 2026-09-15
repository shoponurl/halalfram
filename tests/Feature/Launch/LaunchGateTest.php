<?php

declare(strict_types=1);

use App\Actions\Launch\EvaluateLaunchGate;
use App\Enums\LaunchGateItem;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\LaunchGateEvidence;
use App\Models\User;

/*
 * Guideline ch. 8: the launch gate reports DONE / NOT DONE with proof — never "we're ready".
 */

function gateItem(LaunchGateItem $item): array
{
    return collect(app(EvaluateLaunchGate::class)->handle())->firstWhere('item', $item);
}

it('is NOT DONE while debug mode is on or the app is not in production', function () {
    config(['app.env' => 'production', 'app.debug' => true]);
    expect(gateItem(LaunchGateItem::DebugOff)['done'])->toBeFalse();

    config(['app.debug' => false]);
    expect(gateItem(LaunchGateItem::DebugOff)['done'])->toBeTrue();
});

it('names every active staff member who has not set up 2FA, and ignores deactivated ones', function () {
    staff(Role::Owner);
    $missing = staff(Role::FrontDesk, withTwoFactor: false);
    staff(Role::Driver, withTwoFactor: false, active: false);

    $result = gateItem(LaunchGateItem::StaffTwoFactor);

    expect($result['done'])->toBeFalse()
        ->and($result['evidence'])->toContain($missing->email)
        ->and(substr_count($result['evidence'], '@'))->toBe(1);
});

it('flags active demo accounts left over from seeding', function () {
    $demo = User::factory()->create(['email' => 'owner@halalbrothers.test']);

    expect(gateItem(LaunchGateItem::NoDevAccounts)['evidence'])->toContain('owner@halalbrothers.test');

    $demo->is_active = false;
    $demo->save();
    expect(gateItem(LaunchGateItem::NoDevAccounts)['done'])->toBeTrue();
});

it('refuses test-mode Stripe keys as launch-ready payments', function () {
    config(['services.stripe.secret' => 'sk_test_x', 'services.stripe.key' => 'pk_test_x', 'services.stripe.webhook_secret' => 'whsec_x']);
    expect(gateItem(LaunchGateItem::LivePayments))->toMatchArray(['done' => false])
        ->and(gateItem(LaunchGateItem::LivePayments)['evidence'])->toContain('TEST');

    config(['services.stripe.secret' => 'sk_live_x', 'services.stripe.key' => 'pk_live_x']);
    expect(gateItem(LaunchGateItem::LivePayments)['done'])->toBeTrue();
});

it('requires the alerts channel in the log stack and somewhere for alerts to go', function () {
    config(['logging.default' => 'stack', 'logging.channels.stack.channels' => ['daily'], 'launch.alerts.email' => 'owner@shop.test']);
    expect(gateItem(LaunchGateItem::AlertsConfigured)['done'])->toBeFalse();

    config(['logging.channels.stack.channels' => ['daily', 'alerts']]);
    expect(gateItem(LaunchGateItem::AlertsConfigured)['done'])->toBeTrue();
});

it('flags a database user that can DROP or ALTER', function () {
    // The test database runs as a full-privilege user, so this must be NOT DONE here.
    expect(gateItem(LaunchGateItem::DatabaseLeastPrivilege)['done'])->toBeFalse();
});

it('counts a drill only if its latest run passed recently, in this environment', function () {
    expect(gateItem(LaunchGateItem::RestoreDrill)['evidence'])->toBe('No drill recorded yet.');

    LaunchGateEvidence::record(LaunchGateItem::RestoreDrill, true, 'restored in 8.1s');
    expect(gateItem(LaunchGateItem::RestoreDrill)['done'])->toBeTrue();

    $this->travel(31)->days();
    expect(gateItem(LaunchGateItem::RestoreDrill))->toMatchArray(['done' => false]);

    LaunchGateEvidence::record(LaunchGateItem::RestoreDrill, true, 'restored again');
    LaunchGateEvidence::record(LaunchGateItem::RestoreDrill, false, 'decrypt failed');
    expect(gateItem(LaunchGateItem::RestoreDrill)['evidence'])->toContain('FAILED');
});

it('ignores drill evidence recorded in another environment', function () {
    config(['app.env' => 'staging']);
    LaunchGateEvidence::record(LaunchGateItem::RecallDrill, true, 'staging recall in 40 ms');

    config(['app.env' => 'production']);
    expect(gateItem(LaunchGateItem::RecallDrill)['done'])->toBeFalse();
});

it('does not require a cold-chain test shipment while shipping is switched off', function () {
    fakeShipping();
    config(['launch.soft_launch.enabled' => true]);

    expect(gateItem(LaunchGateItem::ColdChainShipment))->toMatchArray(['done' => true]);

    config(['launch.soft_launch.enabled' => false]);
    expect(gateItem(LaunchGateItem::ColdChainShipment)['done'])->toBeFalse();
});

it('lets a named staff member sign off a manual item, with evidence, and audits it', function () {
    $owner = staff(Role::Owner);

    $this->artisan('launch:attest', ['item' => 'pentest_closed', '--evidence' => 'Acme Security report + retest letter, 2027-02-03', '--by' => $owner->email])
        ->assertSuccessful();

    expect(gateItem(LaunchGateItem::PentestClosed))->toMatchArray(['done' => true])
        ->and(LaunchGateEvidence::query()->sole()->recorded_by)->toBe($owner->id)
        ->and(AuditLog::query()->where('event', 'launch.attested')->count())->toBe(1);
});

it('will not let anyone sign off an automated check or a drill, or sign off without evidence', function () {
    $this->artisan('launch:attest', ['item' => 'reconciliation', '--evidence' => 'looked fine to me honestly'])->assertExitCode(2);
    $this->artisan('launch:attest', ['item' => 'restore_drill', '--evidence' => 'we definitely have backups'])->assertExitCode(2);
    $this->artisan('launch:attest', ['item' => 'sms_10dlc'])->assertExitCode(2);

    expect(LaunchGateEvidence::query()->count())->toBe(0);
});

it('fails launch:check while anything is NOT DONE', function () {
    $this->artisan('launch:check')->assertFailed()->expectsOutputToContain('NOT DONE');
});
