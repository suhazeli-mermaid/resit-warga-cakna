<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cipta Resit Derma Baharu - WARGA CAKNA</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">

    <div class="max-w-3xl mx-auto px-4 py-8">
        <a href="{{ route('receipts.index') }}" class="inline-flex items-center text-emerald-700 hover:text-emerald-900 font-semibold mb-6 text-sm">
            &larr; Kembali ke Senarai Resit
        </a>

        <div class="bg-white rounded-lg shadow-md overflow-hidden border border-gray-200">
            <div class="bg-emerald-800 px-6 py-4 text-white">
                <h2 class="text-xl font-bold">Borang Pengeluaran Resit Derma Rasmi</h2>
                <p class="text-xs text-emerald-200">Persatuan Prihatin Warga Terpinggir Terengganu (WARGA CAKNA)</p>
            </div>

            <form action="{{ route('receipts.store') }}" method="POST" class="p-6 space-y-6">
                @csrf

                <!-- Maklumat Penderma -->
                <div>
                    <h3 class="text-md font-bold text-emerald-800 border-b border-gray-200 pb-2 mb-4">1. Maklumat Penderma</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nama Penderma / Organisasi <span class="text-red-500">*</span></label>
                            <input type="text" name="donor_name" required value="{{ old('donor_name') }}" placeholder="Contoh: Ahmad bin Abdullah" class="w-full border-gray-300 rounded-md shadow-sm border p-2.5 focus:ring-emerald-500 focus:border-emerald-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">No. KP / Passport / Pendaftaran</label>
                            <input type="text" name="donor_ic_passport" value="{{ old('donor_ic_passport') }}" placeholder="Contoh: 880101-11-5555" class="w-full border-gray-300 rounded-md shadow-sm border p-2.5 focus:ring-emerald-500 focus:border-emerald-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">No. Telefon</label>
                            <input type="text" name="donor_phone" value="{{ old('donor_phone') }}" placeholder="Contoh: 019-1234567" class="w-full border-gray-300 rounded-md shadow-sm border p-2.5 focus:ring-emerald-500 focus:border-emerald-500">
                        </div>
                    </div>
                </div>

                <!-- Butiran Sumbangan -->
                <div>
                    <h3 class="text-md font-bold text-emerald-800 border-b border-gray-200 pb-2 mb-4">2. Butiran Sumbangan</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Jumlah Sumbangan (RM) <span class="text-red-500">*</span></label>
                            <input type="number" step="0.01" min="1" name="amount" required value="{{ old('amount') }}" placeholder="0.00" class="w-full border-gray-300 rounded-md shadow-sm border p-2.5 focus:ring-emerald-500 focus:border-emerald-500 font-bold text-gray-900">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tarikh Derma <span class="text-red-500">*</span></label>
                            <input type="date" name="donated_at" required value="{{ old('donated_at', date('Y-m-d')) }}" class="w-full border-gray-300 rounded-md shadow-sm border p-2.5 focus:ring-emerald-500 focus:border-emerald-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Kaedah Bayaran <span class="text-red-500">*</span></label>
                            <select name="payment_method" required class="w-full border-gray-300 rounded-md shadow-sm border p-2.5 focus:ring-emerald-500 focus:border-emerald-500">
                                <option value="fpx">Perbankan Internet (FPX)</option>
                                <option value="cash">Tunai</option>
                                <option value="bank_transfer">Pindahan Bank (Online Transfer)</option>
                                <option value="cheque">Cek</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">No. Rujukan / Resit Bank</label>
                            <input type="text" name="payment_reference" value="{{ old('payment_reference') }}" placeholder="Contoh: MBB-88120412" class="w-full border-gray-300 rounded-md shadow-sm border p-2.5 focus:ring-emerald-500 focus:border-emerald-500">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tujuan / Keterangan Sumbangan</label>
                            <textarea name="description" rows="3" placeholder="Sumbangan Dana Tabung Kebajikan & Bantuan Warga Terpinggir" class="w-full border-gray-300 rounded-md shadow-sm border p-2.5 focus:ring-emerald-500 focus:border-emerald-500">{{ old('description') }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="pt-4 flex justify-end space-x-3 border-t border-gray-200">
                    <a href="{{ route('receipts.index') }}" class="px-5 py-2.5 bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold rounded-md text-sm transition">Batal</a>
                    <button type="submit" class="px-5 py-2.5 bg-emerald-700 hover:bg-emerald-800 text-white font-bold rounded-md text-sm shadow transition">Jana Resit Derma</button>
                </div>
            </form>
        </div>
    </div>

</body>
</html>