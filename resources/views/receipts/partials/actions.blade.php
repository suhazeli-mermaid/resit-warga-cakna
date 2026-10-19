@php
    $base = 'inline-flex min-h-11 items-center justify-center rounded-lg px-3 py-2 text-xs font-bold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2';
    $cleanPhone = preg_replace('/[^0-9]/', '', $receipt->donor_phone ?? '');
    if (str_starts_with($cleanPhone, '0')) {
        $cleanPhone = '6'.$cleanPhone;
    }
    $message = urlencode(
        "Salam, berikut pautan resit sumbangan WARGA CAKNA anda [{$receipt->receipt_no}] "
        ."bertarikh {$receipt->donation_date_label}: ".route('receipts.view-pdf', $receipt->id)
    );
@endphp

<div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
    <a href="{{ route('receipts.show', $receipt->id) }}" class="{{ $base }} bg-blue-600 text-white hover:bg-blue-700 focus-visible:ring-blue-600">Papar</a>

    @unless($receipt->trashed())
        <a href="{{ route('receipts.download-pdf', $receipt->id) }}" target="_blank" class="{{ $base }} bg-emerald-700 text-white hover:bg-emerald-800 focus-visible:ring-emerald-600">PDF</a>

        @if($cleanPhone && ! in_array($receipt->status, [\App\Models\DonationReceipt::STATUS_REJECTED, \App\Models\DonationReceipt::STATUS_FAILED], true))
            <a href="https://wa.me/{{ $cleanPhone }}?text={{ $message }}" target="_blank" rel="noopener noreferrer" class="{{ $base }} bg-green-600 text-white hover:bg-green-700 focus-visible:ring-green-600">WhatsApp</a>
        @endif
    @endunless

    @if($isAdmin)
        @if($receipt->trashed())
            <form action="{{ route('receipts.restore', $receipt->id) }}" method="POST">
                @csrf
                @method('PATCH')
                <button type="submit" class="{{ $base }} w-full bg-emerald-700 text-white hover:bg-emerald-800 focus-visible:ring-emerald-600">Pulihkan</button>
            </form>
        @elseif($receipt->isArchived())
            <form action="{{ route('receipts.unarchive', $receipt->id) }}" method="POST">
                @csrf
                @method('PATCH')
                <button type="submit" class="{{ $base }} w-full bg-amber-600 text-white hover:bg-amber-700 focus-visible:ring-amber-600">Nyaharkib</button>
            </form>
        @elseif($receipt->isPending())
            <a href="{{ route('receipts.edit', $receipt->id) }}" class="{{ $base }} bg-gray-700 text-white hover:bg-gray-800 focus-visible:ring-gray-600">Kemaskini</a>

            @if($receipt->isManualPayment())
                <form action="{{ route('receipts.verify', $receipt->id) }}" method="POST" onsubmit="return confirm('Sahkan bayaran ini sebagai BERJAYA?');">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="{{ $base }} w-full bg-amber-500 text-gray-950 hover:bg-amber-400 focus-visible:ring-amber-600">Sahkan Pembayaran</button>
                </form>

                <form action="{{ route('receipts.reject', $receipt->id) }}" method="POST" class="rounded-lg border border-red-200 bg-red-50 p-2">
                    @csrf
                    @method('PATCH')
                    <label for="rejection_reason_{{ $receipt->id }}" class="mb-1 block text-xs font-bold text-red-800">Sebab penolakan</label>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <input id="rejection_reason_{{ $receipt->id }}" name="rejection_reason" type="text" required maxlength="2000" class="min-h-11 min-w-0 flex-1 rounded-md border-gray-300 text-sm focus:border-red-500 focus:ring-red-500">
                        <button type="submit" class="{{ $base }} bg-red-700 text-white hover:bg-red-800 focus-visible:ring-red-600">Tolak</button>
                    </div>
                </form>
            @endif

            <button type="button"
                    @click="$dispatch('open-delete-modal', { id: {{ $receipt->id }}, receiptNo: @js($receipt->receipt_no), donor: @js($receipt->donor_name), amount: @js('RM '.number_format($receipt->amount, 2)), status: @js($receipt->status_label) })"
                    class="{{ $base }} bg-red-700 text-white hover:bg-red-800 focus-visible:ring-red-600">Hapus</button>
        @elseif(in_array($receipt->status, [\App\Models\DonationReceipt::STATUS_COMPLETED, \App\Models\DonationReceipt::STATUS_REJECTED, \App\Models\DonationReceipt::STATUS_FAILED], true))
            <form action="{{ route('receipts.archive', $receipt->id) }}" method="POST">
                @csrf
                @method('PATCH')
                <button type="submit" class="{{ $base }} w-full bg-amber-600 text-white hover:bg-amber-700 focus-visible:ring-amber-600">Arkibkan</button>
            </form>

            @unless($receipt->isCompleted())
                <button type="button"
                        @click="$dispatch('open-delete-modal', { id: {{ $receipt->id }}, receiptNo: @js($receipt->receipt_no), donor: @js($receipt->donor_name), amount: @js('RM '.number_format($receipt->amount, 2)), status: @js($receipt->status_label) })"
                        class="{{ $base }} bg-red-700 text-white hover:bg-red-800 focus-visible:ring-red-600">Hapus</button>
            @endunless
        @endif
    @endif
</div>
