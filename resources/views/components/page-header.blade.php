@props(['title', 'action' => null, 'actionHref' => null, 'eyebrow' => null, 'description' => null])

<div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
    <div class="min-w-0">
        @if ($eyebrow)
            <p class="app-page-eyebrow">{{ $eyebrow }}</p>
        @endif
        <h1 class="{{ $eyebrow ? 'app-page-title' : 'text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl' }}">{{ $title }}</h1>
        @if ($description)
            <p class="app-page-desc">{{ $description }}</p>
        @endif
    </div>
    @if ($action && $actionHref)
        <a href="{{ $actionHref }}" class="app-btn-primary shrink-0">
            {{ $action }}
        </a>
    @endif
    @if (isset($actions))
        <div class="flex shrink-0 flex-wrap items-center gap-2">
            {{ $actions }}
        </div>
    @endif
</div>
