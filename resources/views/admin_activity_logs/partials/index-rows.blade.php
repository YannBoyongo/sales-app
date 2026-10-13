@forelse ($logs as $log)
    <tr class="transition-colors hover:bg-neutral-50/80" data-log-id="{{ $log->id }}">
        <td class="px-4 py-3 text-center">
            <input
                type="checkbox"
                class="admin-log-checkbox rounded border-neutral-300 text-primary focus:ring-primary"
                value="{{ $log->id }}"
                aria-label="Sélectionner l’entrée du {{ $log->created_at->translatedFormat('d/m/Y H:i') }}"
            />
        </td>
        <td class="whitespace-nowrap px-4 py-3 tabular-nums text-neutral-700">
            {{ $log->created_at->translatedFormat('d/m/Y H:i') }}
        </td>
        <td class="whitespace-nowrap px-4 py-3 font-medium text-neutral-900">
            {{ $log->user?->name ?? '—' }}
        </td>
        <td class="whitespace-nowrap px-4 py-3 text-neutral-700">
            {{ $log->branch?->name ?? '—' }}
        </td>
        <td class="whitespace-nowrap px-4 py-3 font-mono text-xs text-neutral-600">
            {{ $log->action }}
        </td>
        <td class="px-4 py-3 text-neutral-900">
            {{ $log->description }}
        </td>
    </tr>
@empty
    @if (! request()->boolean('infinite'))
        <tr data-empty>
            <td colspan="6" class="px-4 py-10 text-center text-neutral-500">
                Aucune action enregistrée pour le moment.
            </td>
        </tr>
    @endif
@endforelse
