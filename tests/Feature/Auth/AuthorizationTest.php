<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware(['auth', 'verified', 'admin'])
        ->get('/test-admin', fn () => response('ok'));
});

test('guests cannot access admin routes', function () {
    $response = $this->get('/test-admin');

    $response->assertRedirect(route('login'));
});

test('staff cannot access admin routes', function () {
    $staff = User::factory()->create(['role' => 'staff']);

    $response = $this->actingAs($staff)->get('/test-admin');

    $response->assertForbidden();
});

test('multiple admins can access admin routes', function () {
    $admins = User::factory()->count(2)->create(['role' => 'admin']);

    foreach ($admins as $admin) {
        $this->actingAs($admin)->get('/test-admin')->assertOk()->assertSeeText('ok');
    }
});
