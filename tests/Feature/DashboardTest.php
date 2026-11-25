<?php

use App\Models\Attendee;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::dropIfExists('attendees');
    Schema::create('attendees', function (Blueprint $table) {
        $table->uuid('uuid')->primary();
        $table->boolean('received')->default(false);
        $table->timestamp('received_at')->nullable();
    });
});

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response
        ->assertOk()
        ->assertDontSeeText('Staff members');
});

test('admins see staff members on the dashboard', function () {
    $admin = User::factory()->create(['name' => 'Dashboard Admin', 'role' => 'admin']);
    User::factory()->create(['name' => 'Other Admin', 'role' => 'admin']);
    User::factory()->create(['name' => 'Ada Staff', 'email' => 'ada@example.com', 'role' => 'staff']);

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response
        ->assertOk()
        ->assertSeeText('Staff members')
        ->assertSeeText('Ada Staff')
        ->assertSeeText('ada@example.com')
        ->assertDontSeeText('Other Admin');
});

test('dashboard reports current attendance totals', function (array $receivedStates, array $expected) {
    foreach ($receivedStates as $index => $received) {
        Attendee::query()->insert([
            'uuid' => sprintf('00000000-0000-4000-8000-%012d', $index),
            'received' => $received,
            'received_at' => $received ? now() : null,
        ]);
    }

    $user = User::factory()->create();
    DB::flushQueryLog();
    DB::enableQueryLog();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $attendeeQueries = collect(DB::getQueryLog())
        ->filter(fn (array $query): bool => str_contains($query['query'], 'attendees'));

    $response
        ->assertOk()
        ->assertViewHas('total', $expected['total'])
        ->assertViewHas('received', $expected['received'])
        ->assertViewHas('pending', $expected['pending'])
        ->assertViewHas('percentage', $expected['percentage'])
        ->assertSeeText($expected['percentage'].'%')
        ->assertSeeText('checked in');

    expect($attendeeQueries)->toHaveCount(1);
})->with([
    'empty list' => [[], ['total' => 0, 'received' => 0, 'pending' => 0, 'percentage' => 0]],
    'none received' => [[false, false], ['total' => 2, 'received' => 0, 'pending' => 2, 'percentage' => 0]],
    'mixed attendance' => [[true, false, true, false], ['total' => 4, 'received' => 2, 'pending' => 2, 'percentage' => 50]],
    'all received' => [[true, true], ['total' => 2, 'received' => 2, 'pending' => 0, 'percentage' => 100]],
]);
