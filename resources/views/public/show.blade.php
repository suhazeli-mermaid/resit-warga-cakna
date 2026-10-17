<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $campaign->title }} - WARGA CAKNA</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">

    <div class="max-w-4xl mx-auto px-4 py-8">
        <a href="{{ route('home') }}" class="inline-flex items-center text-emerald-700 hover:text-emerald-900 font-semibold mb-6 text-sm">
            &larr; Kembali ke Senarai Dana
        </a>

        @if(session('error'))
            <div class="bg-red-100 border border-red-400 text-red-800 px-4 py-3 rounded mb-6 text-sm">
                {{ session('error') }}
            </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            
            <!-- Maklumat Dana -->
            <div class="md:col-span-1 bg-white rounded-xl shadow-sm border border-gray-200 p-5 h-fit">
                <h2 class="text-xl font-bold text-gray-900 mb-2">{{ $campaign->title }}</h2>
                <p class="text-xs text-emerald-700 font-bold mb-4">Anjuran WARGA CAKNA</p>
                <p class="text-gray-600 text-sm mb-6 leading-relaxed">{{ $campaign->description }}</p>

                <div class="border-t border-gray-200 pt-4">
                    <span class="block text-xs text-gray-500 mb-1">Jumlah Terkumpul Semasa</span>
                    <span class="text-2xl font-extrabold text-emerald-700">RM {{ number_format($campaign->collected_amount, 2) }}</span>
                </div>
            </div>

            <!-- Borang Sumbangan & Login Ringkas -->
            <div class="md:col-span-2 bg-white rounded-xl shadow-md border border-gray-200 overflow-hidden">
                <div class="bg-emerald-800 px-6 py-4 text-white">
                    <h3 class="text-lg font-bold">Borang Sumbangan Derma Online</h3>
                    <p class="text-xs text-emerald-200">Isi maklumat ringkas di bawah untuk penerbitan resit rasmi.</p>
                </div>

                <form action="{{ route('dana.donate', $campaign->slug) }}" method="POST" class="p-6 space-y-6">
                    @csrf

                    <!-- 1. Pilih Amaun Sumbangan -->
                    <div>
                        <label class="block text-sm font-bold text-gray-800 mb-2">1. Pilih / Masukkan Jumlah Sumbangan (RM) <span class="text-red-500">*</span></label>
                        <div class="grid grid-cols-3 gap-3 mb-3">
                            <button type="button" onclick="setAmount(10)" class="py-2 border border-emerald-600 text-emerald-800 font-bold rounded-lg hover:bg-emerald-50 text-sm focus:bg-emerald-700 focus:text-white transition">RM 10</button>
                            <button type="button" onclick="setAmount(50)" class="py-2 border border-emerald-600 text-emerald-800 font-bold rounded-lg hover:bg-emerald-50 text-sm focus:bg-emerald-700 focus:text-white transition">RM 50</button>
                            <button type="button" onclick="setAmount(100)" class="py-2 border border-emerald-600 text-emerald-800 font-bold rounded-lg hover:bg-emerald-50 text-sm focus:bg-emerald-700 focus:text-white transition">RM 100</button>
                        </div>
                        <input type="number" step="0.01" min="1" id="amount" name="amount" required placeholder="Atau taip jumlah lain (Contoh: 30)" class="w-full border-gray-300 rounded-lg shadow-sm border p-3 font-bold text-gray-900 focus:ring-emerald-500 focus:border-emerald-500 text-base">
                    </div>

                    <!-- 2. Login Ringkas / Maklumat Penderma -->
                    <div>
                        <label class="block text-sm font-bold text-gray-800 mb-2">2. Maklumat Penderma (Untuk Resit & Akaun Login)</label>
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Nama Penuh <span class="text-red-500">*</span></label>
                                <input type="text" name="donor_name" required value="{{ Auth::check() ? Auth::user()->name : old('donor_name') }}" placeholder="Contoh: Ahmad bin Abdullah" class="w-full border-gray-300 rounded-lg border p-2.5 text-sm focus:ring-emerald-500 focus:border-emerald-500">
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Alamat E-mel <span class="text-red-500">*</span></label>
                                    <input type="email" name="donor_email" required value="{{ Auth::check() ? Auth::user()->email : old('donor_email') }}" placeholder="Contoh: penderma@gmail.com" class="w-full border-gray-300 rounded-lg border p-2.5 text-sm focus:ring-emerald-500 focus:border-emerald-500">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">No. Telefon <span class="text-red-500">*</span></label>
                                    <input type="text" name="donor_phone" required value="{{ old('donor_phone') }}" placeholder="Contoh: 019-1234567" class="w-full border-gray-300 rounded-lg border p-2.5 text-sm focus:ring-emerald-500 focus:border-emerald-500">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 3. Kaedah Bayaran -->
                    <div>
                        <label class="block text-sm font-bold text-gray-800 mb-2">3. Pilih Kaedah Bayaran <span class="text-red-500">*</span></label>
                        <div class="space-y-2">
                            <label class="flex items-center p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-emerald-50 transition">
                                <input type="radio" name="payment_method" value="toyyibpay" checked onclick="togglePaymentFields('toyyibpay')" class="text-emerald-600 focus:ring-emerald-500">
                                <div class="ml-3">
                                    <span class="block font-bold text-sm text-gray-900">ToyyibPay (FPX / FPX Credit Card)</span>
                                    <span class="block text-xs text-gray-500">Bayaran secara automatik & terus disahkan.</span>
                                </div>
                            </label>

                            <label class="flex items-center p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-emerald-50 transition">
                                <input type="radio" name="payment_method" value="bank_transfer" onclick="togglePaymentFields('manual')" class="text-emerald-600 focus:ring-emerald-500">
                                <div class="ml-3">
                                    <span class="block font-bold text-sm text-gray-900">Pindahan Bank / Transfer Online / QR Pay</span>
                                    <span class="block text-xs text-gray-500">Masukkan kod rujukan perbankan anda.</span>
                                </div>
                            </label>
                        </div>

                        <!-- Medan Tambahan Rujukan Manual -->
                        <div id="manual_ref_field" class="hidden mt-3 bg-gray-50 p-3 rounded-lg border border-gray-200">
                            <label class="block text-xs font-medium text-gray-700 mb-1">No. Rujukan Transaksi Bank / QR</label>
                            <input type="text" name="payment_reference" placeholder="Contoh No. Rujukan: MBB-998811" class="w-full border-gray-300 rounded-lg border p-2.5 text-sm">
                        </div>
                    </div>

                    <button type="submit" class="w-full bg-emerald-700 hover:bg-emerald-800 text-white font-bold py-3 rounded-lg text-base shadow transition">
                        Teruskan Ke Pembayaran &rarr;
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function setAmount(val) {
            document.getElementById('amount').value = val;
        }

        function togglePaymentFields(type) {
            const manualField = document.getElementById('manual_ref_field');
            if (type === 'manual') {
                manualField.classList.remove('hidden');
            } else {
                manualField.classList.add('hidden');
            }
        }
    </script>

</body>
</html>