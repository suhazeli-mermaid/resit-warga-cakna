<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($campaign->title); ?> - WARGA CAKNA</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#f3f4f6] min-h-screen py-10 px-4">

    <div class="max-w-4xl mx-auto">
        <!-- BUTANG KEMBALI KE SENARAI DANA -->
        <div class="mb-6">
            <a href="<?php echo e(route('public.index')); ?>" class="text-[#046a38] hover:underline font-bold text-sm inline-flex items-center gap-1">
                &larr; Kembali ke Senarai Dana
            </a>
        </div>

        <?php if(session('error')): ?>
            <div class="bg-red-100 border border-red-400 text-red-800 px-4 py-3 rounded-xl mb-6 text-sm">
                <?php echo e(session('error')); ?>

            </div>
        <?php endif; ?>

        <?php if(session('success')): ?>
            <div class="bg-green-100 border border-green-400 text-green-800 px-4 py-3 rounded-xl mb-6 text-sm">
                <?php echo e(session('success')); ?>

            </div>
        <?php endif; ?>

        <!-- KAD UTAMA KEMPEN (PERSIS REKA BENTUK ASAL) -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 md:p-10 mb-8">
            <h1 class="text-3xl font-extrabold text-gray-900 mb-4 tracking-tight">
                <?php echo e($campaign->title); ?>

            </h1>

            <?php if($campaign->image_url || isset($campaign->banner_image)): ?>
                <div class="mb-6 rounded-xl overflow-hidden border border-gray-100">
                    <img src="<?php echo e($campaign->image_url ?? asset('storage/' . $campaign->banner_image)); ?>" alt="<?php echo e($campaign->title); ?>" class="w-full h-64 object-cover">
                </div>
            <?php endif; ?>

            <div class="text-gray-600 text-base leading-relaxed mb-8 space-y-4">
                <?php echo nl2br(e($campaign->description)); ?>

            </div>

            <div class="border-t border-gray-100 pt-6">
                <a href="#sumbang" class="block w-full text-center bg-[#046a38] hover:bg-[#03522b] text-white font-bold py-3.5 px-6 rounded-xl transition duration-150 shadow-sm text-base">
                    Sumbang Sekarang &rarr;
                </a>
            </div>
        </div>

        <!-- BORANG SUMBANGAN LENGKAP (#sumbang) -->
        <div id="sumbang" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 md:p-10 scroll-mt-6">
            <h2 class="text-2xl font-bold text-gray-900 mb-2 border-b pb-4">Borang Sumbangan Derma</h2>
            <p class="text-xs text-gray-500 mb-6">Sila lengkapkan maklumat di bawah untuk menyumbang kepada <strong><?php echo e($campaign->title); ?></strong>.</p>

            <form action="<?php echo e(route('public.donate.process')); ?>" method="POST" class="space-y-6">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="campaign_id" value="<?php echo e($campaign->slug ?? $campaign->id); ?>">
                <input type="hidden" name="description" value="Sumbangan Bagi <?php echo e($campaign->title); ?>">

                <!-- PILIHAN PRESET JUMLAH SUMBANGAN -->
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-2">Pilih Jumlah Sumbangan (RM) *</label>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-3">
                        <button type="button" onclick="setAmount(10)" class="amount-btn border-2 border-gray-200 rounded-xl py-2.5 font-bold text-gray-700 hover:border-[#046a38] hover:text-[#046a38] transition">RM 10</button>
                        <button type="button" onclick="setAmount(30)" class="amount-btn border-2 border-gray-200 rounded-xl py-2.5 font-bold text-gray-700 hover:border-[#046a38] hover:text-[#046a38] transition">RM 30</button>
                        <button type="button" onclick="setAmount(50)" class="amount-btn border-2 border-gray-200 rounded-xl py-2.5 font-bold text-gray-700 hover:border-[#046a38] hover:text-[#046a38] transition">RM 50</button>
                        <button type="button" onclick="setAmount(100)" class="amount-btn border-2 border-gray-200 rounded-xl py-2.5 font-bold text-gray-700 hover:border-[#046a38] hover:text-[#046a38] transition">RM 100</button>
                    </div>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center font-bold text-gray-500 text-sm">RM</span>
                        <input type="number" id="custom_amount" name="amount" step="0.01" min="1" value="<?php echo e(old('amount', 25)); ?>" required placeholder="Atau masukkan jumlah pilihan" class="w-full text-sm pl-10 pr-3 py-3 rounded-lg border border-gray-300 font-bold text-[#046a38] focus:outline-none focus:border-[#046a38] focus:ring-1 focus:ring-[#046a38]">
                    </div>
                </div>

                <!-- MAKLUMAT PENDERMA -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Nama Penuh *</label>
                        <input type="text" name="donor_name" value="<?php echo e(old('donor_name')); ?>" placeholder="Nama penderma" required class="w-full text-sm p-3 rounded-lg border border-gray-300 focus:outline-none focus:border-[#046a38] focus:ring-1 focus:ring-[#046a38]">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">No. Telefon *</label>
                        <input type="text" name="donor_phone" value="<?php echo e(old('donor_phone')); ?>" placeholder="01X-XXXXXXX" required class="w-full text-sm p-3 rounded-lg border border-gray-300 focus:outline-none focus:border-[#046a38] focus:ring-1 focus:ring-[#046a38]">
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Alamat Emel (Untuk Resit Digital)</label>
                        <input type="email" name="donor_email" value="<?php echo e(old('donor_email')); ?>" placeholder="contoh@email.com" class="w-full text-sm p-3 rounded-lg border border-gray-300 focus:outline-none focus:border-[#046a38] focus:ring-1 focus:ring-[#046a38]">
                    </div>
                </div>

                <!-- KAEDAH PEMBAYARAN -->
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-2">Pilih Kaedah Pembayaran *</label>
                    <div class="space-y-2">
                        <label class="flex items-center p-3 border rounded-xl cursor-pointer hover:bg-gray-50">
                            <input type="radio" name="payment_method" value="toyyibpay" checked class="text-[#046a38] focus:ring-[#046a38]">
                            <span class="ml-3 font-semibold text-sm text-gray-800">ToyyibPay (FPX / Online Banking / Credit Card)</span>
                        </label>
                        <label class="flex items-center p-3 border rounded-xl cursor-pointer hover:bg-gray-50">
                            <input type="radio" name="payment_method" value="qr_pay" class="text-[#046a38] focus:ring-[#046a38]">
                            <span class="ml-3 font-semibold text-sm text-gray-800">DuitNow QR / FPX Direct Transfer</span>
                        </label>
                        <label class="flex items-center p-3 border rounded-xl cursor-pointer hover:bg-gray-50">
                            <input type="radio" name="payment_method" value="cash" class="text-[#046a38] focus:ring-[#046a38]">
                            <span class="ml-3 font-semibold text-sm text-gray-800">Tunai (Deposit / Walk-in)</span>
                        </label>
                    </div>
                </div>

                <!-- BUTANG HANTAR -->
                <div class="pt-4">
                    <button type="submit" class="w-full bg-[#046a38] hover:bg-[#03522b] text-white font-bold py-3.5 px-6 rounded-xl transition duration-150 shadow-sm text-base">
                        Teruskan Pembayaran Sumbangan &rarr;
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function setAmount(val) {
            document.getElementById('custom_amount').value = val;
        }
    </script>

</body>
</html><?php /**PATH C:\xampp\htdocs\sistem-resit-dana\resources\views/public/show.blade.php ENDPATH**/ ?>