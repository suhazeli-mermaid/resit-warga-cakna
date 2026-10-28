<x-app-layout>
<div class="min-h-screen bg-gray-100 px-4 py-6 sm:py-10">
    <div class="max-w-4xl mx-auto">
        <!-- BUTANG KEMBALI KE SENARAI DANA -->
        <div class="mb-6">
            <a href="{{ route('public.index') }}" class="text-[#046a38] hover:underline font-bold text-sm inline-flex items-center gap-1">
                &larr; Kembali ke Senarai Dana
            </a>
        </div>

        @if(session('error'))
            <div class="bg-red-100 border border-red-400 text-red-800 px-4 py-3 rounded-xl mb-6 text-sm">
                {{ session('error') }}
            </div>
        @endif

        @if(session('success'))
            <div class="bg-green-100 border border-green-400 text-green-800 px-4 py-3 rounded-xl mb-6 text-sm">
                {{ session('success') }}
            </div>
        @endif

        <!-- KAD UTAMA KEMPEN -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 sm:p-8 md:p-10 mb-8">
            <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900 mb-4 tracking-tight">
                {{ $campaign->title }}
            </h1>

            <p class="text-gray-600 text-base leading-relaxed mb-8">
                {{ $campaign->description }}
            </p>

            <div class="border-t border-gray-100 pt-6">
                <a href="#sumbang" class="block w-full text-center bg-[#046a38] hover:bg-[#03522b] text-white font-bold py-3.5 px-6 rounded-xl transition duration-150 shadow-sm text-base">
                    Sumbang Sekarang &rarr;
                </a>
            </div>
        </div>

        <!-- BORANG SUMBANGAN LENGKAP (#sumbang) -->
        <div id="sumbang" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 sm:p-8 md:p-10 scroll-mt-20">
            <h2 class="text-2xl font-bold text-gray-900 mb-2 border-b pb-4">Borang Sumbangan Derma</h2>
            <p class="text-xs text-gray-500 mb-6">Sila lengkapkan maklumat di bawah untuk menyumbang kepada <strong>{{ $campaign->title }}</strong>.</p>

            <form action="{{ route('public.donate.process') }}" method="POST" enctype="multipart/form-data" class="space-y-6"
                  x-data="{ submitting: false }"
                  @submit="if (submitting) { $event.preventDefault() } else { submitting = true }">
                @csrf
                <input type="hidden" name="campaign_id" value="{{ $campaign->slug ?? $campaign->id }}">
                <input type="hidden" name="description" value="Sumbangan Bagi {{ $campaign->title }}">
                <input type="hidden" name="submission_token" value="{{ old('submission_token', \Illuminate\Support\Str::random(64)) }}">

                <!-- PRESET JUMLAH SUMBANGAN (DEFAULT AMOUNT 0) -->
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-2">Pilih Jumlah Sumbangan (RM) *</label>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-3">
                        <button type="button" onclick="setAmount(10)" class="border-2 border-gray-200 rounded-xl py-2.5 font-bold text-gray-700 hover:border-[#046a38] hover:text-[#046a38] transition">RM 10</button>
                        <button type="button" onclick="setAmount(30)" class="border-2 border-gray-200 rounded-xl py-2.5 font-bold text-gray-700 hover:border-[#046a38] hover:text-[#046a38] transition">RM 30</button>
                        <button type="button" onclick="setAmount(50)" class="border-2 border-gray-200 rounded-xl py-2.5 font-bold text-gray-700 hover:border-[#046a38] hover:text-[#046a38] transition">RM 50</button>
                        <button type="button" onclick="setAmount(100)" class="border-2 border-gray-200 rounded-xl py-2.5 font-bold text-gray-700 hover:border-[#046a38] hover:text-[#046a38] transition">RM 100</button>
                    </div>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center font-bold text-gray-500 text-sm">RM</span>
                        <input type="number" id="custom_amount" name="amount" step="0.01" min="1" value="{{ old('amount', 0) }}" required class="w-full text-sm pl-10 pr-3 py-3 rounded-lg border border-gray-300 font-bold text-[#046a38] focus:outline-none focus:border-[#046a38]">
                    </div>
                    <p class="text-[11px] text-gray-400 mt-1">* Amaun mestilah melebihi RM 0.</p>
                </div>

                <!-- MAKLUMAT PENDERMA -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Nama Penuh *</label>
                        <input type="text" name="donor_name" value="{{ old('donor_name') }}" placeholder="Nama penderma" required class="w-full text-sm p-3 rounded-lg border border-gray-300 focus:outline-none focus:border-[#046a38]">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">No. Telefon *</label>
                        <input type="text" name="donor_phone" value="{{ old('donor_phone') }}" placeholder="01X-XXXXXXX" required class="w-full text-sm p-3 rounded-lg border border-gray-300 focus:outline-none focus:border-[#046a38]">
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Alamat Emel (Untuk Resit Digital)</label>
                        <input type="email" name="donor_email" value="{{ old('donor_email') }}" placeholder="contoh@email.com" class="w-full text-sm p-3 rounded-lg border border-gray-300 focus:outline-none focus:border-[#046a38]">
                    </div>
                </div>

                <!-- PILIHAN KAEDAH PEMBAYARAN -->
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-2">Pilih Kaedah Pembayaran *</label>
                    <div class="space-y-2">
                        <label class="flex items-center p-3 border rounded-xl cursor-pointer hover:bg-gray-50">
                            <input type="radio" name="payment_method" value="billplz" checked onclick="toggleManualFields('billplz')" class="text-[#046a38]">
                            <span class="ml-3 font-semibold text-sm text-gray-800">Billplz (FPX / Online Banking)</span>
                        </label>
                        <label class="flex items-center p-3 border rounded-xl cursor-pointer hover:bg-gray-50">
                            <input type="radio" name="payment_method" value="bank_transfer" onclick="toggleManualFields('bank_transfer')" class="text-[#046a38]">
                            <span class="ml-3 font-semibold text-sm text-gray-800">Bank Transfer (Maybank Islamic)</span>
                        </label>
                        <label class="flex items-center p-3 border rounded-xl cursor-pointer hover:bg-gray-50">
                            <input type="radio" name="payment_method" value="qr_pay" onclick="toggleManualFields('qr_pay')" class="text-[#046a38]">
                            <span class="ml-3 font-semibold text-sm text-gray-800">DuitNow QR / Maybank QRPay</span>
                        </label>
                        <label class="flex items-center p-3 border rounded-xl cursor-pointer hover:bg-gray-50">
                            <input type="radio" name="payment_method" value="cash" onclick="toggleManualFields('cash')" class="text-[#046a38]">
                            <span class="ml-3 font-semibold text-sm text-gray-800">Tunai (Cash Deposit / Walk-in)</span>
                        </label>
                    </div>
                </div>

                <!-- MEDAN BAYARAN MANUAL & QR (DINAMIK DISPLAY) -->
                <div id="manual_fields_box" class="hidden bg-emerald-50 border border-emerald-200 p-5 rounded-xl space-y-4">
                    
                    <!-- BANK DETAILS (MAYBANK ISLAMIC 563037065007) -->
                    <div id="bank_info_text" class="text-xs text-emerald-900 leading-relaxed font-medium">
                        <strong>MAKLUMAT BANK WARGA CAKNA:</strong><br>
                        Bank: Maybank Islamic<br>
                        No. Akaun: <span id="acc_no" class="font-bold text-emerald-950">563037065007</span> 
                        <button type="button" onclick="copyAccountNo()" class="ml-2 text-[10px] bg-emerald-800 text-white px-2 py-0.5 rounded hover:bg-emerald-900">Salin No. Akaun</button><br>
                        Nama Akaun: PERSATUAN PRIHATIN WARGA TERPINGGIR TERENGGANU
                    </div>

                    <!-- DISPLAY OFFICIAL DUITNOW QR IMAGE -->
                    <div id="qr_code_box" class="hidden text-center py-2 bg-white rounded-lg border border-emerald-100">
                        <p class="text-xs font-bold text-gray-700 mb-2">Imbas Kod QR Di Bawah Untuk Pembayaran:</p>
                        <img src="{{ asset('images/qr-pay-maybank.jpg') }}" loading="lazy" decoding="async" alt="Warga Cakna DuitNow QR" class="h-64 max-w-full mx-auto rounded-lg shadow-sm border">
                        <a href="{{ asset('images/qr-pay-maybank.jpg') }}" target="_blank" class="inline-block mt-2 text-xs text-emerald-700 underline font-bold">Lihat Paparan Penuh QR</a>
                    </div>

                    <div>
                        <label id="ref_label" class="block text-xs font-bold text-gray-700 uppercase mb-1">Nombor Rujukan Transaksi *</label>
                        <input type="text" id="transaction_reference" name="transaction_reference" placeholder="Contoh: MBB12345678 / Ref No." class="w-full text-sm p-3 rounded-lg border border-gray-300 focus:outline-none focus:border-[#046a38]">
                    </div>

                    <div>
                        <label for="transaction_date" class="block text-xs font-bold text-gray-700 uppercase mb-1">Tarikh Transaksi Sebenar *</label>
                        <input type="date" id="transaction_date" name="transaction_date" value="{{ old('transaction_date') }}" max="{{ now()->toDateString() }}" class="w-full min-h-11 text-sm p-3 rounded-lg border border-gray-300 focus:outline-none focus:border-[#046a38]">
                        <p class="mt-1 text-[11px] text-gray-500">Digunakan sebagai Tarikh Sumbangan pada resit.</p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Muat Naik Resit / Bukti Bayaran (PDF/Gambar)</label>
                        <input type="file" name="proof_file" accept="image/*,.pdf" class="w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:bg-emerald-700 file:text-white file:font-bold hover:file:bg-emerald-800">
                    </div>
                </div>

                <!-- BUTANG SUBMIT -->
                <div class="pt-4">
                    <button type="submit" :disabled="submitting" class="w-full bg-[#046a38] hover:bg-[#03522b] disabled:cursor-wait disabled:opacity-70 text-white font-bold py-3.5 px-6 rounded-xl transition duration-150 shadow-sm text-base">
                        <span x-show="!submitting">Teruskan Pembayaran Sumbangan &rarr;</span>
                        <span x-cloak x-show="submitting">Sedang diproses...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

    <script>
        function setAmount(val) {
            document.getElementById('custom_amount').value = val;
        }

        function copyAccountNo() {
            navigator.clipboard.writeText("563037065007");
            alert("Nombor akaun Maybank Islamic (563037065007) berjaya disalin!");
        }

        function toggleManualFields(method) {
            const box = document.getElementById('manual_fields_box');
            const qrBox = document.getElementById('qr_code_box');
            const refInput = document.getElementById('transaction_reference');
            const dateInput = document.getElementById('transaction_date');
            const refLabel = document.getElementById('ref_label');

            if (method === 'billplz') {
                box.classList.add('hidden');
                refInput.removeAttribute('required');
                dateInput.removeAttribute('required');
            } else {
                box.classList.remove('hidden');
                refInput.setAttribute('required', 'required');
                dateInput.setAttribute('required', 'required');

                if (method === 'qr_pay') {
                    qrBox.classList.remove('hidden');
                    refLabel.innerText = 'Nombor Rujukan Transaksi DuitNow QR *';
                    refInput.placeholder = 'Contoh: QR123456789 / DuitNow Ref';
                } else {
                    qrBox.classList.add('hidden');
                    if (method === 'cash') {
                        refLabel.innerText = 'No. Resit Tunai / Rujukan Terimaan *';
                        refInput.placeholder = 'Contoh: CASH-20261017-01';
                    } else {
                        refLabel.innerText = 'Nombor Rujukan Transaksi Bank *';
                        refInput.placeholder = 'Contoh: MBB12345678 / Ref No.';
                    }
                }
            }
        }
    </script>

</x-app-layout>
