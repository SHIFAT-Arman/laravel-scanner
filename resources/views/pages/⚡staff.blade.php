<?php

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Staff')] class extends Component
{
    use PasswordValidationRules, ProfileValidationRules;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    /** @return Collection<int, User> */
    #[Computed]
    public function staff(): Collection
    {
        return User::query()
            ->where('role', 'staff')
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $validated = $this->validate([
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ]);

        $staff = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);
        $staff->forceFill(['role' => 'staff'])->save();
        $staff->markEmailAsVerified();

        $this->reset();
        unset($this->staff);

        Flux::toast(variant: 'success', text: __('Staff account created.'));
    }
};
?>

<section class="w-full space-y-6">
    <div>
        <flux:heading size="xl">{{ __('Staff') }}</flux:heading>
        <flux:text class="mt-2">{{ __('Create accounts for people who will use the scanner.') }}</flux:text>
    </div>

    <flux:card>
        <form wire:submit="save" class="space-y-6">
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="name" :label="__('Name')" autocomplete="name" />
                <flux:input wire:model="email" type="email" :label="__('Email')" autocomplete="email" />
                <flux:input wire:model="password" type="password" :label="__('Temporary password')" autocomplete="new-password" viewable />
                <flux:input wire:model="password_confirmation" type="password" :label="__('Confirm password')" autocomplete="new-password" viewable />
            </div>

            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                {{ __('Add staff') }}
            </flux:button>
        </form>
    </flux:card>

    <flux:card>
        <flux:table bleed>
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Email') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->staff as $staff)
                    <flux:table.row :key="$staff->id">
                        <flux:table.cell class="font-medium">{{ $staff->name }}</flux:table.cell>
                        <flux:table.cell>{{ $staff->email }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="2">{{ __('No staff accounts yet.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</section>
