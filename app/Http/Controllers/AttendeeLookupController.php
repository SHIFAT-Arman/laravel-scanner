<?php

namespace App\Http\Controllers;

use App\Actions\Attendees\LookupAttendee;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;

class AttendeeLookupController extends Controller
{
    public function __invoke(string $uuid, LookupAttendee $lookupAttendee): JsonResponse
    {
        $attendee = $lookupAttendee->handle($uuid);

        abort_if($attendee === null, 404);

        return response()->json(Arr::except($attendee->attributesToArray(), ['received', 'received_at']));
    }
}
