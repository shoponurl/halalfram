<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\AuditLog;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * Role and is_active are not mass assignable, so they are set explicitly here.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data): User {
            $user = new User;
            $user->name = (string) $data['name'];
            $user->email = strtolower((string) $data['email']);
            $user->password = (string) $data['password'];
            $user->is_active = (bool) ($data['is_active'] ?? true);
            $user->save();
            $user->syncRoles([(string) $data['role']]);

            AuditLog::record('staff.created', "Created staff account {$user->email}", $user, ['role' => (string) $data['role']]);

            return $user;
        });
    }
}
