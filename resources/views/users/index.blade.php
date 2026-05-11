<x-app-layout>
    <x-slot name="header">Gestion des utilisateurs</x-slot>

    <x-page-header title="Utilisateurs" action="Nouvel utilisateur" :action-href="route('users.create')" />

    @if ($errors->has('user'))
        <div class="mb-4 rounded-md border border-neutral-300 bg-neutral-50 px-4 py-3 text-sm text-neutral-800">{{ $errors->first('user') }}</div>
    @endif

    <div class="overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-neutral-200 text-sm">
            <thead class="bg-neutral-50 text-left text-xs font-semibold uppercase tracking-wide text-neutral-600">
                <tr>
                    <th class="px-4 py-3">Nom</th>
                    <th class="px-4 py-3">Nom d’utilisateur</th>
                    <th class="px-4 py-3">E-mail</th>
                    <th class="px-4 py-3">Rôles</th>
                    <th class="px-4 py-3">Branche</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100">
                @foreach ($users as $user)
                    <tr class="hover:bg-neutral-50/80">
                        <td class="px-4 py-3 font-medium text-neutral-900">{{ $user->name }}</td>
                        <td class="px-4 py-3 font-mono text-xs text-neutral-700">{{ $user->username ?? '—' }}</td>
                        <td class="px-4 py-3 text-neutral-600">{{ $user->email }}</td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-1">
                                @forelse ($user->roles as $role)
                                    @if ($role->slug === 'admin')
                                        <span class="rounded bg-primary px-2 py-0.5 text-xs font-medium text-white">{{ $role->name }}</span>
                                    @elseif ($role->slug === 'accountant')
                                        <span class="rounded border border-sky-300 bg-sky-50 px-2 py-0.5 text-xs font-semibold text-sky-900">{{ $role->name }}</span>
                                    @else
                                        <span class="rounded border border-neutral-200 bg-white px-2 py-0.5 text-xs text-neutral-700">{{ $role->name }}</span>
                                    @endif
                                @empty
                                    <span class="text-neutral-400">—</span>
                                @endforelse
                            </div>
                        </td>
                        <td class="px-4 py-3 text-neutral-600">
                            @if ($user->canBypassBranchScope())
                                <span class="text-neutral-400">—</span>
                            @elseif ($user->branch)
                                {{ $user->branch->name }}
                            @else
                                <span class="text-amber-700">Non assigné</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right space-x-2">
                            <a href="{{ route('users.edit', $user) }}" class="text-neutral-700 underline-offset-2 hover:underline">Modifier</a>
                            <form action="{{ route('users.destroy', $user) }}" method="POST" class="inline" onsubmit="return confirm('Supprimer cet utilisateur ?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-neutral-600 underline-offset-2 hover:underline">Supprimer</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $users->links() }}</div>
</x-app-layout>
