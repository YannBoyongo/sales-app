<thead class="text-left text-xs font-semibold uppercase tracking-wide">
    <tr>
        <th class="px-4 py-3">N° bon</th>
        <th class="px-4 py-3">Date</th>
        <th class="px-4 py-3">Description</th>
        <th class="px-4 py-3">Type</th>
        <th class="px-4 py-3 text-right">Montant</th>
        @if (auth()->user()?->isAdmin())
            <th class="px-4 py-3 text-right">Action</th>
        @endif
        @if (auth()->user()?->canAccessAccounting())
            <th class="px-4 py-3 text-right">Comptabilité</th>
        @endif
    </tr>
</thead>
