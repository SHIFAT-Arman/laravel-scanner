<x-layouts::app :title="__('Dashboard')">
    <div class="flex w-full flex-1 flex-col gap-8">
        <header>
            <p class="text-sm font-medium text-ieee-blue dark:text-ieee-cyan">{{ __('Members Orientation 2026') }}</p>
            <h1 class="mt-1 text-3xl font-semibold tracking-tight text-zinc-950 dark:text-white">{{ __('Attendance dashboard') }}</h1>
            <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">{{ __('A live summary of member check-ins.') }}</p>
        </header>

        <section aria-label="{{ __('Attendance totals') }}" class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Total attendees') }}</p>
                <p class="mt-3 text-4xl font-semibold tracking-tight text-zinc-950 dark:text-white">{{ number_format($total) }}</p>
            </div>
            <div class="rounded-2xl border border-ieee-blue/20 bg-ieee-blue/[0.04] p-6 shadow-sm dark:border-ieee-cyan/25 dark:bg-ieee-cyan/[0.06]">
                <p class="text-sm font-medium text-ieee-blue dark:text-ieee-cyan">{{ __('Received') }}</p>
                <p class="mt-3 text-4xl font-semibold tracking-tight text-ieee-navy dark:text-white">{{ number_format($received) }}</p>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Pending') }}</p>
                <p class="mt-3 text-4xl font-semibold tracking-tight text-zinc-950 dark:text-white">{{ number_format($pending) }}</p>
            </div>
        </section>

        <section class="grid gap-6 rounded-3xl border border-zinc-200 bg-white p-6 shadow-sm md:grid-cols-[minmax(0,1fr)_18rem] md:items-center md:p-8 dark:border-zinc-700 dark:bg-zinc-900">
            <div>
                <p class="text-sm font-semibold tracking-wide text-ieee-blue uppercase dark:text-ieee-cyan">{{ __('Attendance progress') }}</p>
                <h2 class="mt-2 text-2xl font-semibold text-zinc-950 dark:text-white">{{ __('Received versus pending') }}</h2>
                <p class="mt-3 max-w-xl text-sm leading-6 text-zinc-600 dark:text-zinc-400">
                    {{ $total === 0 ? __('Check-ins will appear here when attendee records are available.') : __('The chart updates whenever this page is loaded, using the current receipt status.') }}
                </p>

                <dl class="mt-7 flex flex-wrap gap-x-8 gap-y-4 text-sm">
                    <div class="flex items-center gap-2">
                        <span class="size-2.5 rounded-full bg-ieee-blue" aria-hidden="true"></span>
                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Received') }}</dt>
                        <dd class="font-semibold text-zinc-950 dark:text-white">{{ number_format($received) }}</dd>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="size-2.5 rounded-full bg-zinc-200 dark:bg-zinc-700" aria-hidden="true"></span>
                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Pending') }}</dt>
                        <dd class="font-semibold text-zinc-950 dark:text-white">{{ number_format($pending) }}</dd>
                    </div>
                </dl>
            </div>

            <div class="mx-auto grid size-56 place-items-center rounded-full" style="background: conic-gradient(#00629b {{ $percentage }}%, var(--color-zinc-200) 0)" role="img" aria-label="{{ __(':percentage% checked in: :received received and :pending pending', ['percentage' => $percentage, 'received' => $received, 'pending' => $pending]) }}">
                <div class="grid size-40 place-items-center rounded-full bg-white text-center shadow-inner dark:bg-zinc-900">
                    <div>
                        <p class="text-4xl font-semibold tracking-tight text-ieee-navy dark:text-white">{{ $percentage }}%</p>
                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('checked in') }}</p>
                    </div>
                </div>
            </div>
        </section>

        @if (auth()->user()->isAdmin())
            <section class="rounded-3xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <h2 class="text-xl font-semibold text-zinc-950 dark:text-white">{{ __('Staff members') }}</h2>

                <flux:table class="mt-4">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Name') }}</flux:table.column>
                        <flux:table.column>{{ __('Email') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse ($staff as $staffMember)
                            <flux:table.row :key="$staffMember->id">
                                <flux:table.cell class="font-medium">{{ $staffMember->name }}</flux:table.cell>
                                <flux:table.cell>{{ $staffMember->email }}</flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="2">{{ __('No staff accounts yet.') }}</flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </section>
        @endif
    </div>
</x-layouts::app>
