<?php

use App\Models\Attendee;
use DateTimeInterface;

it('casts receipt attributes to their application types', function () {
    $attendee = new Attendee;
    $attendee->setRawAttributes([
        'received' => 1,
        'received_at' => '2026-11-23 12:00:00',
    ]);

    expect($attendee->received)->toBeTrue()
        ->and($attendee->received_at)->toBeInstanceOf(DateTimeInterface::class);
});
