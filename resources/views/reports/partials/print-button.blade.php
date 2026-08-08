@props(['route'])

<a
    href="{{ route($route, request()->query()) }}"
    target="_blank"
    rel="noopener"
    {{ $attributes->merge(['class' => 'app-btn-secondary shrink-0']) }}
>
    Imprimer PDF
</a>
