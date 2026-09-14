<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Pages;

use App\Enums\Role;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var User $user */
        $user = $this->getRecord();
        $data['role'] = $user->getRoleNames()->first();

        return $data;
    }

    /**
     * @param  User  $record
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return DB::transaction(function () use ($record, $data): User {
            $isSelf = $record->is(auth()->user());
            $newRole = $isSelf ? null : ($data['role'] ?? null);
            $newActive = $isSelf ? true : (bool) ($data['is_active'] ?? $record->is_active);

            // Never lock the business out: keep at least one active Owner
            $losesOwner = $record->isOwner() && (($newRole !== null && $newRole !== Role::Owner->value) || ! $newActive);
            if ($losesOwner) {
                $otherOwners = User::query()->role(Role::Owner->value)->where('is_active', true)->whereKeyNot($record->getKey())->lockForUpdate()->count();
                if ($otherOwners === 0) {
                    Notification::make()->danger()->title('There must always be at least one active Owner.')->send();
                    throw new Halt;
                }
            }

            $record->name = (string) $data['name'];
            $record->email = strtolower((string) $data['email']);
            if (filled($data['password'] ?? null)) {
                $record->password = (string) $data['password'];
            }
            $record->is_active = $newActive;
            $record->save();

            if ($newRole !== null) {
                $record->syncRoles([$newRole]);
            }

            return $record;
        });
    }
}
