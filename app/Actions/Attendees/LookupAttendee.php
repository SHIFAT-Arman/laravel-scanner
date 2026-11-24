<?php

namespace App\Actions\Attendees;

use App\Models\Attendee;
use App\Models\AttendeeDisplaySetting;
use Illuminate\Support\Str;

class LookupAttendee
{
    public function handle(string $uuid): ?Attendee
    {
        if (! Str::isUuid($uuid)) {
            return null;
        }

        return Attendee::query()
            ->select(['uuid', 'received', 'received_at', ...AttendeeDisplaySetting::enabledFields()])
            ->where('uuid', $uuid)
            ->first();
    }
}
