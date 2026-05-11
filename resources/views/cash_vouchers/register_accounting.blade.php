<x-app-layout>
    <x-slot name="header">Comptabiliser un bon de caisse</x-slot>

    <x-caisse-flow max-width="max-w-3xl" :with-card="false">
        <x-slot name="header">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-primary/90">Bon de caisse</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight text-neutral-900 sm:text-4xl">Enregistrer en comptabilité</h1>
                <p class="mt-3 max-w-2xl text-base leading-relaxed text-neutral-600">
                    Vérifiez les informations pré-remplies, choisissez le compte concerné puis validez.
                </p>
            </div>
        </x-slot>

        <div
            x-data="{
                addAccountOpen: false,
                accountCode: @js((string) old('account_code', '')),
                newAccountCode: '',
                newAccountName: @js((string) old('new_account_name', '')),
                newAccountType: @js((string) old('new_account_type', '')),
                applyNewAccount() {
                    const code = (this.newAccountCode || '').trim();
                    if (!code) return;
                    const select = this.$refs.accountSelect;
                    const label = this.newAccountName ? `${code} — ${this.newAccountName}` : code;
                    let exists = false;
                    for (const option of select.options) {
                        if (option.value === code) {
                            exists = true;
                            break;
                        }
                    }
                    if (!exists) {
                        select.add(new Option(label, code, true, true));
                    }
                    this.accountCode = code;
                    this.addAccountOpen = false;
                }
            }"
            @keydown.escape.window="addAccountOpen = false"
            class="rounded-2xl border border-neutral-200/90 bg-white/90 p-6 shadow-xl shadow-neutral-900/5 ring-1 ring-neutral-900/5 backdrop-blur-sm sm:p-8"
        >
            <form action="{{ route('cash-vouchers.accounting.store', $cashVoucher) }}" method="POST" class="grid gap-4 sm:grid-cols-2">
                @csrf

                <div>
                    <x-input-label value="N° bon" />
                    <x-text-input type="text" class="mt-1 block w-full bg-neutral-50" :value="$cashVoucher->voucher_no" readonly />
                </div>
                <div>
                    <x-input-label value="Date" />
                    <x-text-input type="text" class="mt-1 block w-full bg-neutral-50" :value="optional($cashVoucher->date)->format('d/m/Y')" readonly />
                </div>
                <div class="sm:col-span-2">
                    <x-input-label value="Description" />
                    <textarea class="mt-1 block w-full rounded-md border-neutral-300 bg-neutral-50 text-sm shadow-sm focus:border-primary focus:ring-primary" rows="3" readonly>{{ $cashVoucher->description }}</textarea>
                </div>
                <div>
                    <x-input-label value="Type d'écriture" />
                    <x-text-input type="text" class="mt-1 block w-full bg-neutral-50" :value="$cashVoucher->type === 'entry' ? 'Débit' : 'Crédit'" readonly />
                </div>
                <div>
                    <x-input-label value="Montant" />
                    <x-text-input type="text" class="mt-1 block w-full bg-neutral-50" :value="number_format((float) $cashVoucher->amount, 2, ',', ' ')" readonly />
                </div>

                <div class="sm:col-span-2">
                    <x-input-label for="account_code" value="Compte impacté" />
                    <select
                        id="account_code"
                        name="account_code"
                        x-model="accountCode"
                        x-ref="accountSelect"
                        required
                        class="mt-1 block w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                    >
                        <option value="">— Choisir un compte —</option>
                        @foreach ($accounts as $account)
                            <option value="{{ $account->account_code }}">
                                {{ $account->account_code }} — {{ $account->name }}
                            </option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('account_code')" class="mt-2" />
                    <button
                        type="button"
                        class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline"
                        @click="addAccountOpen = true"
                    >
                        <span class="inline-flex h-4 w-4 items-center justify-center rounded-full border border-primary/40 text-[10px]">+</span>
                        <span>Add account</span>
                    </button>
                    <input type="hidden" name="new_account_name" x-model="newAccountName">
                    <input type="hidden" name="new_account_type" x-model="newAccountType">
                    <x-input-error :messages="$errors->get('new_account_name')" class="mt-2" />
                    <x-input-error :messages="$errors->get('new_account_type')" class="mt-2" />
                </div>

                <div class="sm:col-span-2 flex justify-end gap-2 border-t border-neutral-100 pt-4">
                    <a href="{{ route('cash-vouchers.index') }}" class="inline-flex items-center rounded-md border border-neutral-300 bg-white px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">
                        Annuler
                    </a>
                    <x-primary-button>Valider l'écriture</x-primary-button>
                </div>
            </form>

            <div x-show="addAccountOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" x-transition.opacity>
                <div class="absolute inset-0 bg-black/50" @click="addAccountOpen = false" aria-hidden="true"></div>
                <div role="dialog" aria-modal="true" class="relative z-10 w-full max-w-lg rounded-2xl border border-neutral-200 bg-white p-6 shadow-xl ring-1 ring-neutral-900/5" @click.stop>
                    <h3 class="text-lg font-semibold text-neutral-900">Add account</h3>
                    <p class="mt-1 text-sm text-neutral-600">Ajoutez un compte qui n'existe pas encore.</p>

                    <div class="mt-4 space-y-4">
                        <div>
                            <x-input-label for="new_account_code_modal" value="Numéro de compte" />
                            <input
                                id="new_account_code_modal"
                                type="text"
                                maxlength="30"
                                x-model="newAccountCode"
                                class="mt-1 block w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                                placeholder="Ex: 101"
                            />
                        </div>
                        <div>
                            <x-input-label for="new_account_name_modal" value="Nom du compte" />
                            <input
                                id="new_account_name_modal"
                                type="text"
                                maxlength="150"
                                x-model="newAccountName"
                                class="mt-1 block w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                                placeholder="Ex: Caisse principale"
                            />
                        </div>
                        <div>
                            <x-input-label for="new_account_type_modal" value="Type du compte" />
                            <select
                                id="new_account_type_modal"
                                x-model="newAccountType"
                                class="mt-1 block w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                            >
                                <option value="">— Choisir —</option>
                                <option value="asset">Actif</option>
                                <option value="liability">Passif</option>
                                <option value="equity">Capitaux propres</option>
                                <option value="revenue">Produit</option>
                                <option value="expense">Charge</option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-5 flex justify-end gap-2 border-t border-neutral-100 pt-4">
                        <button type="button" class="rounded-md border border-neutral-300 bg-white px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50" @click="addAccountOpen = false">Annuler</button>
                        <button type="button" class="rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm hover:opacity-95" @click="applyNewAccount()">Valider</button>
                    </div>
                </div>
            </div>
        </div>
    </x-caisse-flow>
</x-app-layout>
