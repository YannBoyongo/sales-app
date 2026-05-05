@props([
    'maxWidth' => 'max-w-2xl',
    'eyebrow' => 'Caisse',
    'title' => null,
    'description' => null,
    'contextLine' => null,
    'withCard' => true,
])

<div class="relative -mx-4 -mt-4 px-4 pt-2 pb-10 lg:-mx-8 lg:px-8 lg:pt-0 lg:pb-12">
    <div
        class="pointer-events-none absolute inset-0 overflow-hidden rounded-none lg:rounded-3xl"
        aria-hidden="true"
    >
        <div class="absolute -left-24 top-0 h-72 w-72 rounded-full bg-primary/10 blur-3xl"></div>
        <div class="absolute -right-16 top-24 h-64 w-64 rounded-full bg-violet-200/40 blur-3xl"></div>
        <div class="absolute bottom-0 left-1/3 h-48 w-96 rounded-full bg-amber-100/50 blur-3xl"></div>
    </div>

    <div class="relative mx-auto {{ $maxWidth }}">
        @isset($header)
            <div class="mb-8">{{ $header }}</div>
        @elseif ($title)
            <div class="mb-8 text-center lg:text-left">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-primary/90">{{ $eyebrow }}</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight text-neutral-900 sm:text-4xl">{{ $title }}</h1>
                @if ($description)
                    <p class="mx-auto mt-3 max-w-xl text-base leading-relaxed text-neutral-600 lg:mx-0">{{ $description }}</p>
                @endif
                @if ($contextLine)
                    <p class="mx-auto mt-3 inline-flex flex-wrap items-center justify-center gap-x-2 gap-y-1 rounded-full border border-neutral-200/80 bg-white/70 px-4 py-1.5 text-sm text-neutral-700 shadow-sm backdrop-blur-sm lg:mx-0">
                        {!! $contextLine !!}
                    </p>
                @endif
            </div>
        @endif

        @isset($stepper)
            {{ $stepper }}
        @endisset

        @if ($withCard)
            <div
                class="rounded-2xl border border-neutral-200/90 bg-white/90 p-6 shadow-xl shadow-neutral-900/5 ring-1 ring-neutral-900/5 backdrop-blur-sm sm:p-8"
            >
                {{ $slot }}

                @isset($footer)
                    <div class="mt-8 flex flex-wrap items-center justify-center gap-x-6 gap-y-2 border-t border-neutral-100 pt-6 text-sm sm:justify-start">
                        {{ $footer }}
                    </div>
                @endisset
            </div>
        @else
            {{ $slot }}

            @isset($footer)
                <div class="mt-8 flex flex-wrap items-center justify-center gap-x-6 gap-y-2 border-t border-transparent pt-6 text-sm sm:justify-start">
                    {{ $footer }}
                </div>
            @endisset
        @endif
    </div>
</div>
