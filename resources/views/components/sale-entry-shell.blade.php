@props([
    'step' => 1,
    'totalSteps' => 3,
    'title',
    'description' => null,
    'contextLine' => null,
])

<x-caisse-flow :title="$title" :description="$description" :context-line="$contextLine">
    <x-slot name="stepper">
        <x-flow-sale-stepper :step="$step" :total-steps="$totalSteps" />
    </x-slot>

    {{ $slot }}

    @isset($footer)
        <x-slot name="footer">{{ $footer }}</x-slot>
    @endisset
</x-caisse-flow>
