@props(['title', 'action' => null, 'actionHref' => null])

<div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
    <h1 class="text-2xl font-semibold text-neutral-900">{{ $title }}</h1>
    @if ($action && $actionHref)
        <a href="{{ $actionHref }}" class="inline-flex items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2">
            {{ $action }}
        </a>
    @endif
</div>
