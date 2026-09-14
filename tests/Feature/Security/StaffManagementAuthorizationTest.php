<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\Role;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Livewire\Livewire;

/*
 * Security test matrix (guideline ch. 5, layer 4): every role × every staff-management route.
 * Only the Owner may manage staff; everyone else gets 403.
 */

$routes = [
    'list' => fn (User $target) => '/admin/users',
    'create' => fn (User $target) => '/admin/users/create',
    'edit' => fn (User $target) => "/admin/users/{$target->id}/edit",
];

foreach (Role::cases() as $role) {
    foreach ($routes as $name => $url) {
        $expected = $role === Role::Owner ? 200 : 403;

        it("returns {$expected} for {$role->label()} on staff {$name}", function () use ($role, $url, $expected) {
            $target = staff(Role::Driver);

            $this->actingAs(staff($role))->get($url($target))->assertStatus($expected);
        });
    }
}

beforeEach(fn () => Filament::setCurrentPanel('admin'));

it('lets the Owner create a staff member with a role', function () {
    $this->actingAs(staff(Role::Owner));

    Livewire::test(CreateUser::class)
        ->fillForm(['name' => 'New Butcher', 'email' => 'NEW@example.com', 'role' => Role::Butcher->value, 'is_active' => true, 'password' => 'Str0ng-Password-1'])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::query()->where('email', 'new@example.com')->firstOrFail();
    expect($user->hasRole(Role::Butcher->value))->toBeTrue()
        ->and($user->is_active)->toBeTrue();
});

it('never lets role or is_active be mass assigned', function () {
    $user = User::query()->make(['name' => 'X', 'email' => 'x@example.com', 'password' => 'secret-secret']);

    expect(fn () => $user->fill(['is_active' => false]))->toThrow(MassAssignmentException::class);
});

it('lets an Owner demote another Owner while one remains', function () {
    $owner = staff(Role::Owner);
    $other = staff(Role::Owner);
    $this->actingAs($owner);

    Livewire::test(EditUser::class, ['record' => $other->getRouteKey()])
        ->fillForm(['role' => Role::Manager->value])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($other->fresh()->hasRole(Role::Manager->value))->toBeTrue();
});

it('refuses to deactivate or demote the last active Owner', function (array $change) {
    $onlyOwner = staff(Role::Owner);
    // A non-owner who has been granted staff management directly (e.g. a future delegated manager)
    $delegate = staff(Role::Manager);
    $delegate->givePermissionTo(Permission::ManageStaff->value);
    $this->actingAs($delegate);

    Livewire::test(EditUser::class, ['record' => $onlyOwner->getRouteKey()])
        ->fillForm($change)
        ->call('save');

    $fresh = $onlyOwner->fresh();
    expect($fresh->is_active)->toBeTrue()
        ->and($fresh->hasRole(Role::Owner->value))->toBeTrue();
})->with([
    'deactivate' => [['is_active' => false]],
    'demote' => [['role' => Role::Accountant->value]],
]);

it('stops staff from changing their own role or deactivating themselves', function () {
    $owner = staff(Role::Owner);
    $this->actingAs($owner);

    Livewire::test(EditUser::class, ['record' => $owner->getRouteKey()])
        ->assertFormFieldDisabled('role')
        ->assertFormFieldDisabled('is_active');
});

it('cannot delete staff, only deactivate', function () {
    $owner = staff(Role::Owner);
    expect($owner->can('delete', staff(Role::Driver)))->toBeFalse();
});
