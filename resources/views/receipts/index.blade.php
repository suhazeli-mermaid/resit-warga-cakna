<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Senarai Resit Derma (Keseluruhan Transaksi)') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">

                <!-- BORANG CARIAN & SORTING -->
                <form method="GET" action="{{ route('receipts.index') }}" class="mb-6 grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Carian Kata Kunci</label>
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="No Resit, Nama, Emel, Tel..." class="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Status Bayaran</label>
                        <select name="status" class="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                            <option value="">-- Semua Status --</option>
                            <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>BERJAYA</option>
                            <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>TUNGGU PENGESAHAN</option>
                            <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>DITOLAK</option>
                            <option value="failed" {{ request('status') == 'failed' ? 'selected' : '' }}>GAGAL</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Susunan (Sort)</label>
                        <select name="sort" class="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                            <option value="latest" {{ request('sort') == 'latest' || !request('sort') ? 'selected' : '' }}>Terkini Dahulu</option>
                            <option value="oldest" {{ request('sort') == 'oldest' ? 'selected' : '' }}>Terlama Dahulu</option>
                            <option value="amount_high" {{ request('sort') == 'amount_high' ? 'selected' : '' }}>Jumlah (Tinggi -> Rendah)</option>
                            <option value="amount_low" {{ request('sort') == 'amount_low' ? 'selected' : '' }}>Jumlah (Rendah -> Tinggi)</option>
                        </select>
                    </div>

                    <div class="flex items-end gap-2">
                        <button type="submit" class="bg-green-700 hover:bg-green-800 text-white font-bold py-2 px-4 rounded text-sm w-full transition">
                            Cari & Susun
                        </button>
                        <a href="{{ route('receipts.index') }}" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded text-sm text-center w-full transition">
                            Reset
                        </a>
                    </div>
                </form>

                <!-- MESEJ NOTIFIKASI -->
                @if(session('success'))
                    <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative text-sm">
                        {{ session('success') }}
                    </div>
                @endif
                @if(session('error'))
                    <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative text-sm">
                        {{ session('error') }}
                    </div>
                @endif

                <!-- JADUAL SENARAI RESIT -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-emerald-900 text-white text-xs uppercase font-bold">
                                <th class="p-3 border">NO. RESIT</th>
                                <th class="p-3 border">NAMA PENDERMA & INFO</th>
                                <th class="p-3 border">TARIKH</th>
                                <th class="p-3 border text-center">STATUS BAYARAN</th>
                                <th class="p-3 border text-right">JUMLAH (RM)</th>
                                <th class="p-3 border text-center">TINDAKAN ADMIN / CETAK</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y text-sm">
                            @forelse($receipts as $receipt)
                                <tr class="hover:bg-gray-50">
                                    <td class="p-3 font-semibold text-emerald-800 whitespace-nowrap">
                                        {{ $receipt->receipt_no }}
                                    </td>
                                    <td class="p-3">
                                        <div class="font-bold text-gray-900">{{ $receipt->donor_name }}</div>
                                        <div class="text-xs text-gray-500">
                                            {{ $receipt->donor_email ?? '-' }} | {{ $receipt->donor_phone ?? '-' }}
                                        </div>
                                    </td>
                                    <td class="p-3 whitespace-nowrap text-xs">
                                        {{ \Carbon\Carbon::parse($receipt->donated_at)->format('d/m/Y') }}
                                    </td>
                                    <td class="p-3 text-center whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded border text-xs font-bold {{ $receipt->status_badge_classes }}">
                                            {{ $receipt->status_label }}
                                        </span>
                                    </td>
                                    <td class="p-3 text-right font-bold text-emerald-900 whitespace-nowrap">
                                        RM {{ number_format($receipt->amount, 2) }}
                                    </td>
                                    <td class="p-3 text-center whitespace-nowrap">
                                        <div class="flex items-center justify-center gap-1.5 flex-wrap">
                                            <!-- BUTANG PAPAR -->
                                            <a href="{{ route('receipts.show', $receipt->id) }}" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-1 px-2.5 rounded text-xs transition">
                                                Papar
                                            </a>

                                            <!-- BUTANG PDF -->
                                            <a href="{{ route('receipts.download-pdf', $receipt->id) }}" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-1 px-2.5 rounded text-xs transition" target="_blank">
                                                PDF
                                            </a>

                                            <!-- BUTANG WHATSAPP -->
                                            @php
                                                $cleanPhone = preg_replace('/[^0-9]/', '', $receipt->donor_phone ?? '');
                                                if (str_starts_with($cleanPhone, '0')) {
                                                    $cleanPhone = '6' . $cleanPhone;
                                                }
                                                $msg = urlencode("Salam, berikut pautan resit sumbangan WARGA CAKNA anda [{$receipt->receipt_no}]: " . route('receipts.view-pdf', $receipt->id));
                                            @endphp
                                            @if($cleanPhone)
                                                <a href="https://wa.me/{{ $cleanPhone }}?text={{ $msg }}" target="_blank" class="bg-green-600 hover:bg-green-700 text-white font-bold py-1 px-2.5 rounded text-xs transition">
                                                    WhatsApp
                                                </a>
                                            @endif

                                            <!-- BUTANG EMEL -->
                                            @if($receipt->donor_email && $receipt->isCompleted())
                                                <form action="{{ route('receipts.resend-email', $receipt->id) }}" method="POST" class="inline" onsubmit="return confirm('Hantar emel resit ini kepada {{ $receipt->donor_email }}?');">
                                                    @csrf
                                                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-1 px-2.5 rounded text-xs transition">
                                                        Emel
                                                    </button>
                                                </form>
                                            @endif

                                            <!-- BUTANG KEMASKINI -->
                                            <a href="{{ route('receipts.edit', $receipt->id) }}" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-1 px-2.5 rounded text-xs transition">
                                                Kemaskini
                                            </a>

                                            @if(Auth::user()->isAdmin() && $receipt->isManualPayment() && $receipt->isPending())
                                                <form action="{{ route('receipts.verify', $receipt->id) }}" method="POST" class="inline" onsubmit="return confirm('Mengesahkan bayaran ini? Status akan bertukar ke BERJAYA.');">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="bg-amber-500 hover:bg-amber-600 text-white font-bold py-1 px-2.5 rounded text-xs transition">
                                                        Sahkan Pembayaran
                                                    </button>
                                                </form>

                                                <form action="{{ route('receipts.reject', $receipt->id) }}" method="POST" class="inline-flex items-center gap-1" onsubmit="return confirm('Tolak transaksi ini dengan sebab yang diberikan?');">
                                                    @csrf
                                                    @method('PATCH')
                                                    <label for="rejection_reason_{{ $receipt->id }}" class="sr-only">Sebab penolakan</label>
                                                    <input id="rejection_reason_{{ $receipt->id }}" name="rejection_reason" type="text" required maxlength="2000" placeholder="Sebab penolakan" class="w-36 rounded border-gray-300 py-1 text-xs focus:border-red-500 focus:ring-red-500">
                                                    <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-bold py-1 px-2.5 rounded text-xs transition">
                                                        Tolak
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="p-6 text-center text-gray-500">
                                        Tiada rekod resit ditemui.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- PAGINATION -->
                <div class="mt-4">
                    {{ $receipts->links() }}
                </div>

            </div>
        </div>
    </div>
</x-app-layout>
