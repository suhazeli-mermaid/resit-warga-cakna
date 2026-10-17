<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Papan Pemuka & Admin Hub - WARGA CAKNA</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">

    <nav class="bg-emerald-800 text-white shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex flex-wrap justify-between items-center gap-4">
            <div class="flex items-center space-x-3">
                <img src="{{ asset('images/logo-warga-cakna.png') }}" class="h-10 w-auto bg-white rounded-full p-1" alt="WARGA CAKNA Logo">
                <div>
                    <h1 class="text-lg font-bold leading-tight">WARGA CAKNA</h1>
                    <p class="text-xs text-emerald-200">Sistem Pengurusan Resit & Admin Hub</p>
                </div>
            </div>

            <div class="flex items-center space-x-3">
                <a href="{{ route('home') }}" class="bg-emerald-700 hover:bg-emerald-900 text-white font-medium px-3 py-2 rounded-lg text-xs shadow transition">
                    &larr; Portal Awam Dana
                </a>
                <a href="{{ route('receipts.create') }}" class="bg-yellow-400 hover:bg-yellow-500 text-emerald-950 font-bold px-4 py-2 rounded-lg text-sm shadow transition">
                    + Cipta Resit Baharu
                </a>
                <form method="POST" action="{{ route('logout') }}" class="inline">
                    @csrf
                    <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-bold px-3 py-2 rounded-lg text-xs shadow transition">
                        Log Keluar
                    </button>
                </form>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <div class="bg-white rounded-lg p-5 mb-6 shadow-sm border border-gray-200 flex flex-wrap justify-between items-center gap-4">
            <div>
                <span class="text-xs text-emerald-800 font-bold uppercase tracking-wider">Akaun Pengguna</span>
                <h3 class="text-lg font-extrabold text-gray-800">{{ Auth::user()->name }} <span class="text-xs font-normal text-gray-500">({{ Auth::user()->email }})</span></h3>
            </div>
            <div>
                <span class="bg-emerald-100 text-emerald-800 text-xs font-bold px-3 py-1.5 rounded-full border border-emerald-300 uppercase">
                    Peranan: {{ Auth::user()->role ?? 'Penderma' }}
                </span>
            </div>
        </div>

        @if(session('success'))
            <div class="bg-emerald-100 border border-emerald-400 text-emerald-800 px-4 py-3 rounded mb-6 flex justify-between items-center shadow-sm">
                <span>{{ session('success') }}</span>
                <button onclick="this.parentElement.remove()" class="font-bold text-emerald-900">&times;</button>
            </div>
        @endif

        @if(session('error'))
            <div class="bg-red-100 border border-red-400 text-red-800 px-4 py-3 rounded mb-6 flex justify-between items-center shadow-sm">
                <span>{{ session('error') }}</span>
                <button onclick="this.parentElement.remove()" class="font-bold text-red-900">&times;</button>
            </div>
        @endif

        @if(Auth::user()->isAjk())
            <!-- ADMIN HUB: Tetapan Tandatangan Digital Bendahari -->
            <div class="bg-white rounded-lg shadow border border-gray-200 mb-8 overflow-hidden">
                <div class="bg-gray-800 text-white px-6 py-3 flex justify-between items-center">
                    <h3 class="font-bold text-sm uppercase tracking-wide">
                        Tetapan Tandatangan Digital Bendahari (HJ KASMADI IBRAHIM)
                    </h3>
                    <span class="text-xs bg-gray-700 px-2.5 py-1 rounded text-gray-200">Tetapan Cetakan Resit</span>
                </div>

                <div class="p-6 bg-gray-50 flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center space-x-4">
                        @if(!empty($signaturePath))
                            <div class="bg-white p-2 border border-gray-300 rounded shadow-sm">
                                <img src="{{ asset('storage/' . $signaturePath) }}" class="h-12 w-auto object-contain" alt="Tandatangan Bendahari">
                            </div>
                            <span class="text-xs text-emerald-700 font-bold">✓ Tandatangan Berjaya Dimuat Naik</span>
                        @else
                            <div class="bg-gray-200 h-12 w-32 rounded flex items-center justify-center text-xs text-gray-500 font-bold border border-dashed border-gray-400">
                                Tiada Imej
                            </div>
                            <span class="text-xs text-gray-500">Sila muat naik imej tandatangan (PNG/JPG lutsinar)</span>
                        @endif
                    </div>

                    <form action="{{ route('admin.upload-signature') }}" method="POST" enctype="multipart/form-data" class="flex items-center space-x-2">
                        @csrf
                        <input type="file" name="signature" accept="image/*" required class="text-xs text-gray-500 border border-gray-300 rounded p-1.5 bg-white">
                        <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-3 py-2 rounded text-xs transition">
                            Muat Naik Tandatangan
                        </button>
                    </form>
                </div>
            </div>

            <!-- ADMIN HUB: Cipta / Buka Kempen Dana Baharu -->
            <div class="bg-white rounded-lg shadow border border-gray-200 mb-8 overflow-hidden">
                <div class="bg-emerald-900 text-white px-6 py-3 flex justify-between items-center">
                    <h3 class="font-bold text-sm uppercase tracking-wide">
                        Admin Hub: Tambah Dana / Kempen Baharu
                    </h3>
                    <span class="text-xs bg-emerald-800 px-2.5 py-1 rounded text-emerald-200">Pengurusan Dana Awam</span>
                </div>

                <form action="{{ route('campaigns.store') }}" method="POST" enctype="multipart/form-data" class="p-6 bg-gray-50 border-b border-gray-200">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Tajuk Dana / Kempen <span class="text-red-500">*</span></label>
                            <input type="text" name="title" required placeholder="Contoh: Dana Bantuan Gaza" class="w-full border-gray-300 rounded-md border p-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Sasaran Kutipan (RM)</label>
                            <input type="number" step="0.01" name="target_amount" placeholder="Contoh: 50000" class="w-full border-gray-300 rounded-md border p-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Muat Naik Gambar / Poster</label>
                            <input type="file" name="image" accept="image/*" class="w-full text-xs text-gray-500 border border-gray-300 rounded-md p-1.5 bg-white">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Keterangan Ringkas</label>
                            <input type="text" name="description" placeholder="Keterangan singkat bagi kempen..." class="w-full border-gray-300 rounded-md border p-2 text-sm">
                        </div>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-4 py-2 rounded-md text-xs shadow transition">
                            + Buka & Siarkan Dana
                        </button>
                    </div>
                </form>

                <!-- SENARAI KEMPEN UNTUK DITUKAR / DIEDIT / DIPADAM -->
                <div class="p-6">
                    <h4 class="font-bold text-gray-800 text-sm mb-3">Senarai Kempen Dana Sedia Ada (Kemaskini Gambar / Maklumat)</h4>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-gray-600">
                            <thead class="bg-gray-100 uppercase font-bold text-gray-700">
                                <tr>
                                    <th class="p-3">Gambar</th>
                                    <th class="p-3">Tajuk Kempen</th>
                                    <th class="p-3">Sasaran (RM)</th>
                                    <th class="p-3">Terkumpul (RM)</th>
                                    <th class="p-3 text-center">Tindakan Kemaskini</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php if (isset($campaigns) && count($campaigns) > 0): ?>
                                    <?php foreach ($campaigns as$camp): ?>
                                        <tr>
                                            <td class="p-3 w-16">
                                                <?php if (!empty($camp->image_path)): ?>
                                                    <img src="<?php echo asset('storage/' . $camp->image_path); ?>" class="w-12 h-12 object-cover rounded shadow border">
                                                <?php else: ?>
                                                    <span class="inline-block w-12 h-12 bg-gray-200 rounded text-center leading-10 text-gray-400 font-bold">Tiada</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="p-3 font-bold text-gray-900"><?php echo htmlspecialchars($camp->title); ?></td>
                                            <td class="p-3">RM <?php echo number_format($camp->target_amount, 2); ?></td>
                                            <td class="p-3 text-emerald-700 font-bold">RM <?php echo number_format($camp->collected_amount, 2); ?></td>
                                            <td class="p-3 text-center">
                                                <div class="flex justify-center space-x-2">
                                                    <button onclick="openEditModal(<?php echo $camp->id; ?>, '<?php echo addslashes($camp->title); ?>', '<?php echo $camp->target_amount; ?>', '<?php echo addslashes($camp->description ?? ''); ?>')" class="bg-blue-600 hover:bg-blue-700 text-white px-2.5 py-1 rounded font-bold transition">
                                                        Edit / Tukar Gambar
                                                    </button>

                                                    <form action="<?php echo route('campaigns.destroy', $camp->id); ?>" method="POST" onsubmit="return confirm('Adakah anda pasti mahu memadam kempen ini?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-2.5 py-1 rounded font-bold transition">
                                                            Padam
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="p-4 text-center text-gray-400">Tiada kempen dibuka buat masa ini.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        @if(Auth::user()->isAdmin())
            <!-- SUPER ADMIN HUB: Lantik Pengguna Sebagai Admin / AJK -->
            <div class="bg-white rounded-lg shadow border border-gray-200 mb-8 overflow-hidden">
                <div class="bg-gray-800 text-white px-6 py-3">
                    <h3 class="font-bold text-sm uppercase tracking-wide">
                        Super Admin Hub: Pengurusan Peranan Pengguna (AJK / Admin)
                    </h3>
                </div>

                <div class="p-6 overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-600">
                        <thead class="bg-gray-100 uppercase text-xs text-gray-700">
                            <tr>
                                <th class="px-4 py-2">Nama Pengguna</th>
                                <th class="px-4 py-2">Emel</th>
                                <th class="px-4 py-2">Peranan Semasa</th>
                                <th class="px-4 py-2 text-center">Tindakan Lantikan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php if (isset($users) && count($users) > 0): ?>
                                <?php foreach ($users as$user): ?>
                                    <tr>
                                        <td class="px-4 py-3 font-semibold text-gray-900"><?php echo htmlspecialchars($user->name); ?></td>
                                        <td class="px-4 py-3"><?php echo htmlspecialchars($user->email); ?></td>
                                        <td class="px-4 py-3">
                                            <span class="px-2.5 py-1 rounded text-xs font-bold uppercase <?php echo $user->role === 'admin' ? 'bg-red-100 text-red-800' : ($user->role === 'ajk' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800'); ?>">
                                                <?php echo htmlspecialchars($user->role ?? 'penderma'); ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <form action="<?php echo route('users.update-role', $user->id); ?>" method="POST" class="inline-flex items-center space-x-2">
                                                @csrf
                                                <select name="role" class="border-gray-300 rounded text-xs p-1">
                                                    <option value="penderma" <?php echo $user->role === 'penderma' ? 'selected' : ''; ?>>Penderma</option>
                                                    <option value="ajk" <?php echo $user->role === 'ajk' ? 'selected' : ''; ?>>AJK</option>
                                                    <option value="admin" <?php echo $user->role === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                                </select>
                                                <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold px-2.5 py-1 rounded transition">
                                                    Kemaskini
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <!-- SENARAI RESIT DERMA & STATUS BAYARAN -->
        <div class="bg-white rounded-lg shadow overflow-hidden border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center bg-gray-50">
                <h2 class="text-lg font-bold text-gray-800">Senarai Resit Derma <?php echo Auth::user()->isAjk() ? '(Keseluruhan Transaksi)' : 'Saya'; ?></h2>
                <span class="text-sm text-gray-500">Jumlah Rekod: <?php echo $receipts->total(); ?></span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-gray-600">
                    <thead class="bg-emerald-800 text-white uppercase text-xs">
                        <tr>
                            <th class="px-4 py-3">No. Resit</th>
                            <th class="px-4 py-3">Nama Penderma & Info</th>
                            <th class="px-4 py-3">Tarikh</th>
                            <th class="px-4 py-3">Status Bayaran</th>
                            <th class="px-4 py-3 text-right">Jumlah (RM)</th>
                            <th class="px-4 py-3 text-center">Tindakan Admin / Cetak</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if (count($receipts) > 0): ?>
                            <?php foreach ($receipts as$receipt): ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-4 py-4 font-mono font-bold text-emerald-700">
                                        <?php echo htmlspecialchars($receipt->receipt_no); ?>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="font-semibold text-gray-900"><?php echo htmlspecialchars($receipt->donor_name); ?></div>
                                        <div class="text-xs text-gray-500"><?php echo htmlspecialchars($receipt->donor_email ?? 'Tiada Emel'); ?> | <?php echo htmlspecialchars($receipt->donor_phone ?? 'Tiada Tel'); ?></div>
                                    </td>
                                    <td class="px-4 py-4"><?php echo \Carbon\Carbon::parse($receipt->donated_at)->format('d/m/Y'); ?></td>
                                    <td class="px-4 py-4">
                                        <?php if (!empty($receipt->is_paid_status)): ?>
                                            <span class="bg-emerald-100 text-emerald-800 border border-emerald-300 font-bold px-2.5 py-1 rounded text-xs uppercase inline-block">
                                                ✓ BERJAYA
                                            </span>
                                        <?php else: ?>
                                            <span class="bg-red-100 text-red-800 border border-red-300 font-bold px-2.5 py-1 rounded text-xs uppercase inline-block">
                                                ✕ BELUM BAYAR / GAGAL
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-4 text-right font-bold text-emerald-700">RM <?php echo number_format($receipt->amount, 2); ?></td>
                                    <td class="px-4 py-4 text-center">
                                        <div class="flex items-center justify-center space-x-1.5">
                                            <!-- Butang Papar PDF Terus dalam Browser -->
                                            <a href="<?php echo route('receipts.view-pdf', $receipt->id); ?>" target="_blank" class="bg-blue-600 hover:bg-blue-700 text-white font-medium px-2.5 py-1.5 rounded text-xs transition shadow-sm" title="Papar PDF dalam Browser">
                                                Papar
                                            </a>

                                            <!-- Butang Muat Turun PDF -->
                                            <a href="<?php echo route('receipts.pdf', $receipt->id); ?>" class="bg-emerald-600 hover:bg-emerald-700 text-white font-medium px-2.5 py-1.5 rounded text-xs transition shadow-sm" title="Muat Turun PDF">
                                                PDF
                                            </a>

                                            <?php if (Auth::user()->isAjk() && empty($receipt->is_paid_status)): ?>
                                                <!-- Butang Direct WhatsApp Penderma -->
                                                <?php if (!empty($receipt->clean_phone)): ?>
                                                    <a href="https://wa.me/<?php echo $receipt->clean_phone; ?>?text=<?php echo$receipt->wa_text; ?>" target="_blank" class="bg-green-600 hover:bg-green-700 text-white font-bold px-2.5 py-1.5 rounded text-xs transition shadow-sm" title="WhatsApp Penderma">
                                                        WhatsApp
                                                    </a>
                                                <?php endif; ?>

                                                <!-- Butang Hantar Peringatan Emel -->
                                                <?php if (!empty($receipt->donor_email)): ?>
                                                    <form action="<?php echo route('receipts.remind-email', $receipt->id); ?>" method="POST" class="inline">
                                                        @csrf
                                                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-2.5 py-1.5 rounded text-xs transition shadow-sm" title="Hantar Emel Peringatan">
                                                            Emel
                                                        </button>
                                                    </form>
                                                <?php endif; ?>

                                                <!-- Butang Sahkan Bayaran Manual -->
                                                <form action="<?php echo route('receipts.mark-paid', $receipt->id); ?>" method="POST" class="inline" onsubmit="return confirm('Sahkan resit ini telah berjaya diterima bayarannya?')">
                                                    @csrf
                                                    <button type="submit" class="bg-yellow-500 hover:bg-yellow-600 text-white font-bold px-2.5 py-1.5 rounded text-xs transition shadow-sm" title="Sahkan Bayaran Manual">
                                                        Sahkan
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center text-gray-400">
                                    Tiada rekod resit derma dijumpai.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                <?php echo $receipts->links(); ?>
            </div>
        </div>
    </main>

    <!-- MODAL POPUP EDIT KEMPEN -->
    <div id="editModal" class="hidden fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex justify-center items-center p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-lg w-full overflow-hidden">
            <div class="bg-emerald-800 text-white px-6 py-4 flex justify-between items-center">
                <h3 class="font-bold text-base">Kemaskini Kempen Dana</h3>
                <button onclick="closeEditModal()" class="text-white text-xl font-bold">&times;</button>
            </div>
            
            <form id="editForm" method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Tajuk Kempen <span class="text-red-500">*</span></label>
                    <input type="text" id="edit_title" name="title" required class="w-full border-gray-300 rounded border p-2 text-sm">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Sasaran Kutipan (RM)</label>
                    <input type="number" step="0.01" id="edit_target_amount" name="target_amount" class="w-full border-gray-300 rounded border p-2 text-sm">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Ganti Gambar / Poster Baharu (Pilihan)</label>
                    <input type="file" name="image" accept="image/*" class="w-full text-xs text-gray-500 border border-gray-300 rounded p-1.5 bg-white">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Keterangan</label>
                    <textarea id="edit_description" name="description" rows="3" class="w-full border-gray-300 rounded border p-2 text-sm"></textarea>
                </div>

                <div class="flex justify-end space-x-2 pt-2 border-t border-gray-200">
                    <button type="button" onclick="closeEditModal()" class="px-4 py-2 bg-gray-200 text-gray-700 font-bold rounded text-xs">Batal</button>
                    <button type="submit" class="px-4 py-2 bg-emerald-700 text-white font-bold rounded text-xs shadow hover:bg-emerald-800">Simpan Kemaskini</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditModal(id, title, target, description) {
            document.getElementById('editForm').action = `/admin/campaigns/${id}`;
            document.getElementById('edit_title').value = title;
            document.getElementById('edit_target_amount').value = target;
            document.getElementById('edit_description').value = description;
            document.getElementById('editModal').classList.remove('hidden');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }
    </script>

</body>
</html>