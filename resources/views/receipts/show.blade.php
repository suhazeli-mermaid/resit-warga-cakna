<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Paparan Butiran Resit Sumbangan') }} - {{ $receipt->receipt_no }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">

                <div class="mb-6 border-b pb-4 flex justify-between items-center">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">Maklumat Transaksi Resit</h3>
                        <p class="text-xs text-gray-500">Butiran penuh sumbangan dan maklumat penderma.</p>
                    </div>
                    <div class="flex gap-2">
                        <a href="{{ route('receipts.index') }}" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-1.5 px-4 rounded text-xs transition">
                            &larr; Kembali ke Senarai
                        </a>
                        <a href="{{ route('receipts.download-pdf', $receipt->id) }}" target="_blank" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold py-1.5 px-4 rounded text-xs transition">
                            Muat Turun PDF
                        </a>
                    </div>
                </div>

                <!-- JADUAL BUTIRAN RESIT -->
                <div class="border rounded-lg overflow-hidden mb-6">
                    <table class="w-full text-left text-sm">
                        <tbody>
                            <tr class="border-b bg-gray-50">
                                <th class="p-3 w-1/3 text-gray-700 font-bold uppercase text-xs">Nombor Resit</th>
                                <td class="p-3 font-extrabold text-emerald-800">{{ $receipt->receipt_no }}</td>
                            </tr>
                            <tr class="border-b">
                                <th class="p-3 text-gray-700 font-bold uppercase text-xs">Nama Penderma</th>
                                <td class="p-3 font-semibold text-gray-900">{{ $receipt->donor_name }}</td>
                            </tr>
                            <tr class="border-b bg-gray-50">
                                <th class="p-3 text-gray-700 font-bold uppercase text-xs">Alamat Emel</th>
                                <td class="p-3 text-gray-700">{{ $receipt->donor_email ?? '-' }}</td>
                            </tr>
                            <tr class="border-b">
                                <th class="p-3 text-gray-700 font-bold uppercase text-xs">No. Telefon</th>
                                <td class="p-3 text-gray-700">{{ $receipt->donor_phone ?? '-' }}</td>
                            </tr>
                            <tr class="border-b bg-gray-50">
                                <th class="p-3 text-gray-700 font-bold uppercase text-xs">Jumlah Sumbangan</th>
                                <td class="p-3 font-bold text-lg text-emerald-900">RM {{ number_format($receipt->amount, 2) }}</td>
                            </tr>
                            <tr class="border-b">
                                <th class="p-3 text-gray-700 font-bold uppercase text-xs">Status Bayaran</th>
                                <td class="p-3">
                                    @if($receipt->status === 'completed')
                                        <span class="inline-flex items-center px-2.5 py-1 rounded border border-green-300 text-xs font-bold bg-green-100 text-green-800">
                                            ✓ BERJAYA (COMPLETED)
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-1 rounded border border-red-300 text-xs font-bold bg-red-100 text-red-800">
                                            ✕ BELUM BAYAR / GAGAL
                                        </span>
                                    @endif
                                </td>
                            </tr>
                            <tr class="border-b bg-gray-50">
                                <th class="p-3 text-gray-700 font-bold uppercase text-xs">Kaedah Bayaran</th>
                                <td class="p-3 text-gray-700">{{ strtoupper(str_replace('_', ' ', $receipt->payment_method)) }}</td>
                            </tr>
                            <tr class="border-b">
                                <th class="p-3 text-gray-700 font-bold uppercase text-xs">Tarikh & Masa Bayaran</th>
                                <td class="p-3 text-gray-700">{{ \Carbon\Carbon::parse($receipt->donated_at)->format('d/m/Y h:i A') }}</td>
                            </tr>
                            <tr>
                                <th class="p-3 text-gray-700 font-bold uppercase text-xs">Tujuan / Catatan</th>
                                <td class="p-3 text-gray-700">{{ $receipt->description ?? 'Sumbangan Tabung Kebajikan WARGA CAKNA' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-end gap-2">
                    <a href="{{ route('receipts.edit', $receipt->id) }}" class="bg-gray-700 hover:bg-gray-800 text-white font-bold py-2 px-5 rounded text-xs transition">
                        Kemaskini Resit Ini
                    </a>
                </div>

            </div>
        </div>
    </div>
</x-app-layout>