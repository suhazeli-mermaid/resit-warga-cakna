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
            <?php echo e(__('Admin Hub / Pengurusan Pengguna & AJK')); ?>

        </h2>
     <?php $__env->endSlot(); ?>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">

                <div class="mb-6 flex justify-between items-center border-b pb-4">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">Senarai Akaun Pengguna & AJK</h3>
                        <p class="text-xs text-gray-500">Uruskan peranan (Role) akaun yang berdaftar dalam sistem WARGA CAKNA.</p>
                    </div>
                    <div class="text-xs font-bold bg-emerald-100 text-emerald-800 px-3 py-1.5 rounded-full">
                        Jumlah Pengguna: <?php echo e($users->total()); ?>

                    </div>
                </div>

                <!-- MESEJ NOTIFIKASI -->
                <?php if(session('success')): ?>
                    <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative text-sm">
                        <?php echo e(session('success')); ?>

                    </div>
                <?php endif; ?>
                <?php if(session('error')): ?>
                    <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative text-sm">
                        <?php echo e(session('error')); ?>

                    </div>
                <?php endif; ?>

                <!-- JADUAL SENARAI PENGGUNA -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-emerald-900 text-white text-xs uppercase font-bold">
                                <th class="p-3 border">BIL</th>
                                <th class="p-3 border">NAMA PENGGUNA</th>
                                <th class="p-3 border">ALAMAT EMEL</th>
                                <th class="p-3 border text-center">PERANAN (ROLE) SEMASA</th>
                                <th class="p-3 border text-center">TARIKH DAFTAR</th>
                                <th class="p-3 border text-center">TINDAKAN KEMASKINI ROLE</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y text-sm">
                            <?php $__empty_1 = true; $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="p-3 border text-center font-bold text-gray-600">
                                        <?php echo e($users->firstItem() + $index); ?>

                                    </td>
                                    <td class="p-3 border font-bold text-gray-900">
                                        <?php echo e($user->name); ?>

                                        <?php if(Auth::id() === $user->id): ?>
                                            <span class="ml-1 text-xs text-emerald-600 font-semibold">(Akaun Anda)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-3 border text-gray-600">
                                        <?php echo e($user->email); ?>

                                    </td>
                                    <td class="p-3 border text-center">
                                        <?php if($user->role === 'admin'): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-bold bg-purple-100 text-purple-800 border border-purple-300">
                                                ADMIN
                                            </span>
                                        <?php elseif($user->role === 'ajk'): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-bold bg-blue-100 text-blue-800 border border-blue-300">
                                                AJK
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-bold bg-gray-100 text-gray-800 border border-gray-300">
                                                PENGGUNA
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-3 border text-center text-xs text-gray-500 whitespace-nowrap">
                                        <?php echo e($user->created_at ? $user->created_at->format('d/m/Y h:i A') : '-'); ?>

                                    </td>
                                    <td class="p-3 border text-center">
                                        <form action="<?php echo e(route('users.update-role', $user->id)); ?>" method="POST" class="flex items-center justify-center gap-2">
                                            <?php echo csrf_field(); ?>
                                            <?php echo method_field('PATCH'); ?>
                                            <select name="role" class="text-xs rounded border-gray-300 py-1 px-2 focus:ring-emerald-500 focus:border-emerald-500">
                                                <option value="user" <?php echo e($user->role === 'user' || !$user->role ? 'selected' : ''); ?>>Pengguna</option>
                                                <option value="ajk" <?php echo e($user->role === 'ajk' ? 'selected' : ''); ?>>AJK</option>
                                                <option value="admin" <?php echo e($user->role === 'admin' ? 'selected' : ''); ?>>Admin</option>
                                            </select>
                                            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold py-1 px-3 rounded text-xs transition">
                                                Kemaskini
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                <tr>
                                    <td colspan="6" class="p-6 text-center text-gray-500">
                                        Tiada pengguna berdaftar.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- PAGINATION -->
                <div class="mt-4">
                    <?php echo e($users->links()); ?>

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
<?php endif; ?><?php /**PATH C:\xampp\htdocs\sistem-resit-dana\resources\views/users/index.blade.php ENDPATH**/ ?>