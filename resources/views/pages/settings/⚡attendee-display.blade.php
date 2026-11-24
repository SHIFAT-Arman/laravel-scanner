<?php

use App\Models\AttendeeDisplaySetting;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Attendee display settings')] class extends Component
{
    /** @var array<int, string> */
    public array $fields = [];

    public function mount(): void
    {
        $this->fields = AttendeeDisplaySetting::enabledFields();
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $validated = $this->validate([
            'fields' => ['array'],
            'fields.*' => ['string', Rule::in(array_keys(AttendeeDisplaySetting::SUPPORTED_FIELDS))],
        ]);

        AttendeeDisplaySetting::query()->updateOrCreate(['id' => 1], $validated);

        Flux::toast(variant: 'success', text: __('Display fields updated.'));
    }
};
?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Attendee display settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Attendee display')" :subheading="__('Choose the attendee details shown after a scan')">
        <form wire:submit="save" class="my-6 space-y-6">
            <flux:checkbox.group wire:model="fields" :label="__('Visible fields')">
                @foreach (AttendeeDisplaySetting::SUPPORTED_FIELDS as $field => $label)
                    <flux:checkbox :value="$field" :label="$label" wire:key="display-field-{{ $field }}" />
                @endforeach
            </flux:checkbox.group>

            <flux:error name="fields.*" />

            <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
        </form>
    </x-pages::settings.layout>
</section>
