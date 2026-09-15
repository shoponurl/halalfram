<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;

/**
 * For a staff member who lost both their authenticator app and their recovery codes. Clears 2FA so
 * they enrol a new app at their next sign-in (2FA stays required — the panel won't let them in
 * without it). Server shell only, so resetting someone's second factor needs server access too.
 */
class ResetStaffTwoFactor extends Command
{
    protected $signature = 'staff:reset-2fa {email} {--reason= : why, for the audit log (e.g. "lost phone, identity checked in person")}';

    protected $description = 'Clears a staff member\'s 2FA so they set it up again at next sign-in (audited)';

    public function handle(): int
    {
        $user = User::query()->where('email', (string) $this->argument('email'))->first();
        if ($user === null) {
            $this->error('No staff account with that email.');

            return self::FAILURE;
        }

        $reason = trim((string) $this->option('reason'));
        if ($reason === '') {
            $this->error('--reason is required (it goes in the audit log). Confirm who they are in person or by a call to a known number first.');

            return self::INVALID;
        }

        if ($this->input->isInteractive() && ! confirm("Reset 2FA for {$user->email}?", default: false)) {
            return self::FAILURE;
        }

        $user->saveAppAuthenticationSecret(null);
        $user->saveAppAuthenticationRecoveryCodes(null);
        AuditLog::record('staff.2fa_reset', "2FA reset for {$user->email}", $user, ['reason' => $reason]);

        $this->info("2FA cleared for {$user->email}. They'll be asked to set up an authenticator app when they next sign in.");

        return self::SUCCESS;
    }
}
