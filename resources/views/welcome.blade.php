<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => __('Members Orientation 2026')])
    </head>
    <body class="min-h-screen bg-[#f7fafc] text-slate-900 antialiased dark:bg-ieee-navy dark:text-white">
        <div class="relative isolate min-h-screen overflow-hidden">
            <div aria-hidden="true" class="absolute inset-x-0 top-0 -z-10 h-96 bg-[radial-gradient(circle_at_top_right,rgba(0,166,214,0.18),transparent_55%)] dark:bg-[radial-gradient(circle_at_top_right,rgba(0,166,214,0.24),transparent_55%)]"></div>

            <header class="mx-auto flex w-full max-w-7xl items-center justify-between px-6 py-6 lg:px-8">
                <x-app-logo href="{{ route('home') }}" />

                @auth
                    <a href="{{ route('dashboard') }}" class="rounded-full bg-ieee-blue px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-[#004f7d] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-ieee-cyan">
                        {{ __('Open dashboard') }}
                    </a>
                @else
                    <a href="{{ route('login') }}" class="rounded-full bg-ieee-blue px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-[#004f7d] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-ieee-cyan">
                        {{ __('Log in') }}
                    </a>
                @endauth
            </header>

            <main class="mx-auto grid w-full max-w-7xl items-center gap-16 px-6 py-16 lg:grid-cols-[minmax(0,1fr)_26rem] lg:px-8 lg:py-24">
                <section class="max-w-3xl">
                    <p class="mb-5 text-sm font-semibold tracking-[0.24em] text-ieee-blue uppercase dark:text-ieee-cyan">
                        {{ __('IEEE AIUB Student Branch') }}
                    </p>
                    <h1 class="max-w-3xl text-5xl font-semibold tracking-[-0.04em] text-balance text-ieee-navy sm:text-6xl lg:text-7xl dark:text-white">
                        {{ __('Members Orientation 2026') }}
                    </h1>
                </section>

                <aside class="relative overflow-hidden rounded-3xl border border-ieee-blue/15 bg-white p-8 shadow-2xl shadow-ieee-navy/10 dark:border-white/10 dark:bg-white/5 dark:shadow-black/30">
                    <div aria-hidden="true" class="absolute top-0 right-0 h-32 w-32 translate-x-12 -translate-y-12 rounded-full border-[18px] border-ieee-cyan/15"></div>
                    <p class="text-xs font-semibold tracking-[0.22em] text-ieee-blue uppercase dark:text-ieee-cyan">{{ __('Event details') }}</p>
                    <dl class="mt-8 grid gap-7">
                        <div>
                            <dt class="text-sm text-slate-500 dark:text-slate-400">{{ __('Date') }}</dt>
                            <dd class="mt-1 text-xl font-semibold">{{ __('26 November 2026') }}</dd>
                        </div>
                        <div class="border-t border-slate-200 pt-7 dark:border-white/10">
                            <dt class="text-sm text-slate-500 dark:text-slate-400">{{ __('Time') }}</dt>
                            <dd class="mt-1 text-xl font-semibold">{{ __('3:30 PM (GMT +6)') }}</dd>
                        </div>
                        <div class="border-t border-slate-200 pt-7 dark:border-white/10">
                            <dt class="text-sm text-slate-500 dark:text-slate-400">{{ __('Venue') }}</dt>
                            <dd class="mt-1 text-xl font-semibold">{{ __('Multipurpose Hall, Annex-7, AIUB') }}</dd>
                        </div>
                    </dl>
                </aside>
            </main>
        </div>

        @fluxScripts
    </body>
</html>
