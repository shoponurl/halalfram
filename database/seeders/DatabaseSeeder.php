<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([RolesAndPermissionsSeeder::class, NotificationTemplateSeeder::class, CategorySeeder::class, CatalogSeeder::class, DeliverySeeder::class]);

        // Demo accounts and demo stock — local and staging only. Production owners are created with
        // `php artisan staff:create`, and real inventory comes in via /admin/lots.
        if (! app()->environment(['local', 'staging', 'testing'])) {
            return;
        }

        foreach (Role::cases() as $role) {
            User::query()->firstOrCreate(
                ['email' => "{$role->value}@halalbrothers.test"],
                ['name' => "Demo {$role->label()}", 'password' => 'Staging-Only-2026!'],
            )->syncRoles([$role->value]);
        }

        $this->call(InventorySeeder::class);
    }
}
