<?php

use App\Models\AttendeeDisplaySetting;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    Schema::dropIfExists('attendees');
    Schema::create('attendees', function (Blueprint $table) {
        $table->text('uuid')->primary();
        $table->text('name')->nullable();
        $table->text('aiub_id')->nullable();
        $table->text('email')->nullable();
        $table->text('phone_no')->nullable();
        $table->text('amount_paid')->nullable();
        $table->boolean('received')->default(false);
        $table->timestamp('received_at')->nullable();
    });
});

test('staff can retrieve an attendee by UUID with safe fields only', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    $uuid = (string) Str::uuid();

    DB::table('attendees')->insert([
        'uuid' => $uuid,
        'name' => 'Ada Lovelace',
        'aiub_id' => 'AIUB-001',
        'email' => 'ada@example.com',
        'phone_no' => '+8801000000000',
        'amount_paid' => '1000',
    ]);

    $response = $this->actingAs($staff)->getJson(route('attendees.show', $uuid));

    $response->assertOk()->assertExactJson([
        'uuid' => $uuid,
        'name' => 'Ada Lovelace',
        'aiub_id' => 'AIUB-001',
    ]);
});

test('invalid and nonexistent UUIDs return not found', function () {
    $staff = User::factory()->create(['role' => 'staff']);

    $this->actingAs($staff)->getJson('/attendees/not-a-uuid')->assertNotFound();
    $this->actingAs($staff)->getJson(route('attendees.show', (string) Str::uuid()))->assertNotFound();
});

test('staff cannot request additional attendee columns', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    $uuid = (string) Str::uuid();

    DB::table('attendees')->insert([
        'uuid' => $uuid,
        'name' => 'Ada Lovelace',
        'aiub_id' => 'AIUB-001',
        'email' => 'ada@example.com',
        'amount_paid' => '1000',
    ]);

    $response = $this->actingAs($staff)->getJson(
        route('attendees.show', $uuid).'?fields[]=email&fields[]=amount_paid',
    );

    $response->assertOk()->assertExactJson([
        'uuid' => $uuid,
        'name' => 'Ada Lovelace',
        'aiub_id' => 'AIUB-001',
    ]);
});

test('staff lookup immediately uses saved display fields', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    $uuid = (string) Str::uuid();
    AttendeeDisplaySetting::query()->create(['id' => 1, 'fields' => ['email']]);

    DB::table('attendees')->insert([
        'uuid' => $uuid,
        'name' => 'Ada Lovelace',
        'aiub_id' => 'AIUB-001',
        'email' => 'ada@example.com',
    ]);

    $response = $this->actingAs($staff)->getJson(route('attendees.show', $uuid));

    $response->assertOk()->assertExactJson([
        'uuid' => $uuid,
        'email' => 'ada@example.com',
    ]);
});
