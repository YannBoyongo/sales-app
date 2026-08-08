<form method="GET" action="{{ $action }}" class="app-filter-bar mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
    <div>
        <label for="date_from" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Date du</label>
        <input id="date_from" name="date_from" type="date" value="{{ $filters['date_from'] }}" class="mt-1 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary" />
    </div>
    <div>
        <label for="date_to" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Date au</label>
        <input id="date_to" name="date_to" type="date" value="{{ $filters['date_to'] }}" class="mt-1 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary" />
    </div>
    <div class="flex items-end gap-2 sm:col-span-2">
        <button type="submit" class="app-btn-primary">Filtrer</button>
        <a href="{{ $action }}" class="app-btn-secondary">Réinitialiser</a>
    </div>
</form>
