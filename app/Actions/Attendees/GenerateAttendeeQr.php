<?php

namespace App\Actions\Attendees;

use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Writer;

class GenerateAttendeeQr
{
    public function handle(string $uuid): string
    {
        return (new Writer(new GDLibRenderer(600)))->writeString($uuid);
    }
}
