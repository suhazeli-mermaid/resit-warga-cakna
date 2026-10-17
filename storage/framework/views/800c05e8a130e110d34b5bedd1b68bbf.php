<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cipta Resit Derma Baharu - WARGA CAKNA</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">

    <nav class="bg-emerald-800 text-white shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
            <div class="flex items-center space-x-3">
                <img src="<?php echo e(asset('images/logo-warga-cakna.png')); ?>" class="h-10 w-auto bg-white rounded-full p-1" alt="WARGA CAKNA Logo">
                <h1 class="text-lg font-bold">WARGA CAKNA - Resit Baharu</h1>
            </div>
            <a href="<?php echo e(route('receipts.index')); ?>" class="bg-emerald-700 hover:bg-emerald-900 text-white font-medium px-4 py-2 rounded-lg text-xs transition">
                &larr; Kembali ke Senarai Resit
            </a>
        </div>
    </nav>

    <main class="max-w-3xl mx-auto px-4 py-8">
        <div class="bg-white rounded-lg shadow-md border border-gray-200 overflow-hidden">
            <div class="bg-emerald-900 text-white px-6 py-4">
                <h2 class="text-base font-bold uppercase tracking-wider">Borang Penjanaan Resit Derma</h2>
            </div>

            <form action="<?php echo e(route('receipts.store')); ?>" method="POST" class="p-6 space-y-4">
                <?php echo csrf_field(); ?>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Nama Penderma <span class="text-red-500">*</span></label>
                        <input type="text" name="donor_name" required value="<?php echo e(old('donor_name')); ?>" class="w-full border border-gray-300 rounded p-2 text-sm focus:ring-2 focus:ring-emerald-600 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">No. KP / Passport (Pilihan)</label>
                        <input type="text" name="donor_ic_passport" value="<?php echo e(old('donor_ic_passport')); ?>" class="w-full border border-gray-300 rounded p-2 text-sm focus:ring-2 focus:ring-emerald-600 focus:outline-none">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">No. Telefon Penderma</label>
                        <input type="text" name="donor_phone" value="<?php echo e(old('donor_phone')); ?>" placeholder="Contoh: 0199563653" class="w-full border border-gray-300 rounded p-2 text-sm focus:ring-2 focus:ring-emerald-600 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Emel Penderma</label>
                        <input type="email" name="donor_email" value="<?php echo e(old('donor_email')); ?>" placeholder="penderma@gmail.com" class="w-full border border-gray-300 rounded p-2 text-sm focus:ring-2 focus:ring-emerald-600 focus:outline-none">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Jumlah Derma (RM) <span class="text-red-500">*</span></label>
                        <input type="number" step="0.01" name="amount" required value="<?php echo e(old('amount')); ?>" placeholder="10.00" class="w-full border border-gray-300 rounded p-2 text-sm focus:ring-2 focus:ring-emerald-600 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Kaedah Bayaran <span class="text-red-500">*</span></label>
                        <select name="payment_method" required class="w-full border border-gray-300 rounded p-2 text-sm focus:ring-2 focus:ring-emerald-600 focus:outline-none">
                            <option value="bank_transfer" <?php echo e(old('payment_method') == 'bank_transfer' ? 'selected' : ''); ?>>Pindahan Bank / Transfer Online</option>
                            <option value="qr_pay" <?php echo e(old('payment_method') == 'qr_pay' ? 'selected' : ''); ?>>QR Pay</option>
                            <option value="fpx" <?php echo e(old('payment_method') == 'fpx' ? 'selected' : ''); ?>>FPX / ToyyibPay</option>
                            <option value="cash" <?php echo e(old('payment_method') == 'cash' ? 'selected' : ''); ?>>Tunai / Cash</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Tarikh Derma <span class="text-red-500">*</span></label>
                        <input type="date" name="donated_at" required value="<?php echo e(old('donated_at', date('Y-m-d'))); ?>" class="w-full border border-gray-300 rounded p-2 text-sm focus:ring-2 focus:ring-emerald-600 focus:outline-none">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">No. Rujukan Bayaran / Resit Bank</label>
                    <input type="text" name="payment_reference" value="<?php echo e(old('payment_reference')); ?>" placeholder="Contoh: Ref-8849204" class="w-full border border-gray-300 rounded p-2 text-sm focus:ring-2 focus:ring-emerald-600 focus:outline-none">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Tujuan / Keterangan Derma</label>
                    <textarea name="description" rows="2" placeholder="Sumbangan Dana Tabung Kebajikan WARGA CAKNA" class="w-full border border-gray-300 rounded p-2 text-sm focus:ring-2 focus:ring-emerald-600 focus:outline-none"><?php echo e(old('description')); ?></textarea>
                </div>

                <div class="pt-4 flex justify-end space-x-3">
                    <a href="<?php echo e(route('receipts.index')); ?>" class="px-4 py-2 bg-gray-200 text-gray-700 text-xs font-bold rounded hover:bg-gray-300 transition">Batal</a>
                    <button type="submit" class="px-5 py-2 bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold rounded shadow transition">
                        Simpan & Jana Resit
                    </button>
                </div>
            </form>
        </div>
    </main>

</body>
</html><?php /**PATH C:\xampp\htdocs\sistem-resit-dana\resources\views/receipts/create.blade.php ENDPATH**/ ?>