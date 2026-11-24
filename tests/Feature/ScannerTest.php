<?php

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    Schema::dropIfExists('attendees');
    Schema::create('attendees', function (Blueprint $table) {
        $table->text('uuid')->primary();
        $table->text('name')->nullable();
        $table->text('aiub_id')->nullable();
        $table->text('email')->nullable();
        $table->boolean('received')->default(false);
        $table->timestamp('received_at')->nullable();
    });
});

test('guests cannot open the scanner', function () {
    $this->get(route('scanner'))->assertRedirect(route('login'));
});

test('staff can open the scanner and find an attendee', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    $uuid = (string) Str::uuid();
    DB::table('attendees')->insert([
        'uuid' => $uuid,
        'name' => 'Ada Lovelace',
        'aiub_id' => 'AIUB-001',
        'email' => 'ada@example.com',
    ]);

    $this->actingAs($staff)->get(route('scanner'))->assertOk()->assertSeeText('Start camera');

    Livewire::actingAs($staff)
        ->test('pages::scanner')
        ->set('uuid', $uuid)
        ->call('lookup')
        ->assertSeeText('Ada Lovelace')
        ->assertSeeText('AIUB-001')
        ->assertDontSee('ada@example.com');
});

test('invalid UUID shows a safe not found state', function () {
    $staff = User::factory()->create(['role' => 'staff']);

    Livewire::actingAs($staff)
        ->test('pages::scanner')
        ->set('uuid', 'not-a-uuid')
        ->call('lookup')
        ->assertSeeText('Attendee not found');
});

test('camera result can look up an attendee directly', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    $uuid = (string) Str::uuid();
    DB::table('attendees')->insert([
        'uuid' => $uuid,
        'name' => 'Grace Hopper',
        'aiub_id' => 'AIUB-002',
    ]);

    Livewire::actingAs($staff)
        ->test('pages::scanner')
        ->call('lookup', $uuid)
        ->assertSet('uuid', $uuid)
        ->assertSeeText('Grace Hopper');
});

test('staff can mark an attendee received once', function () {
    $this->travelTo('2026-11-24 12:00:00');
    $staff = User::factory()->create(['role' => 'staff']);
    $uuid = (string) Str::uuid();
    DB::table('attendees')->insert(['uuid' => $uuid, 'name' => 'Ada Lovelace']);

    Livewire::actingAs($staff)
        ->test('pages::scanner')
        ->call('lookup', $uuid)
        ->call('markReceived')
        ->assertSeeText('Received');

    $this->assertDatabaseHas('attendees', [
        'uuid' => $uuid,
        'received' => true,
        'received_at' => '2026-11-24 12:00:00',
    ]);
});

test('repeated scans report the original receipt time', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    $uuid = (string) Str::uuid();
    DB::table('attendees')->insert([
        'uuid' => $uuid,
        'name' => 'Ada Lovelace',
        'received' => true,
        'received_at' => '2026-11-23 10:30:00',
    ]);

    Livewire::actingAs($staff)
        ->test('pages::scanner')
        ->call('lookup', $uuid)
        ->assertSeeText('Already received')
        ->assertSeeText('Nov 23, 2026 10:30 AM')
        ->call('markReceived');

    $this->assertDatabaseHas('attendees', [
        'uuid' => $uuid,
        'received_at' => '2026-11-23 10:30:00',
    ]);
});
