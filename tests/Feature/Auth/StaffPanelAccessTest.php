<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\User;
use Spatie\Permission\Models\Role as RoleModel;

it('shows the storefront home page', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Halal Brothers', false)
        ->assertSee('id="productGrid"', false);
});

it('sends guests to the staff login', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('has exactly the six guideline roles', function () {
    expect(RoleModel::query()->pluck('name')->sort()->values()->all())
        ->toBe(collect(Role::values())->sort()->values()->all());
});

it('lets every staff role into the panel', function (Role $role) {
    $this->actingAs(staff($role))->get('/admin')->assertOk();
})->with(Role::cases());

it('keeps customers (no role) out of the panel', function () {
    $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
});

it('keeps deactivated staff out of the panel', function () {
    $this->actingAs(staff(Role::Manager, active: false))->get('/admin')->assertForbidden();
});

it('forces staff without 2FA to set it up before using the panel', function () {
    $response = $this->actingAs(staff(Role::Owner, withTwoFactor: false))->get('/admin');

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('multi-factor-authentication');
});

it('stores the 2FA secret encrypted and never serialises it', function () {
    $user = staff(Role::Butcher);

    $raw = DB::table('users')->where('id', $user->id)->value('app_authentication_secret');
    expect($raw)->not->toBe('JBSWY3DPEHPK3PXP')
        ->and($user->toArray())->not->toHaveKey('app_authentication_secret');
});
