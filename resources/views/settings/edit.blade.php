<x-app-layout>
    <x-slot name="header">Paramètre</x-slot>

    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-neutral-900">Paramètre</h1>
        <p class="mt-1 text-sm text-neutral-600">Modifier les informations de la boutique.</p>
    </div>

    <section class="rounded-lg border border-neutral-200 bg-white p-6 shadow-sm">
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
                        <div x-show="!previewUrl" x-cloak>
                            <p class="text-xs font-medium text-neutral-500">Logo enregistré</p>
                            <img src="{{ asset('storage/'.$setting->logo) }}" alt="Logo actuel" class="mt-1 h-16 w-auto rounded border border-neutral-200 bg-white p-1 object-contain" />
                        </div>
                    @endif
                </div>
                <x-input-error :messages="$errors->get('logo')" class="mt-2" />
            </div>

            <div class="md:col-span-2 pt-2">
                <x-primary-button>Enregistrer</x-primary-button>
            </div>
        </form>
    </section>
</x-app-layout>
