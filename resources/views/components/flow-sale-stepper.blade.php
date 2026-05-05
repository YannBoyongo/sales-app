@props([
    'step' => 1,
    'totalSteps' => 3,
])

@php
    $all = [
        1 => ['label' => 'Branche', 'short' => '1'],
        2 => ['label' => 'Terminal', 'short' => '2'],
        3 => ['label' => 'Département', 'short' => '3'],
        4 => ['label' => 'Saisie', 'short' => '4'],
    ];
    $steps = array_slice($all, 0, max(1, min(4, (int) $totalSteps)), true);
    $lastStepNum = array_key_last($steps);
@endphp

<nav class="mb-8" aria-label="Étapes du parcours vente">
    <ol class="flex items-center justify-center gap-2 sm:justify-start sm:gap-0">
        @foreach ($steps as $num => $meta)
            @php $active = (int) $step === $num; $done = (int) $step > $num; @endphp
            <li class="flex items-center {{ $num !== $lastStepNum ? 'sm:flex-1' : '' }}">
                <div class="flex flex-col items-center gap-1.5 sm:flex-row sm:gap-3">
                    <span
                        @class([
                            'flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-bold tabular-nums transition-all',
                            'bg-primary text-white shadow-md shadow-primary/25' => $active,
                            'bg-emerald-500 text-white shadow-md shadow-emerald-500/20' => $done,
                            'border-2 border-neutral-200 bg-white text-neutral-400' => ! $active && ! $done,
                        ])
                    >
                        @if ($done)
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                            </svg>
                        @else
                            {{ $meta['short'] }}
                        @endif
                    </span>
                    <span
                        @class([
                            'hidden text-xs font-semibold uppercase tracking-wide sm:inline',
                            'text-primary' => $active,
                            'text-emerald-700' => $done,
                            'text-neutral-400' => ! $active && ! $done,
                        ])
                    >
                        {{ $meta['label'] }}
                    </span>
                </div>
                @if ($num !== $lastStepNum)
                    <div
                        class="mx-2 hidden h-0.5 min-w-[2rem] flex-1 rounded-full sm:block {{ $done ? 'bg-emerald-200' : 'bg-neutral-200' }}"
                        aria-hidden="true"
                    ></div>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
