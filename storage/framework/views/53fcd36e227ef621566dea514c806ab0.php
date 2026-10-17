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
            <?php echo e(__('Kemaskini Resit Sumbangan')); ?> - <?php echo e($receipt->receipt_no); ?>

        </h2>
     <?php $__env->endSlot(); ?>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">

                <div class="mb-6 border-b pb-4 flex justify-between items-center">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">Borang Kemaskini Resit</h3>
                        <p class="text-xs text-gray-500">Kemaskini maklumat penderma, jumlah, atau status bayaran.</p>
                    </div>
                    <a href="<?php echo e(route('receipts.index')); ?>" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-1.5 px-4 rounded text-xs transition">
                        &larr; Kembali ke Senarai
                    </a>
                </div>

                <!-- MESEJ RALAT VALIDASI -->
                <?php if($errors->any()): ?>
                    <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative text-sm">
                        <strong class="font-bold">Sila periksa input anda:</strong>
                        <ul class="mt-1 list-disc list-inside text-xs">
                            <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <li><?php echo e($error); ?></li>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <!-- BORANG KEMASKINI -->
                <form action="<?php echo e(route('receipts.update', $receipt->id)); ?>" method="POST" class="space-y-4">
                    <?php echo csrf_field(); ?>
                    <?php echo method_field('PUT'); ?>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- NAMA PENDERMA -->
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Nama Penderma *</label>
                            <input type="text" name="donor_name" value="<?php echo e(old('donor_name', $receipt->donor_name)); ?>" required class="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                        </div>

                        <!-- ALAMAT EMEL -->
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Alamat Emel</label>
                            <input type="email" name="donor_email" value="<?php echo e(old('donor_email', $receipt->donor_email)); ?>" class="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                        </div>

                        <!-- TELEFON -->
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">No. Telefon</label>
                            <input type="text" name="donor_phone" value="<?php echo e(old('donor_phone', $receipt->donor_phone)); ?>" class="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                        </div>

                        <!-- JUMLAH (RM) -->
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Jumlah Sumbangan (RM) *</label>
                            <input type="number" step="0.01" name="amount" value="<?php echo e(old('amount', $receipt->amount)); ?>" required class="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                        </div>

                        <!-- KAEDAH BAYARAN -->
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Kaedah Bayaran *</label>
                            <select name="payment_method" required class="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                                <option value="online_transfer" <?php echo e(old('payment_method', $receipt->payment_method) == 'online_transfer' ? 'selected' : ''); ?>>Online Transfer / FPX</option>
                                <option value="cash" <?php echo e(old('payment_method', $receipt->payment_method) == 'cash' ? 'selected' : ''); ?>>Tunai (Cash)</option>
                                <option value="cheque" <?php echo e(old('payment_method', $receipt->payment_method) == 'cheque' ? 'selected' : ''); ?>>Cek</option>
                                <option value="qr_pay" <?php echo e(old('payment_method', $receipt->payment_method) == 'qr_pay' ? 'selected' : ''); ?>>DuitNow QR</option>
                            </select>
                        </div>

                        <!-- STATUS BAYARAN -->
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Status Bayaran *</label>
                            <select name="status" required class="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                                <option value="completed" <?php echo e(old('status', $receipt->status) == 'completed' ? 'selected' : ''); ?>>BERJAYA (Completed)</option>
                                <option value="pending" <?php echo e(old('status', $receipt->status) == 'pending' ? 'selected' : ''); ?>>BELUM BAYAR / PENDING</option>
                                <option value="failed" <?php echo e(old('status', $receipt->status) == 'failed' ? 'selected' : ''); ?>>GAGAL (Failed)</option>
                            </select>
                        </div>
                    </div>

                    <!-- CATATAN / TUJUAN -->
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Tujuan / Catatan Sumbangan</label>
                        <textarea name="description" rows="3" class="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"><?php echo e(old('description', $receipt->description)); ?></textarea>
                    </div>

                    <!-- BUTANG SIMPAN -->
                    <div class="pt-4 flex justify-end gap-2">
                        <a href="<?php echo e(route('receipts.index')); ?>" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded text-sm transition">
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
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $attributes = $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $component = $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?><?php /**PATH C:\xampp\htdocs\sistem-resit-dana\resources\views/receipts/edit.blade.php ENDPATH**/ ?>