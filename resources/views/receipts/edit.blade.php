<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Kemaskini Resit Sumbangan') }} - {{ $receipt->receipt_no }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">

                <div class="mb-6 border-b pb-4 flex justify-between items-center">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">Borang Kemaskini Resit</h3>
                        <p class="text-xs text-gray-500">Kemaskini maklumat penderma, jumlah, atau status bayaran.</p>
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

                        <!-- JUMLAH (RM) -->
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Jumlah Sumbangan (RM) *</label>
                            <input type="number" step="0.01" name="amount" value="{{ old('amount', $receipt->amount) }}" required class="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                        </div>

                        <!-- KAEDAH BAYARAN -->
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Kaedah Bayaran *</label>
                            <select name="payment_method" required class="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                                <option value="online_transfer" {{ old('payment_method', $receipt->payment_method) == 'online_transfer' ? 'selected' : '' }}>Online Transfer / FPX</option>
                                <option value="cash" {{ old('payment_method', $receipt->payment_method) == 'cash' ? 'selected' : '' }}>Tunai (Cash)</option>
                                <option value="cheque" {{ old('payment_method', $receipt->payment_method) == 'cheque' ? 'selected' : '' }}>Cek</option>
                                <option value="qr_pay" {{ old('payment_method', $receipt->payment_method) == 'qr_pay' ? 'selected' : '' }}>DuitNow QR</option>
                            </select>
                        </div>

                        <!-- STATUS BAYARAN -->
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Status Bayaran *</label>
                            <select name="status" required class="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                                <option value="completed" {{ old('status', $receipt->status) == 'completed' ? 'selected' : '' }}>BERJAYA (Completed)</option>
                                <option value="pending" {{ old('status', $receipt->status) == 'pending' ? 'selected' : '' }}>BELUM BAYAR / PENDING</option>
                                <option value="failed" {{ old('status', $receipt->status) == 'failed' ? 'selected' : '' }}>GAGAL (Failed)</option>
                            </select>
                        </div>
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