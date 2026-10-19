<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Kemaskini Resit Sumbangan') }} - {{ $receipt->receipt_no }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm rounded-xl p-4 sm:p-6">

                <div class="mb-6 border-b pb-4 flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">Borang Kemaskini Resit</h3>
                        <p class="text-xs text-gray-500">Kemaskini maklumat penderma, tarikh dan jumlah. Status hanya berubah melalui aliran pengesahan.</p>
                    </div>
                    <a href="{{ route('receipts.index') }}" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-1.5 px-4 rounded text-xs transition">
                        &larr; Kembali ke Senarai
                    </a>
                </div>

                <!-- MESEJ RALAT VALIDASI -->
                @if ($errors->any())
                    <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative text-sm">
                        <strong class="font-bold">Sila periksa input anda:</strong>
                        <ul class="mt-1 list-disc list-inside text-xs">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <!-- BORANG KEMASKINI -->
                <form action="{{ route('receipts.update', $receipt->id) }}" method="POST" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- NAMA PENDERMA -->
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Nama Penderma *</label>
                            <input type="text" name="donor_name" value="{{ old('donor_name', $receipt->donor_name) }}" required class="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                        </div>

                        <!-- ALAMAT EMEL -->
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Alamat Emel</label>
                            <input type="email" name="donor_email" value="{{ old('donor_email', $receipt->donor_email) }}" class="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                        </div>

                        <!-- TELEFON -->
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">No. Telefon</label>
                            <input type="text" name="donor_phone" value="{{ old('donor_phone', $receipt->donor_phone) }}" class="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                        </div>

                        <!-- TARIKH SUMBANGAN -->
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Tarikh Sumbangan *</label>
                            <input type="date" name="donated_at" max="{{ now()->toDateString() }}" value="{{ old('donated_at', $receipt->donated_at?->format('Y-m-d')) }}" required class="min-h-11 w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                        </div>

                        <!-- JUMLAH (RM) -->
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Jumlah Sumbangan (RM) *</label>
                            <input type="number" step="0.01" name="amount" value="{{ old('amount', $receipt->amount) }}" required class="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                        </div>

                        <div>
                            <span class="block text-xs font-bold text-gray-700 uppercase mb-1">Kaedah Bayaran</span>
                            <span class="text-sm text-gray-800">{{ strtoupper(str_replace('_', ' ', $receipt->payment_method)) }}</span>
                        </div>

                        <div>
                            <span class="block text-xs font-bold text-gray-700 uppercase mb-1">Status Bayaran</span>
                            <span class="inline-flex items-center px-2.5 py-2 rounded border text-xs font-bold {{ $receipt->status_badge_classes }}">
                                {{ $receipt->status_label }}
                            </span>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">No. Rujukan Bayaran</label>
                        <input type="text" name="payment_reference" value="{{ old('payment_reference', $receipt->payment_reference) }}" maxlength="255" class="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                    </div>

                    <!-- CATATAN / TUJUAN -->
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Tujuan / Catatan Sumbangan</label>
                        <textarea name="description" rows="3" class="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">{{ old('description', $receipt->description) }}</textarea>
                    </div>

                    <!-- BUTANG SIMPAN -->
                    <div class="pt-4 flex justify-end gap-2">
                        <a href="{{ route('receipts.index') }}" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded text-sm transition">
                            Batal
                        </a>
                        <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold py-2 px-6 rounded text-sm transition">
                            Simpan Perubahan
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</x-app-layout>
