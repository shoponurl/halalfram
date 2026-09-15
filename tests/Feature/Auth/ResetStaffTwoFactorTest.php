<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\AuditLog;

/* A staff member who lost their phone and recovery codes needs a way back in that still ends in 2FA. */

it('clears 2FA so it must be enrolled again, and audits who and why', function () {
    $butcher = staff(Role::Butcher);
    expect($butcher->app_authentication_secret)->not->toBeNull();

    $this->artisan('staff:reset-2fa', ['email' => $butcher->email, '--reason' => 'lost phone, identity confirmed in person', '--no-interaction' => true])
        ->assertSuccessful();

    expect($butcher->fresh()->app_authentication_secret)->toBeNull()
        ->and($butcher->fresh()->app_authentication_recovery_codes)->toBeNull()
        ->and(AuditLog::query()->where('event', 'staff.2fa_reset')->sole()->meta)->toBe(['reason' => 'lost phone, identity confirmed in person']);
});

it('refuses without a reason', function () {
    $butcher = staff(Role::Butcher);

    $this->artisan('staff:reset-2fa', ['email' => $butcher->email, '--no-interaction' => true])->assertExitCode(2);

    expect($butcher->fresh()->app_authentication_secret)->not->toBeNull();
});
