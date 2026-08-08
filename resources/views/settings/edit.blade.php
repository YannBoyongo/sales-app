<x-app-layout>
    <x-slot name="header">Paramètre</x-slot>

    <div class="mb-6">
        <h1 class="app-page-title">Paramètre</h1>
        <p class="app-page-desc">Modifier les informations de la boutique.</p>
    </div>

    <section class="app-panel app-panel-body">
        <form action="{{ route('parametre.update') }}" method="POST" enctype="multipart/form-data" class="grid gap-4 md:grid-cols-2">
            @csrf
            @method('PATCH')

            <div class="md:col-span-2">
                <x-input-label for="shopname" value="Nom de la boutique" />
                <x-text-input id="shopname" name="shopname" type="text" class="mt-1 block w-full" :value="old('shopname', $setting->shopname)" required />
                <x-input-error :messages="$errors->get('shopname')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="phone" value="Téléphone" />
                <x-text-input id="phone" name="phone" type="text" class="mt-1 block w-full" :value="old('phone', $setting->phone)" required />
                <x-input-error :messages="$errors->get('phone')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="email" value="Email" />
                <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $setting->email)" required />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <div class="md:col-span-2">
                <x-input-label for="address" value="Adresse" />
                <x-text-input id="address" name="address" type="text" class="mt-1 block w-full" :value="old('address', $setting->address)" required />
                <x-input-error :messages="$errors->get('address')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="rccm" value="RCCM" />
                <x-text-input id="rccm" name="rccm" type="text" class="mt-1 block w-full" :value="old('rccm', $setting->rccm)" required />
                <x-input-error :messages="$errors->get('rccm')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="idnat" value="IDNAT" />
                <x-text-input id="idnat" name="idnat" type="text" class="mt-1 block w-full" :value="old('idnat', $setting->idnat)" required />
                <x-input-error :messages="$errors->get('idnat')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="nif" value="NIF" />
                <x-text-input id="nif" name="nif" type="text" class="mt-1 block w-full" :value="old('nif', $setting->nif)" required />
                <x-input-error :messages="$errors->get('nif')" class="mt-2" />
            </div>

            <div
                x-data="{
                    previewUrl: null,
                    revoke() {
                        if (this.previewUrl) {
                            URL.revokeObjectURL(this.previewUrl);
                            this.previewUrl = null;
                        }
                    },
                    onFile(e) {
                        this.revoke();
                        const f = e.target.files?.[0];
                        if (f && f.type.startsWith('image/')) {
                            this.previewUrl = URL.createObjectURL(f);
                        }
                    }
                }"
            >
                <x-input-label for="logo" value="Logo" />
                <input
                    id="logo"
                    name="logo"
                    type="file"
                    accept="image/*"
                    class="mt-1 block w-full rounded-md border-neutral-300 shadow-sm focus:border-primary focus:ring-primary"
                    x-on:change="onFile($event)"
                />
                <div class="mt-3 space-y-2">
                    <template x-if="previewUrl">
                        <div>
                            <p class="text-xs font-medium text-neutral-500">Aperçu (avant enregistrement)</p>
                            <img x-bind:src="previewUrl" alt="" class="mt-1 h-20 w-auto max-w-full rounded border border-primary/30 bg-white p-1 object-contain" />
                        </div>
                    </template>
                    @if ($setting->logo)
                        <div x-show="!previewUrl" x-cloak class="flex flex-wrap items-end gap-3">
                            <div>
                                <p class="text-xs font-medium text-neutral-500">Logo enregistré</p>
                                <img src="{{ asset('storage/'.$setting->logo) }}" alt="Logo actuel" class="mt-1 h-16 w-auto rounded border border-neutral-200 bg-white p-1 object-contain" />
                            </div>
                            <button
                                type="submit"
                                form="parametre-logo-delete"
                                class="app-btn-danger"
                                onclick="return confirm('Supprimer le logo enregistré ?');"
                            >
                                Supprimer le logo
                            </button>
                        </div>
                    @endif
                </div>
                <x-input-error :messages="$errors->get('logo')" class="mt-2" />
            </div>

            <div class="md:col-span-2 pt-2 border-t border-neutral-200">
                <h2 class="text-base font-semibold text-neutral-900">Point de vente — déstockage</h2>
                <p class="mt-1 text-sm text-neutral-600">Emplacement où le stock sera déduit pour toutes les ventes du point de vente mobile.</p>

                @if ($setting->hasFieldPosStockLocation())
                    <p class="mt-3 rounded-lg border border-emerald-200/80 bg-emerald-50/80 px-4 py-3 text-sm text-emerald-900">
                        Configuration actuelle :
                        <strong>{{ $setting->fieldPosStockBranch?->name }}</strong>
                        ·
                        <strong>{{ $setting->fieldPosStockLocation?->name }}</strong>
                        @if ($setting->fieldPosStockLocation)
                            <span class="text-emerald-800/80">({{ \App\Models\Location::kindLabel($setting->fieldPosStockLocation->kind) }})</span>
                        @endif
                    </p>
                @endif

                @php
                    $selectedBranchId = old('field_pos_stock_branch_id', $setting->field_pos_stock_branch_id);
                    $selectedLocationId = old('field_pos_stock_location_id', $setting->field_pos_stock_location_id);
                @endphp

                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <div>
                        <x-input-label for="field_pos_stock_branch_id" value="Branche de déstockage" />
                        <select
                            id="field_pos_stock_branch_id"
                            name="field_pos_stock_branch_id"
                            class="mt-1 block w-full rounded-md border-neutral-300 shadow-sm focus:border-primary focus:ring-primary"
                        >
                            <option value="">— Non configuré —</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}" @selected((string) $selectedBranchId === (string) $branch->id)>
                                    {{ $branch->name }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('field_pos_stock_branch_id')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="field_pos_stock_location_id" value="Emplacement de déstockage" />
                        <select
                            id="field_pos_stock_location_id"
                            name="field_pos_stock_location_id"
                            class="mt-1 block w-full rounded-md border-neutral-300 shadow-sm focus:border-primary focus:ring-primary"
                            @if (! $selectedBranchId) disabled @endif
                        >
                            <option value="">Choisir…</option>
                            @foreach ($locationsByBranch as $branchId => $locations)
                                @foreach ($locations as $location)
                                    <option
                                        value="{{ $location['id'] }}"
                                        data-branch-id="{{ $branchId }}"
                                        @selected((string) $selectedLocationId === (string) $location['id'])
                                        @if ($selectedBranchId && (string) $branchId !== (string) $selectedBranchId) hidden @endif
                                    >
                                        {{ $location['name'] }} ({{ $location['kind'] }})
                                    </option>
                                @endforeach
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('field_pos_stock_location_id')" class="mt-2" />
                    </div>
                </div>
            </div>

            @push('scripts')
                <script>
                    document.addEventListener('DOMContentLoaded', () => {
                        const branchSelect = document.getElementById('field_pos_stock_branch_id');
                        const locationSelect = document.getElementById('field_pos_stock_location_id');
                        if (!branchSelect || !locationSelect) return;

                        const syncLocationOptions = (preserveSelection = false) => {
                            const branchId = branchSelect.value;
                            let hasVisible = false;

                            locationSelect.querySelectorAll('option[data-branch-id]').forEach((option) => {
                                const show = branchId !== '' && option.dataset.branchId === branchId;
                                option.hidden = !show;
                                if (show) hasVisible = true;
                            });

                            locationSelect.disabled = branchId === '';

                            if (preserveSelection) {
                                return;
                            }

                            if (!branchId || !hasVisible) {
                                locationSelect.value = '';
                            } else if (!locationSelect.querySelector(`option[value="${locationSelect.value}"]:not([hidden])`)) {
                                locationSelect.value = '';
                            }
                        };

                        branchSelect.addEventListener('change', () => syncLocationOptions(false));
                        syncLocationOptions(true);
                    });
                </script>
            @endpush

            <div class="md:col-span-2 pt-2">
                <x-primary-button>Enregistrer</x-primary-button>
            </div>
        </form>

        @if ($setting->logo)
            <form
                id="parametre-logo-delete"
                action="{{ route('parametre.logo.destroy') }}"
                method="POST"
                class="hidden"
            >
                @csrf
                @method('DELETE')
            </form>
        @endif
    </section>
</x-app-layout>
