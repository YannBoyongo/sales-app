<tr class="transition-colors hover:bg-neutral-50/80" data-voucher-id="{{ $voucher->id }}">
    <td class="px-4 py-3">
        <div class="flex flex-wrap items-center gap-2">
            <span class="font-semibold text-neutral-900">{{ $voucher->voucher_no }}</span>
            @if ($voucher->accounting_transaction_id)
                <span class="inline-flex rounded-full bg-sky-100 px-2 py-0.5 text-[11px] font-semibold text-sky-800">Comptabilisé</span>
            @elseif ($voucher->approved_at)
                <span class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-800">Approuvé</span>
            @else
                <span class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-800">En attente</span>
            @endif
        </div>
    </td>
    <td class="px-4 py-3 text-neutral-700">{{ $voucher->date?->format('d/m/Y') }}</td>
    <td class="px-4 py-3 text-neutral-700">{{ $voucher->description }}</td>
    <td class="px-4 py-3">
        @if ($voucher->type === 'entry')
            <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">Entrée</span>
        @else
            <span class="inline-flex rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-semibold text-red-800">Sortie</span>
        @endif
    </td>
    <td class="px-4 py-3 text-right font-medium tabular-nums text-neutral-900">{{ number_format((float) $voucher->amount, 2, ',', ' ') }}</td>
    @if (auth()->user()?->isAdmin())
        <td class="px-4 py-3 text-right">
            <div class="inline-flex items-center justify-end gap-1">
                @if (! $voucher->approved_at)
                    <button
                        type="button"
                        title="Modifier le n° bon"
                        aria-label="Modifier le n° bon"
                        class="app-icon-btn"
                        @click="editVoucherId = {{ $voucher->id }}; editVoucherNo = @js($voucher->voucher_no); editAction = @js(route('cash-vouchers.update', $voucher)); editOpen = true"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.862 3.487a2.1 2.1 0 112.97 2.97L9.75 16.54 6 17.25l.71-3.75 10.152-10.013z" />
                        </svg>
                    </button>
                    <button
                        type="button"
                        title="Approuver"
                        aria-label="Approuver"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-800 hover:bg-emerald-100 disabled:cursor-not-allowed disabled:opacity-50"
                        @click="approveVoucher(@js(route('cash-vouchers.approve', $voucher)), $event)"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </button>
                @endif
                @if (! $voucher->accounting_transaction_id)
                    <form action="{{ route('cash-vouchers.destroy', $voucher) }}" method="POST" onsubmit="return confirm('Supprimer définitivement ce bon de caisse ?');" class="inline">
                        @csrf
                        @method('DELETE')
                        <button
                            type="submit"
                            title="Supprimer"
                            aria-label="Supprimer"
                            class="app-icon-btn-danger"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </form>
                @else
                    <span class="inline-flex h-8 w-8 items-center justify-center text-xs text-neutral-400" title="Bon comptabilisé — suppression impossible">—</span>
                @endif
            </div>
        </td>
    @endif
    @if (auth()->user()?->canAccessAccounting())
        <td class="px-4 py-3 text-right">
            @if ($voucher->approved_at && ! $voucher->accounting_transaction_id)
                <a href="{{ route('cash-vouchers.accounting.create', $voucher) }}" class="inline-flex items-center rounded-lg border border-primary/25 bg-primary/10 px-3 py-1.5 text-xs font-semibold text-primary hover:bg-primary/15">
                    Imputer
                </a>
            @elseif ($voucher->accounting_transaction_id)
                <form
                    action="{{ route('cash-vouchers.unaccount', $voucher) }}"
                    method="POST"
                    onsubmit="return confirm('Annuler la comptabilisation de ce bon ? L’écriture comptable sera supprimée et le bon repassera en attente.');"
                    class="inline"
                >
                    @csrf
                    <button
                        type="submit"
                        class="inline-flex items-center rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-900 hover:bg-amber-100"
                    >
                        Décomptabiliser
                    </button>
                </form>
            @else
                <span class="text-xs text-neutral-500">—</span>
            @endif
        </td>
    @endif
</tr>
