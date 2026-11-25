<?php

namespace App\Actions\Attendees;

use App\Models\Attendee;
use App\Models\AttendeeDisplaySetting;
use Illuminate\Support\Str;

class LookupAttendee
{
    public function handle(string $identifier): ?Attendee
    {
        $identifier = trim($identifier);

        if ($identifier === '') {
            return null;
        }

        return Attendee::query()
            ->select(['uuid', 'received', 'received_at', ...AttendeeDisplaySetting::enabledFields()])
            ->where(Str::isUuid($identifier) ? 'uuid' : 'aiub_id', $identifier)
            ->first();
    }
}
