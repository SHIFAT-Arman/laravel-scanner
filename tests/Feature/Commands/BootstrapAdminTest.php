<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('bootstrap admin creates one verified administrator and is idempotent', function () {
    config()->set('auth.bootstrap_admin', [
        'name' => 'Event Admin',
        'email' => 'admin@example.com',
        'password' => 'temporary-password',
    ]);

    $this->artisan('app:bootstrap-admin')->assertSuccessful();
    $this->artisan('app:bootstrap-admin')->assertSuccessful();

    $admin = User::where('email', 'admin@example.com')->firstOrFail();

    expect(User::where('email', 'admin@example.com')->count())->toBe(1)
        ->and($admin->name)->toBe('Event Admin')
        ->and($admin->role)->toBe('admin')
        ->and($admin->email_verified_at)->not->toBeNull()
        ->and(Hash::check('temporary-password', $admin->password))->toBeTrue();
});

test('bootstrap admin refuses missing credentials', function () {
    config()->set('auth.bootstrap_admin', [
        'name' => null,
        'email' => null,
        'password' => null,
    ]);

    $this->artisan('app:bootstrap-admin')->assertFailed();

    expect(User::count())->toBe(0);
});
