@forelse ($entries as $entry)
    @include('suivi-cout.partials.entry-row', ['entry' => $entry])
@empty
    <tr data-empty>
        <td colspan="8" class="px-4 py-14 text-center text-neutral-500">Aucune écriture enregistrée.</td>
    </tr>
@endforelse
