<?php

use App\Actions\Attendees\MarkAttendeeReceived;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    Schema::dropIfExists('attendees');
    Schema::create('attendees', function (Blueprint $table) {
        $table->text('uuid')->primary();
        $table->boolean('received')->default(false);
        $table->timestamp('received_at')->nullable();
    });
});

test('the conditional update permits only one first receipt', function () {
    $this->travelTo('2026-11-24 12:00:00');
    $uuid = (string) Str::uuid();
    DB::table('attendees')->insert(['uuid' => $uuid]);
    $action = app(MarkAttendeeReceived::class);

    $first = $action->handle($uuid);
    $second = $action->handle($uuid);

    expect($first['received_now'])->toBeTrue()
        ->and($second['received_now'])->toBeFalse()
        ->and($second['received_at']->toDateTimeString())->toBe('2026-11-24 12:00:00');
    $this->assertDatabaseHas('attendees', [
        'uuid' => $uuid,
        'received' => true,
        'received_at' => '2026-11-24 12:00:00',
    ]);
});
