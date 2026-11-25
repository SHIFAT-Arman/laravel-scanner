<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

test('only admins can open staff management', function () {
    $staff = User::factory()->create(['role' => 'staff']);

    $this->get(route('staff.index'))->assertRedirect(route('login'));
    $this->actingAs($staff)->get(route('staff.index'))->assertForbidden();
});

test('admin can create a verified staff account', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    Livewire::actingAs($admin)
        ->test('pages::staff')
        ->set('name', 'Scanner Staff')
        ->set('email', 'staff@example.com')
        ->set('password', 'temporary-password')
        ->set('password_confirmation', 'temporary-password')
        ->call('save')
        ->assertHasNoErrors();

    $staff = User::query()->where('email', 'staff@example.com')->firstOrFail();

    expect($staff->name)->toBe('Scanner Staff')
        ->and($staff->role)->toBe('staff')
        ->and($staff->hasVerifiedEmail())->toBeTrue()
        ->and(Hash::check('temporary-password', $staff->password))->toBeTrue();
});

test('admin cannot create staff with an existing email', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    User::factory()->create(['email' => 'existing@example.com']);

    Livewire::actingAs($admin)
        ->test('pages::staff')
        ->set('name', 'Scanner Staff')
        ->set('email', 'existing@example.com')
        ->set('password', 'temporary-password')
        ->set('password_confirmation', 'temporary-password')
        ->call('save')
        ->assertHasErrors(['email']);

    expect(User::query()->where('email', 'existing@example.com')->count())->toBe(1);
});

test('admin can promote staff to admin', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $staff = User::factory()->create(['role' => 'staff']);

    Livewire::actingAs($admin)
        ->test('pages::staff')
        ->call('promote', $staff->id)
        ->assertHasNoErrors();

    expect($staff->refresh()->role)->toBe('admin');
    $this->actingAs($staff)->get(route('staff.index'))->assertOk();
});

test('staff cannot promote another staff member', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    $otherStaff = User::factory()->create(['role' => 'staff']);

    Livewire::actingAs($staff)
        ->test('pages::staff')
        ->call('promote', $otherStaff->id)
        ->assertForbidden();

    expect($otherStaff->fresh()->role)->toBe('staff');
});
