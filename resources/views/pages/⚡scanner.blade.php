<?php

use App\Actions\Attendees\LookupAttendee;
use App\Actions\Attendees\MarkAttendeeReceived;
use App\Models\AttendeeDisplaySetting;
use Illuminate\Support\Arr;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Scanner')] class extends Component
{
    public string $uuid = '';

    /** @var array<string, mixed>|null */
    public ?array $attendee = null;

    public bool $notFound = false;

    public ?string $receiptStatus = null;

    public ?string $receivedAt = null;

    public function lookup(LookupAttendee $lookupAttendee, ?string $scannedUuid = null): void
    {
        $this->uuid = trim($scannedUuid ?? $this->uuid);
        $attendee = $lookupAttendee->handle($this->uuid);
        $this->uuid = $attendee?->uuid ?? $this->uuid;
        $this->attendee = $attendee ? Arr::except($attendee->attributesToArray(), ['received', 'received_at']) : null;
        $this->notFound = $attendee === null;
        $this->receiptStatus = $attendee?->received ? 'already' : null;
        $this->receivedAt = $attendee?->received_at?->toDayDateTimeString();
    }

    public function markReceived(MarkAttendeeReceived $markAttendeeReceived): void
    {
        $result = $markAttendeeReceived->handle($this->uuid);

        if ($result === null) {
            $this->attendee = null;
            $this->notFound = true;

            return;
        }

        $this->receiptStatus = $result['received_now'] ? 'received' : 'already';
        $this->receivedAt = $result['received_at']?->toDayDateTimeString();
    }
};
?>

<div class="mx-auto flex w-full max-w-xl flex-col gap-6" x-data="qrScanner">
    <div>
        <flux:heading size="xl">{{ __('Scan attendee QR') }}</flux:heading>
        <flux:text class="mt-2">{{ __('Point the camera at an attendee QR code or search by UUID or AIUB ID.') }}</flux:text>
    </div>

    <flux:card class="space-y-4">
        <video x-ref="video" x-show="scanning" class="aspect-square w-full rounded-lg bg-black object-cover" muted playsinline></video>

        <template x-if="error">
            <flux:callout variant="warning" x-text="error" />
        </template>

        <div class="flex gap-3">
            <flux:button variant="primary" class="flex-1" x-show="!scanning" @click="start">
                {{ __('Start camera') }}
            </flux:button>
            <flux:button class="flex-1" x-show="scanning" @click="stop">
                {{ __('Stop camera') }}
            </flux:button>
        </div>
    </flux:card>

    <form wire:submit="lookup" class="flex flex-col gap-3 sm:flex-row">
        <flux:input wire:model="uuid" :label="__('Attendee UUID or AIUB ID')" class="flex-1" autocomplete="off" />
        <flux:button variant="primary" type="submit" class="sm:self-end" wire:loading.attr="disabled">
            {{ __('Find attendee') }}
        </flux:button>
    </form>

    @if ($notFound)
        <flux:callout variant="danger" heading="{{ __('Attendee not found') }}" text="{{ __('Check the QR code, UUID, or AIUB ID and try again.') }}" />
    @endif

    @if ($attendee)
        <flux:card class="space-y-4">
            <flux:heading size="lg">{{ __('Attendee found') }}</flux:heading>

            <dl class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @foreach ($attendee as $field => $value)
                    @continue($field === 'uuid')
                    <div class="grid gap-1 py-3 sm:grid-cols-2" wire:key="attendee-field-{{ $field }}">
                        <dt class="font-medium">{{ AttendeeDisplaySetting::SUPPORTED_FIELDS[$field] }}</dt>
                        <dd class="break-words">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>

            @if ($receiptStatus === 'received')
                <flux:callout variant="success" heading="{{ __('Received') }}" text="{{ $receivedAt }}" />
            @elseif ($receiptStatus === 'already')
                <flux:callout variant="warning" heading="{{ __('Already received') }}" text="{{ $receivedAt }}" />
            @else
                <flux:button variant="primary" class="w-full" wire:click="markReceived" wire:loading.attr="disabled">
                    {{ __('Mark received') }}
                </flux:button>
            @endif
        </flux:card>
    @endif
</div>
