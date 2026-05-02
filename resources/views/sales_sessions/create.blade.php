<x-app-layout>
    <x-slot name="header">Nouvelle vente</x-slot>

    <x-page-header title="Nouvelle vente (session journalière)" />

    @if ($errors->has('session'))
        <div class="mb-4 rounded-md border border-neutral-300 bg-neutral-50 px-4 py-3 text-sm text-neutral-800">{{ $errors->first('session') }}</div>
    @endif

    <form action="{{ route('sales-sessions.store') }}" method="POST" class="max-w-lg space-y-4 rounded-lg border border-neutral-200 bg-white p-6 shadow-sm">
        @csrf
        <p class="text-sm text-neutral-600">Une seule session par jour et par branche. Une seule session ouverte à la fois pour votre branche.</p>
        @isset($userBranch)
            <p class="text-sm text-neutral-800"><span class="font-medium">Branche :</span> {{ $userBranch->name }}</p>
        @else
            <div>
                <x-input-label for="branch_id" value="Branche" />
                <select id="branch_id" name="branch_id" class="mt-1 block w-full rounded-md border-neutral-300 shadow-sm focus:border-primary focus:ring-primary" required>
                    <option value="">— Choisir —</option>
                    @foreach ($branches as $b)
                        <option value="{{ $b->id }}" @selected(old('branch_id') == $b->id)>{{ $b->name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('branch_id')" class="mt-2" />
            </div>
        @endisset
        <div class="flex gap-3">
            <x-primary-button>Nouvelle vente</x-primary-button>
            <a href="{{ route('sales-sessions.index') }}" class="inline-flex items-center rounded-md border border-neutral-300 bg-white px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">Annuler</a>
        </div>
    </form>
</x-app-layout>
