@props([
    'maxWidth' => 'max-w-2xl',
    'eyebrow' => 'Caisse',
    'title' => null,
    'description' => null,
    'contextLine' => null,
    'withCard' => true,
])

<div class="relative -mx-4 -mt-2 px-4 pb-8 pt-2 lg:-mx-8 lg:px-8 lg:pb-10">
    <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
        <div class="absolute -left-32 top-0 h-80 w-80 rounded-full bg-primary/[0.08] blur-3xl"></div>
        <div class="absolute -right-20 top-20 h-72 w-72 rounded-full bg-primary-light/25 blur-3xl"></div>
    </div>

    <div class="relative mx-auto {{ $maxWidth }}">
        @isset($header)
            <div class="mb-6 lg:mb-8">{{ $header }}</div>
        @elseif ($title)
            <div class="mb-6 text-center lg:mb-8 lg:text-left">
                <p class="app-page-eyebrow">{{ $eyebrow }}</p>
                <h1 class="app-page-title">{{ $title }}</h1>
                @if ($description)
                    <p class="app-page-desc mx-auto lg:mx-0">{{ $description }}</p>
                @endif
                @if ($contextLine)
                    <p class="mx-auto mt-3 inline-flex flex-wrap items-center justify-center gap-x-2 gap-y-1 rounded-full border border-slate-200 bg-white px-4 py-1.5 text-sm text-slate-600 shadow-sm lg:mx-0">
                        {!! $contextLine !!}
                    </p>
                @endif
            </div>
        @endif

        @isset($stepper)
            {{ $stepper }}
        @endisset

        @if ($withCard)
            <div class="app-panel app-panel-body sm:p-8">
                {{ $slot }}

                @isset($footer)
                    <div class="mt-8 flex flex-wrap items-center justify-center gap-x-6 gap-y-2 border-t border-slate-100 pt-6 text-sm sm:justify-start">
                        {{ $footer }}
                    </div>
                @endisset
            </div>
        @else
            {{ $slot }}

            @isset($footer)
                <div class="mt-8 flex flex-wrap items-center justify-center gap-x-6 gap-y-2 pt-6 text-sm sm:justify-start">
                    {{ $footer }}
                </div>
            @endisset
        @endif
    </div>
</div>
