<?php

use App\Models\User;

test('guests see the event home page and login action', function () {
    $response = $this->get(route('home'));

    $response
        ->assertOk()
        ->assertSeeText('IEEE AIUB Student Branch')
        ->assertSeeText('Members Orientation 2026')
        ->assertSeeText('Log in')
        ->assertDontSeeText('A fast, secure check-in space for the staff welcoming our newest IEEE members.')
        ->assertDontSeeText('Authorized event staff only')
        ->assertDontSee('laravel.com', false)
        ->assertDontSee('github.com/laravel', false);
});

test('authenticated users can open the dashboard from the event home page', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('home'));

    $response
        ->assertOk()
        ->assertSeeText('Open dashboard')
        ->assertDontSeeText('Log in');
});
