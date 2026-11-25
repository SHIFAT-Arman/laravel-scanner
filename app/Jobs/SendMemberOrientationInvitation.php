<?php

namespace App\Jobs;

use App\Actions\Attendees\GenerateAttendeeQr;
use App\Mail\MemberOrientationInvitation;
use App\Models\Attendee;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SendMemberOrientationInvitation implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    /** @var array<int, int> */
    public array $backoff = [10, 30];

    /**
     * Create a new job instance.
     */
    public function __construct(public string $uuid, public string $recipientEmail) {}

    /**
     * Execute the job.
     */
    public function handle(GenerateAttendeeQr $generateQr): void
    {
        $claimed = Attendee::query()
            ->whereKey($this->uuid)
            ->whereIn('invitation_email_status', ['queued', 'failed'])
            ->update([
                'invitation_email_status' => 'sending',
                'invitation_email_error' => null,
            ]);

        if ($claimed === 0) {
            return;
        }

        try {
            $attendee = Attendee::query()->findOrFail($this->uuid);
            if (! filter_var($this->recipientEmail, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('The attendee does not have a valid email address.');
            }

            Mail::to($this->recipientEmail)->send(
                new MemberOrientationInvitation($attendee->uuid, $generateQr->handle($attendee->uuid)),
            );

            Attendee::query()->whereKey($this->uuid)->update([
                'invitation_email_status' => 'sent',
                'invitation_email_sent_at' => now(),
                'invitation_email_error' => null,
            ]);
        } catch (Throwable $exception) {
            Attendee::query()->whereKey($this->uuid)->update([
                'invitation_email_status' => 'failed',
                'invitation_email_error' => Str::limit($exception->getMessage(), 1000),
            ]);

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        Attendee::query()
            ->whereKey($this->uuid)
            ->where('invitation_email_status', '!=', 'sent')
            ->update([
                'invitation_email_status' => 'failed',
                'invitation_email_error' => Str::limit($exception?->getMessage() ?? 'Email delivery failed.', 1000),
            ]);
    }
}
