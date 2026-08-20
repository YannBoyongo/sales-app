<thead class="text-left text-xs font-semibold uppercase tracking-wide">
    <tr>
        @if ($selectable ?? false)
            <th class="w-12 px-4 py-3 text-center">
                <input
                    type="checkbox"
                    class="rounded border-neutral-300 text-primary focus:ring-primary"
                    aria-label="Sélectionner tous les bons chargés et modifiables"
                    :checked="allLoadedApprovedSelected()"
                    @change="toggleAllLoadedApproved($event.target.checked)"
                />
            </th>
        @endif
        <th class="px-4 py-3">N° bon</th>
        <th class="px-4 py-3">Date</th>
        <th class="px-4 py-3">Description</th>
        <th class="px-4 py-3">Type</th>
        <th class="px-4 py-3 text-right">Montant</th>
        <th class="px-4 py-3">Branche</th>
        <th class="px-4 py-3">Terminal</th>
        @if (auth()->user()?->hasApplicationAdminAccess())
            <th class="px-4 py-3 text-right">Action</th>
        @endif
        @if (auth()->user()?->canAccessAccounting())
            <th class="px-4 py-3 text-right">Comptabilité</th>
        @endif
    </tr>
</thead>
