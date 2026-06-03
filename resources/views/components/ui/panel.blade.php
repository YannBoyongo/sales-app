@props(['padding' => true])

<div {{ $attributes->merge(['class' => 'app-panel']) }}>
    @if (isset($header))
        <div class="app-panel-header">
            {{ $header }}
        </div>
    @endif
    <div @class(['app-panel-body' => $padding])>
        {{ $slot }}
    </div>
</div>
