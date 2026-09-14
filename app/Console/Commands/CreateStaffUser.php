<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

/**
 * Creates a staff account from the server shell — the only way to create the first Owner in production.
 * The password is prompted (never passed as an argument, so it doesn't land in shell history).
 */
class CreateStaffUser extends Command
{
    protected $signature = 'staff:create {--role= : One of: owner, manager, front_desk, butcher, driver, accountant}';

    protected $description = 'Create a staff account with a role (2FA is set up on first sign-in)';

    public function handle(): int
    {
        $name = text('Name', required: true);
        $email = text('Email', required: true, validate: fn (string $v) => filter_var($v, FILTER_VALIDATE_EMAIL) ? null : 'Enter a valid email.');
        $role = $this->option('role') ?? select('Role', array_combine(Role::values(), array_map(fn (Role $r) => $r->label(), Role::cases())));
        $pass = password('Password (12+ characters)', required: true);

        $validator = Validator::make(
            ['email' => $email, 'role' => $role, 'password' => $pass],
            [
                'email' => ['unique:users,email'],
                'role' => ['in:'.implode(',', Role::values())],
                'password' => [Password::min(12)->mixedCase()->numbers()],
            ],
        );
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = new User;
        $user->name = $name;
        $user->email = strtolower($email);
        $user->password = $pass;
        $user->save();
        $user->syncRoles([$role]);

        $this->info("Created {$user->email} as ".Role::from($role)->label().'. They will be asked to set up 2FA when they first sign in at /admin.');

        return self::SUCCESS;
    }
}
