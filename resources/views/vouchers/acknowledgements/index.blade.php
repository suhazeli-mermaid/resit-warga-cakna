<x-app-layout>
    <x-slot name="header"><div><h1 class="text-xl font-semibold text-gray-800">Surat Akuan Penerimaan Bayaran</h1><p class="text-sm text-gray-500">Senarai permintaan dan dokumen Akuan yang berkait dengan Baucar</p></div></x-slot>

    <div class="py-6"><div class="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
        @if(session('success'))<div class="rounded-lg border border-green-300 bg-green-50 p-4 text-sm text-green-800">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="rounded-lg border border-red-300 bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>@endif
        @include('vouchers.partials.subnavigation')

        <form method="GET" class="grid gap-3 rounded-xl bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-5">
            <input name="search" value="{{ request('search') }}" placeholder="No. akuan, no. baucar atau penerima" class="rounded-md border-gray-300 sm:col-span-2" />
            <select name="status" class="rounded-md border-gray-300"><option value="">Semua status</option><option value="pending" @selected(request('status') === 'pending')>MENUNGGU AKUAN</option><option value="completed" @selected(request('status') === 'completed')>SELESAI</option><option value="expired" @selected(request('status') === 'expired')>TAMAT TEMPOH</option></select>
            <input name="date_from" value="{{ request('date_from') }}" type="date" class="rounded-md border-gray-300" aria-label="Tarikh pautan dari" />
            <input name="date_to" value="{{ request('date_to') }}" type="date" class="rounded-md border-gray-300" aria-label="Tarikh pautan hingga" />
            <div class="flex gap-2"><button class="min-h-11 flex-1 rounded-md bg-gray-800 px-4 text-sm font-bold text-white">Tapis</button><a href="{{ route('vouchers.acknowledgements.index') }}" class="inline-flex min-h-11 items-center rounded-md border border-gray-300 px-4 text-sm font-bold">Reset</a></div>
        </form>

        <div class="space-y-3 md:hidden" data-testid="mobile-acknowledgement-list">
            @forelse($acknowledgements as $acknowledgement)
                @php($voucher = $acknowledgement->voucher)
                <article class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-3"><div><a href="{{ route('vouchers.acknowledgements.show', $acknowledgement) }}" class="font-bold text-violet-800">{{ $acknowledgement->acknowledgement_no }}</a><p class="mt-1 text-sm font-semibold">{{ $voucher->voucher_no }} · {{ $voucher->recipient_name }}</p></div><strong class="whitespace-nowrap text-emerald-800">RM {{ number_format($voucher->paid_amount ?? $voucher->amount, 2) }}</strong></div>
                    <p class="mt-3 line-clamp-2 text-sm text-gray-600">{{ $voucher->purpose }}</p>
                    <dl class="mt-3 grid grid-cols-2 gap-2 text-xs"><div><dt class="text-gray-500">Transaksi</dt><dd class="font-semibold">{{ $voucher->transaction_date?->format('d/m/Y') ?? '—' }}</dd></div><div><dt class="text-gray-500">Pautan dihantar</dt><dd class="font-semibold">{{ $voucher->latestAcknowledgementSentAudit?->created_at?->format('d/m/Y H:i') ?? 'Belum berjaya dihantar' }}</dd></div><div><dt class="text-gray-500">Akuan diterima</dt><dd class="font-semibold">{{ $acknowledgement->used_at?->format('d/m/Y H:i') ?? '—' }}</dd></div><div><dt class="text-gray-500">Status</dt><dd><span class="inline-flex rounded-full px-2 py-1 font-bold {{ $acknowledgement->status === 'completed' ? 'bg-green-100 text-green-800' : ($acknowledgement->status === 'expired' ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800') }}">{{ $acknowledgement->status_label }}</span></dd></div></dl>
                    <div class="mt-4 flex flex-wrap gap-3 border-t border-gray-100 pt-3 text-sm">@include('vouchers.acknowledgements.partials.actions', ['acknowledgement' => $acknowledgement])</div>
                </article>
            @empty<div class="rounded-xl bg-white p-8 text-center text-gray-500">Tiada permintaan Akuan ditemui.</div>@endforelse
        </div>

        <div class="hidden overflow-x-auto rounded-xl bg-white shadow-sm md:block"><table class="min-w-full divide-y divide-gray-200 text-sm"><thead class="bg-violet-900 text-left text-xs uppercase text-white"><tr>@foreach(['No. Akuan','No. Baucar','Penerima / Tujuan','Jumlah','Tarikh Transaksi','Pautan Dihantar','Akuan Diterima','Status','Tindakan'] as $heading)<th class="p-3">{{ $heading }}</th>@endforeach</tr></thead><tbody class="divide-y divide-gray-100">
            @forelse($acknowledgements as $acknowledgement)@php($voucher = $acknowledgement->voucher)<tr class="align-top"><td class="whitespace-nowrap p-3 font-bold text-violet-800">{{ $acknowledgement->acknowledgement_no }}</td><td class="whitespace-nowrap p-3 font-semibold">{{ $voucher->voucher_no }}</td><td class="p-3"><div class="font-semibold">{{ $voucher->recipient_name }}</div><div class="mt-1 max-w-xs text-xs text-gray-500">{{ $voucher->purpose }}</div></td><td class="whitespace-nowrap p-3">RM {{ number_format($voucher->paid_amount ?? $voucher->amount, 2) }}</td><td class="whitespace-nowrap p-3">{{ $voucher->transaction_date?->format('d/m/Y') ?? '—' }}</td><td class="whitespace-nowrap p-3 text-xs">{{ $voucher->latestAcknowledgementSentAudit?->created_at?->format('d/m/Y H:i') ?? 'Belum berjaya dihantar' }}</td><td class="whitespace-nowrap p-3 text-xs">{{ $acknowledgement->used_at?->format('d/m/Y H:i') ?? '—' }}</td><td class="p-3"><span class="inline-flex rounded-full px-2 py-1 text-xs font-bold {{ $acknowledgement->status === 'completed' ? 'bg-green-100 text-green-800' : ($acknowledgement->status === 'expired' ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800') }}">{{ $acknowledgement->status_label }}</span></td><td class="min-w-52 p-3">@include('vouchers.acknowledgements.partials.actions', ['acknowledgement' => $acknowledgement])</td></tr>
            @empty<tr><td colspan="9" class="p-8 text-center text-gray-500">Tiada permintaan Akuan ditemui.</td></tr>@endforelse
        </tbody></table></div>
        {{ $acknowledgements->links() }}
    </div></div>
</x-app-layout>
