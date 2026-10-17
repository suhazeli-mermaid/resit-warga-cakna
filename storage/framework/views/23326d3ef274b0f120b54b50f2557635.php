<?php if (isset($component)) { $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54 = $attributes; } ?>
<?php $component = App\View\Components\AppLayout::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('app-layout'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\AppLayout::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
     <?php $__env->slot('header', null, []); ?> 
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            <?php echo e(__('Senarai Resit Derma (Keseluruhan Transaksi)')); ?>

        </h2>
     <?php $__env->endSlot(); ?>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">

                <!-- BORANG CARIAN & SORTING -->
                <form method="GET" action="<?php echo e(route('receipts.index')); ?>" class="mb-6 grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Carian Kata Kunci</label>
                        <input type="text" name="search" value="<?php echo e(request('search')); ?>" placeholder="No Resit, Nama, Emel, Tel..." class="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Status Bayaran</label>
                        <select name="status" class="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                            <option value="">-- Semua Status --</option>
                            <option value="completed" <?php echo e(request('status') == 'completed' ? 'selected' : ''); ?>>BERJAYA</option>
                            <option value="pending" <?php echo e(request('status') == 'pending' ? 'selected' : ''); ?>>BELUM BAYAR / GAGAL</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Susunan (Sort)</label>
                        <select name="sort" class="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                            <option value="latest" <?php echo e(request('sort') == 'latest' || !request('sort') ? 'selected' : ''); ?>>Terkini Dahulu</option>
                            <option value="oldest" <?php echo e(request('sort') == 'oldest' ? 'selected' : ''); ?>>Terlama Dahulu</option>
                            <option value="amount_high" <?php echo e(request('sort') == 'amount_high' ? 'selected' : ''); ?>>Jumlah (Tinggi -> Rendah)</option>
                            <option value="amount_low" <?php echo e(request('sort') == 'amount_low' ? 'selected' : ''); ?>>Jumlah (Rendah -> Tinggi)</option>
                        </select>
                    </div>

                    <div class="flex items-end gap-2">
                        <button type="submit" class="bg-green-700 hover:bg-green-800 text-white font-bold py-2 px-4 rounded text-sm w-full transition">
                            Cari & Susun
                        </button>
                        <a href="<?php echo e(route('receipts.index')); ?>" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded text-sm text-center w-full transition">
                            Reset
                        </a>
                    </div>
                </form>

                <!-- MESEJ NOTIFIKASI -->
                <?php if(session('success')): ?>
                    <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative">
                        <?php echo e(session('success')); ?>

                    </div>
                <?php endif; ?>
                <?php if(session('error')): ?>
                    <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative">
                        <?php echo e(session('error')); ?>

                    </div>
                <?php endif; ?>

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
                            <?php $__empty_1 = true; $__currentLoopData = $receipts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $receipt): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="p-3 font-semibold text-emerald-800 whitespace-nowrap">
                                        <?php echo e($receipt->receipt_no); ?>

                                    </td>
                                    <td class="p-3">
                                        <div class="font-bold text-gray-900"><?php echo e($receipt->donor_name); ?></div>
                                        <div class="text-xs text-gray-500">
                                            <?php echo e($receipt->donor_email ?? '-'); ?> | <?php echo e($receipt->donor_phone ?? '-'); ?>

                                        </div>
                                    </td>
                                    <td class="p-3 whitespace-nowrap text-xs">
                                        <?php echo e(\Carbon\Carbon::parse($receipt->donated_at)->format('d/m/Y')); ?>

                                    </td>
                                    <td class="p-3 text-center whitespace-nowrap">
                                        <?php if($receipt->status === 'completed'): ?>
                                            <span class="inline-flex items-center px-2.5 py-1 rounded border border-green-300 text-xs font-bold bg-green-100 text-green-800">
                                                ✓ BERJAYA
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2.5 py-1 rounded border border-red-300 text-xs font-bold bg-red-100 text-red-800">
                                                ✕ BELUM BAYAR / GAGAL
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-3 text-right font-bold text-emerald-900 whitespace-nowrap">
                                        RM <?php echo e(number_format($receipt->amount, 2)); ?>

                                    </td>
                                    <td class="p-3 text-center whitespace-nowrap">
                                        <div class="flex items-center justify-center gap-1.5 flex-wrap">
                                            <!-- BUTANG PAPAR -->
                                            <a href="<?php echo e(route('receipts.show', $receipt->id)); ?>" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-1 px-2.5 rounded text-xs transition">
                                                Papar
                                            </a>

                                            <!-- BUTANG PDF -->
                                            <a href="<?php echo e(route('receipts.download-pdf', $receipt->id)); ?>" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-1 px-2.5 rounded text-xs transition" target="_blank">
                                                PDF
                                            </a>

                                            <!-- BUTANG WHATSAPP -->
                                            <?php
                                                $cleanPhone = preg_replace('/[^0-9]/', '', $receipt->donor_phone ?? '');
                                                if (str_starts_with($cleanPhone, '0')) {
                                                    $cleanPhone = '6' . $cleanPhone;
                                                }
                                                $msg = urlencode("Salam, berikut pautan resit sumbangan WARGA CAKNA anda [{$receipt->receipt_no}]: " . route('receipts.view-pdf', $receipt->id));
                                            ?>
                                            <?php if($cleanPhone): ?>
                                                <a href="https://wa.me/<?php echo e($cleanPhone); ?>?text=<?php echo e($msg); ?>" target="_blank" class="bg-green-600 hover:bg-green-700 text-white font-bold py-1 px-2.5 rounded text-xs transition">
                                                    WhatsApp
                                                </a>
                                            <?php endif; ?>

                                            <!-- BUTANG EMEL -->
                                            <?php if($receipt->donor_email): ?>
                                                <form action="<?php echo e(route('receipts.resend-email', $receipt->id)); ?>" method="POST" class="inline" onsubmit="return confirm('Hantar emel resit ini kepada <?php echo e($receipt->donor_email); ?>?');">
                                                    <?php echo csrf_field(); ?>
                                                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-1 px-2.5 rounded text-xs transition">
                                                        Emel
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <!-- BUTANG KEMASKINI -->
                                            <a href="<?php echo e(route('receipts.edit', $receipt->id)); ?>" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-1 px-2.5 rounded text-xs transition">
                                                Kemaskini
                                            </a>

                                            <!-- BUTANG SAHKAN (HANYA JIKA BELUM BAYAR) -->
                                            <?php if($receipt->status !== 'completed'): ?>
                                                <form action="<?php echo e(route('receipts.verify', $receipt->id)); ?>" method="POST" class="inline" onsubmit="return confirm('Mengesahkan bayaran ini? Status akan bertukar ke BERJAYA.');">
                                                    <?php echo csrf_field(); ?>
                                                    <?php echo method_field('PATCH'); ?>
                                                    <button type="submit" class="bg-amber-500 hover:bg-amber-600 text-white font-bold py-1 px-2.5 rounded text-xs transition">
                                                        Sahkan
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                <tr>
                                    <td colspan="6" class="p-6 text-center text-gray-500">
                                        Tiada rekod resit ditemui.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- PAGINATION -->
                <div class="mt-4">
                    <?php echo e($receipts->links()); ?>

                </div>

            </div>
        </div>
    </div>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $attributes = $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $component = $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?><?php /**PATH C:\xampp\htdocs\sistem-resit-dana\resources\views/receipts/index.blade.php ENDPATH**/ ?>