<?php

namespace App\Actions\Attendees;

use App\Models\Attendee;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

class MarkAttendeeReceived
{
    /**
     * @return array{received_now: bool, received_at: CarbonInterface|null}|null
     */
    public function handle(string $uuid): ?array
    {
        if (! Str::isUuid($uuid)) {
            return null;
        }

        $receivedAt = now();
        $updated = Attendee::query()
            ->whereKey($uuid)
            ->where('received', false)
            ->update(['received' => true, 'received_at' => $receivedAt]);

        if ($updated === 1) {
            return ['received_now' => true, 'received_at' => $receivedAt];
        }

        $attendee = Attendee::query()->select(['uuid', 'received', 'received_at'])->find($uuid);

        return $attendee?->received
            ? ['received_now' => false, 'received_at' => $attendee->received_at]
            : null;
    }
}
