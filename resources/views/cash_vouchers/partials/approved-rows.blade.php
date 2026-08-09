@forelse ($approvedVouchers as $voucher)
    @include('cash_vouchers.partials.row', ['voucher' => $voucher])
@empty
    @if (! request()->boolean('infinite'))
        <tr data-empty>
            <td colspan="{{ $voucherTableColspan }}" class="px-4 py-10 text-center text-neutral-500">Aucun bon approuvé.</td>
        </tr>
    @endif
@endforelse
