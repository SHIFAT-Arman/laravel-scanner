<?php

use App\Actions\Attendees\GenerateAttendeeQr;
use App\Jobs\SendMemberOrientationInvitation;
use App\Models\Attendee;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

new #[Title('Attendees')] class extends Component
{
    use WithFileUploads, WithPagination;

    private const array FORM_FIELDS = ['uuid', 'name', 'aiub_id', 'email', 'phone_no', 'gender', 'size', 'dept', 'amount_paid', 'membership_cost'];

    private const array EXPORT_FIELDS = [...self::FORM_FIELDS, 'received', 'received_at'];

    private const int MAX_IMPORT_ROWS = 5000;

    public string $search = '';

    public string $status = 'all';

    public string $recipientColumn = 'email';

    public bool $showForm = false;

    public bool $showQr = false;

    public ?string $editingUuid = null;

    /** @var array<string, mixed> */
    public array $form = [];

    /** @var array<int, string> */
    public array $selected = [];

    /** @var array<string, mixed>|null */
    public ?array $qrAttendee = null;

    public ?string $qrImage = null;

    public mixed $csv = null;

    public function mount(): void
    {
        $this->resetForm();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->editingUuid = null;
        $this->resetForm();
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(string $uuid): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $attendee = Attendee::query()->findOrFail($uuid);

        $this->editingUuid = $attendee->uuid;
        $this->form = $attendee->only(self::FORM_FIELDS);
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $attendee = $this->editingUuid
            ? Attendee::query()->findOrFail($this->editingUuid)
            : new Attendee;
        $uniqueUuid = Rule::unique(Attendee::class, 'uuid');

        if ($attendee->exists) {
            $uniqueUuid->ignore($attendee);
        }

        $validated = $this->validate($this->rules($uniqueUuid))['form'];

        $attendee->fill($validated)->save();
        $this->showForm = false;
        Flux::toast(variant: 'success', text: $this->editingUuid ? __('Attendee updated.') : __('Attendee created.'));
    }

    public function delete(string $uuid): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        Attendee::query()->findOrFail($uuid)->delete();
        Flux::toast(variant: 'success', text: __('Attendee deleted.'));
    }

    public function export(): StreamedResponse
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        return response()->streamDownload(function (): void {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, self::EXPORT_FIELDS, escape: '');

            Attendee::query()->orderBy('uuid')->each(function (Attendee $attendee) use ($stream): void {
                fputcsv($stream, array_map(
                    fn (string $field): mixed => $this->csvCell($attendee->{$field}),
                    self::EXPORT_FIELDS,
                ), escape: '');
            });

            fclose($stream);
        }, 'attendees.csv', ['Content-Type' => 'text/csv']);
    }

    public function downloadQr(string $uuid, GenerateAttendeeQr $generateQr): StreamedResponse
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        $uuid = Attendee::query()->findOrFail($uuid)->uuid;

        return response()->streamDownload(
            fn () => print $generateQr->handle($uuid),
            "{$uuid}.png",
            ['Content-Type' => 'image/png'],
        );
    }

    public function previewQr(string $uuid, GenerateAttendeeQr $generateQr): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        $attendee = Attendee::query()
            ->select(['uuid', 'name', 'aiub_id', 'size', 'phone_no', 'received', 'received_at', 'invitation_email_status', 'invitation_email_sent_at', 'invitation_email_error'])
            ->findOrFail($uuid);

        $this->qrAttendee = [
            'uuid' => $attendee->uuid,
            'name' => $attendee->name,
            'aiub_id' => $attendee->aiub_id,
            'size' => $attendee->size,
            'phone_no' => $attendee->phone_no,
            'received' => $attendee->received,
            'received_at' => $attendee->received_at?->toDayDateTimeString(),
            'invitation_email_status' => $attendee->invitation_email_status,
            'invitation_email_sent_at' => $attendee->invitation_email_sent_at?->toDayDateTimeString(),
            'invitation_email_error' => $attendee->invitation_email_error,
        ];
        $this->qrImage = 'data:image/png;base64,'.base64_encode($generateQr->handle($attendee->uuid));
        $this->showQr = true;
    }

    public function downloadSelectedQrs(GenerateAttendeeQr $generateQr): StreamedResponse
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        $validated = $this->validate([
            'selected' => ['required', 'array', 'min:1', 'max:100'],
            'selected.*' => ['required', 'uuid', Rule::exists(Attendee::class, 'uuid')],
        ]);
        $attendees = Attendee::query()
            ->select('uuid')
            ->whereIn('uuid', array_unique($validated['selected']))
            ->orderBy('uuid')
            ->get();

        return response()->streamDownload(function () use ($attendees, $generateQr): void {
            $path = tempnam(sys_get_temp_dir(), 'attendee-qrs-');
            throw_if($path === false, \RuntimeException::class, 'Unable to create the QR archive.');

            $zip = new \ZipArchive;

            if ($zip->open($path, \ZipArchive::OVERWRITE) !== true) {
                unlink($path);

                throw new \RuntimeException('Unable to open the QR archive.');
            }

            try {
                foreach ($attendees as $attendee) {
                    $zip->addFromString("{$attendee->uuid}.png", $generateQr->handle($attendee->uuid));
                }

                $zip->close();
                readfile($path);
            } finally {
                is_file($path) && unlink($path);
            }
        }, 'attendee-qrs.zip', ['Content-Type' => 'application/zip']);
    }

    public function queueInvitation(string $uuid): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        Attendee::query()->findOrFail($uuid);

        $this->dispatchInvitations([$uuid]);
    }

    public function resetInvitation(string $uuid): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        Attendee::query()
            ->whereKey($uuid)
            ->whereIn('invitation_email_status', ['sent', 'failed'])
            ->update([
                'invitation_email_status' => null,
                'invitation_email_sent_at' => null,
                'invitation_email_error' => null,
            ]);
    }

    public function queueSelectedInvitations(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        $validated = $this->validate([
            'selected' => ['required', 'array', 'min:1', 'max:100'],
            'selected.*' => ['required', 'uuid', Rule::exists(Attendee::class, 'uuid')],
        ]);

        $this->dispatchInvitations(array_values(array_unique($validated['selected'])));
    }

    public function import(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        $this->resetErrorBag('csv');
        $this->validate(['csv' => ['required', 'file', 'mimes:csv,txt', 'max:2048']]);

        $stream = fopen($this->csv->getRealPath(), 'r');
        $header = fgetcsv($stream, escape: '');

        if (! is_array($header)) {
            $this->addError('csv', __('The CSV file is empty.'));
            fclose($stream);

            return;
        }

        $header = array_map(fn (string $column): string => strtolower(trim($column, " \t\n\r\0\x0B\xEF\xBB\xBF")), $header);
        $unsupported = array_diff($header, self::FORM_FIELDS);

        if (! in_array('uuid', $header, true) || $unsupported !== [] || count($header) !== count(array_unique($header))) {
            $this->addError('csv', __('The header must contain uuid once and only supported attendee fields.'));
            fclose($stream);

            return;
        }

        $rows = [];
        $uuidLines = [];
        $line = 1;

        while (($values = fgetcsv($stream, escape: '')) !== false) {
            $line++;

            if (count(array_filter($values, fn ($value): bool => trim((string) $value) !== '')) === 0) {
                continue;
            }

            if (count($rows) >= self::MAX_IMPORT_ROWS) {
                $this->addError('csv', __('CSV files may contain at most :count data rows.', ['count' => self::MAX_IMPORT_ROWS]));
                break;
            }

            if (count($values) !== count($header)) {
                $this->addError('csv', __('Row :row has the wrong number of columns.', ['row' => $line]));
                continue;
            }

            $row = array_map(fn (string $value): ?string => trim($value) === '' ? null : trim($value), array_combine($header, $values));
            $validator = Validator::make($row, array_intersect_key($this->importRules(), $row));

            if ($validator->fails()) {
                $this->addError('csv', __('Row :row: :errors', ['row' => $line, 'errors' => $validator->errors()->first()]));
                continue;
            }

            $uuid = $row['uuid'];

            if (isset($uuidLines[$uuid])) {
                $this->addError('csv', __('Row :row duplicates UUID from row :first.', ['row' => $line, 'first' => $uuidLines[$uuid]]));
                continue;
            }

            $uuidLines[$uuid] = $line;
            $rows[] = $row;
        }

        fclose($stream);

        foreach (array_chunk(array_keys($uuidLines), 500) as $uuids) {
            foreach (Attendee::query()->whereIn('uuid', $uuids)->pluck('uuid') as $uuid) {
                $this->addError('csv', __('Row :row uses an existing UUID.', ['row' => $uuidLines[$uuid]]));
            }
        }

        if ($rows === [] && ! $this->getErrorBag()->has('csv')) {
            $this->addError('csv', __('The CSV file has no attendee rows.'));
        }

        if ($this->getErrorBag()->has('csv')) {
            return;
        }

        DB::transaction(fn () => Attendee::query()->insert($rows));
        $this->reset('csv');
        Flux::toast(variant: 'success', text: trans_choice(':count attendee imported.|:count attendees imported.', count($rows), ['count' => count($rows)]));
    }

    /** @return LengthAwarePaginator<int, Attendee> */
    #[Computed]
    public function attendees(): LengthAwarePaginator
    {
        $search = trim($this->search);

        return Attendee::query()
            ->select(['uuid', 'name', 'aiub_id', 'size', 'phone_no', 'received', 'received_at', 'invitation_email_status', 'invitation_email_sent_at', 'invitation_email_error'])
            ->when($search !== '', fn ($query) => $query->where(
                fn ($query) => $query
                    ->whereLike('uuid', "%{$search}%")
                    ->orWhereLike('name', "%{$search}%"),
            ))
            ->when($this->status === 'received', fn ($query) => $query->where('received', true))
            ->when($this->status === 'unreceived', fn ($query) => $query->where('received', false))
            ->orderBy('name')
            ->orderBy('uuid')
            ->paginate(10);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(?Unique $uniqueUuid = null): array
    {
        return [
            'form.uuid' => ['required', 'uuid', $uniqueUuid ?? Rule::unique(Attendee::class, 'uuid')],
            'form.name' => ['nullable', 'string', 'max:255'],
            'form.aiub_id' => ['nullable', 'string', 'max:255'],
            'form.email' => ['nullable', 'email', 'max:255'],
            'form.phone_no' => ['nullable', 'string', 'max:255'],
            'form.gender' => ['nullable', 'string', 'max:255'],
            'form.size' => ['nullable', 'string', 'max:255'],
            'form.dept' => ['nullable', 'string', 'max:255'],
            'form.amount_paid' => ['nullable', 'string', 'max:255'],
            'form.membership_cost' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /** @return array<string, array<int, mixed>> */
    private function importRules(): array
    {
        return [
            'uuid' => ['required', 'uuid'],
            'name' => ['nullable', 'string', 'max:255'],
            'aiub_id' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone_no' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', 'string', 'max:255'],
            'size' => ['nullable', 'string', 'max:255'],
            'dept' => ['nullable', 'string', 'max:255'],
            'amount_paid' => ['nullable', 'string', 'max:255'],
            'membership_cost' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    private function csvCell(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return is_string($value) && preg_match('/^[=+\-@]/', $value) ? "'{$value}" : $value;
    }

    private function resetForm(): void
    {
        $this->form = array_fill_keys(self::FORM_FIELDS, '');
    }

    /** @param array<int, string> $uuids */
    private function dispatchInvitations(array $uuids): void
    {
        $recipientColumn = $this->validate([
            'recipientColumn' => ['required', Rule::in(['email', 'outlook_email'])],
        ])['recipientColumn'];
        $adminEmail = auth()->user()->email;
        $memberEmails = config('mail.attendee_dev_mode')
            ? []
            : Attendee::query()->whereIn('uuid', $uuids)->pluck($recipientColumn, 'uuid')->all();
        $queuedUuids = DB::transaction(function () use ($uuids): array {
            $queuedUuids = [];

            foreach ($uuids as $uuid) {
                $updated = Attendee::query()
                    ->whereKey($uuid)
                    ->where(fn ($query) => $query
                        ->whereNull('invitation_email_status')
                        ->orWhere('invitation_email_status', 'failed'))
                    ->update([
                        'invitation_email_status' => 'queued',
                        'invitation_email_sent_at' => null,
                        'invitation_email_error' => null,
                    ]);

                if ($updated === 1) {
                    $queuedUuids[] = $uuid;
                }
            }

            return $queuedUuids;
        });

        foreach ($queuedUuids as $uuid) {
            SendMemberOrientationInvitation::dispatch(
                $uuid,
                config('mail.attendee_dev_mode') ? $adminEmail : ($memberEmails[$uuid] ?? ''),
            );
        }

        $queued = count($queuedUuids);

        if ($this->qrAttendee && in_array($this->qrAttendee['uuid'], $uuids, true) && $queued > 0) {
            $this->qrAttendee['invitation_email_status'] = 'queued';
            $this->qrAttendee['invitation_email_sent_at'] = null;
            $this->qrAttendee['invitation_email_error'] = null;
        }

        Flux::toast(
            variant: $queued > 0 ? 'success' : 'warning',
            text: $queued > 0
                ? trans_choice(':count invitation queued.|:count invitations queued.', $queued, ['count' => $queued])
                : __('No invitations were queued. They may already be queued or sent.'),
        );
    }
};
?>

<section class="w-full space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Attendees') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Search attendees and manage their details.') }}</flux:text>
        </div>

        <div class="grid grid-cols-2 gap-2 sm:flex sm:items-end">
            <flux:select wire:model="recipientColumn" :label="__('Invitation email')">
                <flux:select.option value="email">{{ __('Email') }}</flux:select.option>
                <flux:select.option value="outlook_email">{{ __('Outlook email') }}</flux:select.option>
            </flux:select>
            <flux:button class="min-w-0 whitespace-normal! sm:whitespace-nowrap!" wire:click="downloadSelectedQrs" wire:loading.attr="disabled" wire:target="downloadSelectedQrs">
                {{ __('Download selected QRs') }}
            </flux:button>
            <flux:button class="min-w-0 whitespace-normal! sm:whitespace-nowrap!" wire:click="queueSelectedInvitations" wire:loading.attr="disabled" wire:target="queueSelectedInvitations">
                {{ __('Queue selected emails') }}
            </flux:button>
            <flux:button class="min-w-0 whitespace-normal! sm:whitespace-nowrap!" wire:click="export">{{ __('Export CSV') }}</flux:button>
            <flux:button class="col-span-2 w-full sm:w-auto" variant="primary" wire:click="create">{{ __('Add attendee') }}</flux:button>
        </div>
    </div>

    <flux:card class="space-y-4">
        <div>
            <flux:heading size="lg">{{ __('Import CSV') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Up to 5,000 rows. Existing or repeated UUIDs reject the entire import. Receipt state is not imported.') }}</flux:text>
        </div>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <flux:input wire:model="csv" type="file" accept=".csv,text/csv" :label="__('CSV file')" />
            <flux:button wire:click="import" wire:loading.attr="disabled" wire:target="csv,import">{{ __('Import') }}</flux:button>
        </div>

        @if ($errors->has('csv'))
            <ul class="list-disc space-y-1 pl-5 text-sm text-red-600 dark:text-red-400">
                @foreach ($errors->get('csv') as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif
    </flux:card>

    <div class="grid gap-4 sm:grid-cols-[1fr_12rem]">
        <flux:input wire:model.live.debounce.300ms="search" :label="__('Search')" :placeholder="__('UUID or name')" />
        <flux:select wire:model.live="status" :label="__('Receipt status')">
            <flux:select.option value="all">{{ __('All') }}</flux:select.option>
            <flux:select.option value="received">{{ __('Received') }}</flux:select.option>
            <flux:select.option value="unreceived">{{ __('Unreceived') }}</flux:select.option>
        </flux:select>
    </div>

    <flux:card>
        <flux:table :paginate="$this->attendees" bleed>
            <flux:table.columns>
                <flux:table.column><span class="sr-only">{{ __('Select') }}</span></flux:table.column>
                <flux:table.column>{{ __('Attendee') }}</flux:table.column>
                <flux:table.column>{{ __('AIUB ID') }}</flux:table.column>
                <flux:table.column>{{ __('T-shirt size') }}</flux:table.column>
                <flux:table.column>{{ __('Phone number') }}</flux:table.column>
                <flux:table.column>{{ __('Receipt') }}</flux:table.column>
                <flux:table.column>{{ __('Email') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->attendees as $attendee)
                    <flux:table.row :key="$attendee->uuid">
                        <flux:table.cell>
                            <flux:checkbox wire:model="selected" value="{{ $attendee->uuid }}" :aria-label="__('Select :name', ['name' => $attendee->name ?: $attendee->uuid])" />
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex flex-col">
                                <span class="font-medium">{{ $attendee->name ?: __('Unnamed attendee') }}</span>
                                <span class="font-mono text-xs text-zinc-500 dark:text-zinc-400">{{ $attendee->uuid }}</span>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $attendee->aiub_id ?: '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $attendee->size ?: '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $attendee->phone_no ?: '—' }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="flex flex-col gap-1">
                                <flux:badge size="sm" :color="$attendee->received ? 'green' : 'zinc'">
                                    {{ $attendee->received ? __('Received') : __('Unreceived') }}
                                </flux:badge>
                                @if ($attendee->received_at)
                                    <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $attendee->received_at->toDayDateTimeString() }}</span>
                                @endif
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($attendee->invitation_email_status)
                                <div class="flex flex-col gap-1">
                                    <flux:badge size="sm" :color="match ($attendee->invitation_email_status) { 'sent' => 'green', 'failed' => 'red', default => 'amber' }">
                                        {{ __(ucfirst($attendee->invitation_email_status)) }}
                                    </flux:badge>
                                    @if (in_array($attendee->invitation_email_status, ['sent', 'failed'], true))
                                        <flux:button size="sm" wire:click="resetInvitation(@js($attendee->uuid))" wire:confirm="{{ __('Reset this email to not queued?') }}">
                                            {{ __('Reset') }}
                                        </flux:button>
                                    @endif
                                    @if ($attendee->invitation_email_error)
                                        <span class="max-w-48 truncate text-xs text-red-600 dark:text-red-400" title="{{ $attendee->invitation_email_error }}">{{ $attendee->invitation_email_error }}</span>
                                    @endif
                                </div>
                            @else
                                <span class="text-zinc-500">—</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex justify-end gap-2">
                                <flux:button size="sm" wire:click="previewQr(@js($attendee->uuid))">{{ __('View QR') }}</flux:button>
                                <flux:button size="sm" wire:click="edit(@js($attendee->uuid))">{{ __('Edit') }}</flux:button>
                                <flux:button size="sm" variant="danger" wire:click="delete(@js($attendee->uuid))" wire:confirm="{{ __('Delete this attendee?') }}">
                                    {{ __('Delete') }}
                                </flux:button>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="8">{{ __('No attendees found.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        <flux:error name="selected" />
    </flux:card>

    <flux:modal wire:model="showQr" variant="flyout" position="right" class="w-full md:w-[28rem]">
        @if ($qrAttendee && $qrImage)
            <div class="space-y-6">
                <div>
                    <flux:heading size="xl">{{ $qrAttendee['name'] ?: __('Unnamed attendee') }}</flux:heading>
                    <flux:text class="mt-1 font-mono text-xs">{{ $qrAttendee['uuid'] }}</flux:text>
                </div>

                <div class="rounded-xl bg-white p-4 ring ring-zinc-200 dark:ring-zinc-700">
                    <img src="{{ $qrImage }}" alt="{{ __('QR code for :name', ['name' => $qrAttendee['name'] ?: $qrAttendee['uuid']]) }}" class="mx-auto aspect-square w-full max-w-80" />
                </div>

                <dl class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    <div class="flex justify-between gap-4 py-3">
                        <dt class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('AIUB ID') }}</dt>
                        <dd class="text-sm font-medium">{{ $qrAttendee['aiub_id'] ?: '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 py-3">
                        <dt class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('T-shirt size') }}</dt>
                        <dd class="text-sm font-medium">{{ $qrAttendee['size'] ?: '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 py-3">
                        <dt class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Phone number') }}</dt>
                        <dd class="text-sm font-medium">{{ $qrAttendee['phone_no'] ?: '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 py-3">
                        <dt class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Receipt') }}</dt>
                        <dd class="flex flex-col items-end gap-1">
                            <flux:badge size="sm" :color="$qrAttendee['received'] ? 'green' : 'zinc'">
                                {{ $qrAttendee['received'] ? __('Received') : __('Unreceived') }}
                            </flux:badge>
                            @if ($qrAttendee['received_at'])
                                <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $qrAttendee['received_at'] }}</span>
                            @endif
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4 py-3">
                        <dt class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Email delivery') }}</dt>
                        <dd class="flex max-w-64 flex-col items-end gap-1 text-right">
                            <flux:badge size="sm" :color="match ($qrAttendee['invitation_email_status']) { 'sent' => 'green', 'failed' => 'red', null => 'zinc', default => 'amber' }">
                                {{ $qrAttendee['invitation_email_status'] ? __(ucfirst($qrAttendee['invitation_email_status'])) : __('Not queued') }}
                            </flux:badge>
                            @if ($qrAttendee['invitation_email_sent_at'])
                                <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $qrAttendee['invitation_email_sent_at'] }}</span>
                            @endif
                            @if ($qrAttendee['invitation_email_error'])
                                <span class="text-xs text-red-600 dark:text-red-400">{{ $qrAttendee['invitation_email_error'] }}</span>
                            @endif
                        </dd>
                    </div>
                </dl>

                <flux:button variant="primary" class="w-full" wire:click="downloadQr(@js($qrAttendee['uuid']))">
                    {{ __('Download QR') }}
                </flux:button>

                <flux:button class="w-full" wire:click="queueInvitation(@js($qrAttendee['uuid']))" wire:loading.attr="disabled" wire:target="queueInvitation">
                    {{ config('mail.attendee_dev_mode') ? __('Queue test email to me') : __('Queue invitation email') }}
                </flux:button>

                @if (config('mail.attendee_dev_mode'))
                    <flux:text class="text-center text-xs">
                        {{ __('Development mode: this email goes only to your admin address, never to the member.') }}
                    </flux:text>
                @endif
            </div>
        @endif
    </flux:modal>

    <flux:modal wire:model="showForm" class="md:w-2xl" scroll="body">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingUuid ? __('Edit attendee') : __('Add attendee') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Receipt status is managed only by the scanner.') }}</flux:text>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="form.uuid" :label="__('UUID')" />
                <flux:input wire:model="form.name" :label="__('Name')" />
                <flux:input wire:model="form.aiub_id" :label="__('AIUB ID')" />
                <flux:input wire:model="form.email" type="email" :label="__('Email')" />
                <flux:input wire:model="form.phone_no" :label="__('Phone number')" />
                <flux:input wire:model="form.gender" :label="__('Gender')" />
                <flux:input wire:model="form.size" :label="__('T-shirt size')" />
                <flux:input wire:model="form.dept" :label="__('Department')" />
                <flux:input wire:model="form.amount_paid" :label="__('Amount paid')" />
                <flux:input wire:model="form.membership_cost" type="number" min="0" step="0.01" :label="__('Membership cost')" />
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
