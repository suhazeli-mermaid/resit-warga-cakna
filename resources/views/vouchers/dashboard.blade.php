<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div><h1 class="text-xl font-semibold text-gray-800">Kewangan / Baucar Bayaran</h1><p class="text-sm text-gray-500">Dashboard pengurusan Baucar dan Surat Akuan</p></div>
            @can('create', App\Models\PaymentVoucher::class)
                <a href="{{ route('vouchers.create') }}" class="inline-flex min-h-11 items-center rounded-lg bg-emerald-700 px-4 text-sm font-bold text-white">+ Draf Baharu</a>
            @endcan
        </div>
    </x-slot>

    <div class="py-6"><div class="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
        @include('vouchers.partials.subnavigation')

        <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
            @foreach([
                ['Jumlah Baucar', $stats['total']], ['Menunggu Kelulusan', $stats['pending_approval']],
                ['Menunggu Bayaran', $stats['pending_payment']], ['Menunggu Akuan', $stats['pending_ack']],
                ['Selesai', $stats['completed']], ['Jumlah RM Dibayar', 'RM '.number_format($stats['paid_total'], 2)],
            ] as [$label, $value])
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm"><div class="text-xs font-semibold uppercase text-gray-500">{{ $label }}</div><div class="mt-2 text-xl font-bold text-emerald-800">{{ $value }}</div></div>
            @endforeach
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <a href="{{ route('vouchers.index') }}" class="group rounded-xl border border-emerald-200 bg-white p-5 shadow-sm hover:border-emerald-500">
                <div class="font-bold text-emerald-800">LIHAT SENARAI BAUCAR</div><p class="mt-1 text-sm text-gray-600">Urus permohonan, kelulusan, pengeluaran dan bayaran.</p>
            </a>
            <a href="{{ route('vouchers.acknowledgements.index') }}" class="group rounded-xl border border-violet-200 bg-white p-5 shadow-sm hover:border-violet-500">
                <div class="font-bold text-violet-800">LIHAT SURAT AKUAN</div><p class="mt-1 text-sm text-gray-600">Semak pautan akuan, penerimaan dan dokumen selesai.</p>
            </a>
        </div>
    </div></div>
</x-app-layout>
