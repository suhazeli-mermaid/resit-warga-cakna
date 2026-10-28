<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Admin Hub / Pengurusan Pengguna & AJK') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">

                <div class="mb-6 flex justify-between items-center border-b pb-4">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">Senarai Akaun Pengguna & AJK</h3>
                        <p class="text-xs text-gray-500">Uruskan peranan (Role) akaun yang berdaftar dalam sistem WARGA CAKNA.</p>
                    </div>
                    <div class="text-xs font-bold bg-emerald-100 text-emerald-800 px-3 py-1.5 rounded-full">
                        Jumlah Pengguna: {{ $users->total() }}
                    </div>
                </div>

                <!-- MESEJ NOTIFIKASI -->
                @if(session('success'))
                    <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative text-sm">
                        {{ session('success') }}
                    </div>
                @endif
                @if(session('error'))
                    <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative text-sm">
                        {{ session('error') }}
                    </div>
                @endif

                <!-- JADUAL SENARAI PENGGUNA -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-emerald-900 text-white text-xs uppercase font-bold">
                                <th class="p-3 border">BIL</th>
                                <th class="p-3 border">NAMA PENGGUNA</th>
                                <th class="p-3 border">ALAMAT EMEL</th>
                                <th class="p-3 border text-center">PERANAN (ROLE) SEMASA</th>
                                <th class="p-3 border">JAWATAN WARGA CAKNA</th>
                                <th class="p-3 border text-center">TARIKH DAFTAR</th>
                                <th class="p-3 border text-center">TINDAKAN KEMASKINI</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y text-sm">
                            @forelse($users as $index => $user)
                                <tr class="hover:bg-gray-50">
                                    <td class="p-3 border text-center font-bold text-gray-600">
                                        {{ $users->firstItem() + $index }}
                                    </td>
                                    <td class="p-3 border font-bold text-gray-900">
                                        {{ $user->name }}
                                        @if(Auth::id() === $user->id)
                                            <span class="ml-1 text-xs text-emerald-600 font-semibold">(Akaun Anda)</span>
                                        @endif
                                    </td>
                                    <td class="p-3 border text-gray-600">
                                        {{ $user->email }}
                                    </td>
                                    <td class="p-3 border text-center">
                                        @if($user->role === 'admin')
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-bold bg-purple-100 text-purple-800 border border-purple-300">
                                                ADMIN
                                            </span>
                                        @elseif($user->role === 'ajk')
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-bold bg-blue-100 text-blue-800 border border-blue-300">
                                                AJK
                                            </span>
                                        @elseif($user->role === 'setiausaha')
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-bold bg-cyan-100 text-cyan-800 border border-cyan-300">SETIAUSAHA</span>
                                        @elseif($user->role === 'bendahari')
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-bold bg-amber-100 text-amber-800 border border-amber-300">BENDAHARI</span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-bold bg-gray-100 text-gray-800 border border-gray-300">
                                                PENGGUNA
                                            </span>
                                        @endif
                                    </td>
                                    <td class="p-3 border text-sm text-gray-700">
                                        {{ $user->organization_position ?: 'Belum ditetapkan' }}
                                    </td>
                                    <td class="p-3 border text-center text-xs text-gray-500 whitespace-nowrap">
                                        {{ $user->created_at ? $user->created_at->format('d/m/Y h:i A') : '-' }}
                                    </td>
                                    <td class="p-3 border text-center">
                                        <form action="{{ route('users.update-role', $user->id) }}" method="POST" class="flex min-w-56 flex-col items-stretch justify-center gap-2">
                                            @csrf
                                            @method('PATCH')
                                            <select name="role" class="text-xs rounded border-gray-300 py-1 px-2 focus:ring-emerald-500 focus:border-emerald-500">
                                                <option value="penderma" {{ in_array($user->role, ['penderma','user',null], true) ? 'selected' : '' }}>Penderma</option>
                                                <option value="ajk" {{ $user->role === 'ajk' ? 'selected' : '' }}>AJK</option>
                                                <option value="setiausaha" {{ $user->role === 'setiausaha' ? 'selected' : '' }}>Setiausaha</option>
                                                <option value="bendahari" {{ $user->role === 'bendahari' ? 'selected' : '' }}>Bendahari</option>
                                                <option value="admin" {{ $user->role === 'admin' ? 'selected' : '' }}>Admin</option>
                                            </select>
                                            <input type="text" name="organization_position" value="{{ $user->organization_position }}" maxlength="120" placeholder="Contoh: Pengerusi / Bendahari" class="rounded border-gray-300 px-2 py-1 text-xs focus:border-emerald-500 focus:ring-emerald-500">
                                            <fieldset class="rounded-md border border-gray-200 p-2 text-left">
                                                <legend class="px-1 text-xs font-bold text-gray-700">Kebenaran Kewangan</legend>
                                                <input type="hidden" name="can_create_receipt" value="0">
                                                <label class="flex min-h-9 items-start gap-2 py-1 text-xs text-gray-700">
                                                    <input type="checkbox" name="can_create_receipt" value="1" @checked($user->canCreateReceipt()) @disabled($user->isAdmin()) class="mt-0.5 rounded border-gray-300 text-emerald-700 focus:ring-emerald-500">
                                                    <span><strong class="block">Boleh Cipta Resit</strong><span class="text-[11px] text-gray-500">Boleh mencipta resit manual dan memilih Favourite Pembayar/Penderma sedia ada.</span></span>
                                                </label>
                                                <input type="hidden" name="can_create_voucher" value="0">
                                                <label class="flex min-h-9 items-start gap-2 py-1 text-xs text-gray-700">
                                                    <input type="checkbox" name="can_create_voucher" value="1" @checked($user->canCreateVoucher()) @disabled($user->isAdmin()) class="mt-0.5 rounded border-gray-300 text-emerald-700 focus:ring-emerald-500">
                                                    <span><strong class="block">Boleh Cipta Baucar</strong><span class="text-[11px] text-gray-500">Boleh mencipta, mengemas kini dan menghantar Baucar sendiri untuk kelulusan.</span></span>
                                                </label>
                                                <input type="hidden" name="can_approve_voucher" value="0">
                                                <label class="flex min-h-9 items-start gap-2 py-1 text-xs text-gray-700">
                                                    <input type="checkbox" name="can_approve_voucher" value="1" @checked($user->canApproveVoucher()) @disabled($user->isAdmin()) class="mt-0.5 rounded border-gray-300 text-emerald-700 focus:ring-emerald-500">
                                                    <span><strong class="block">Boleh Lulus Baucar</strong><span class="text-[11px] text-gray-500">Boleh melulus atau menolak Baucar pengguna lain; Baucar sendiri tetap dilarang.</span></span>
                                                </label>
                                                <input type="hidden" name="can_confirm_payment" value="0">
                                                <label class="flex min-h-9 items-start gap-2 py-1 text-xs text-gray-700">
                                                    <input type="checkbox" name="can_confirm_payment" value="1" @checked($user->canConfirmPayment()) @disabled($user->isAdmin()) class="mt-0.5 rounded border-gray-300 text-emerald-700 focus:ring-emerald-500">
                                                    <span><strong class="block">Boleh Sahkan Bayaran</strong><span class="text-[11px] text-gray-500">Boleh merekod pengesahan bayaran Baucar dan memulakan aliran Akuan Penerimaan.</span></span>
                                                </label>
                                                <input type="hidden" name="can_manage_favourites" value="0">
                                                <label class="flex min-h-9 items-start gap-2 py-1 text-xs text-gray-700">
                                                    <input type="checkbox" name="can_manage_favourites" value="1" @checked($user->canManageFavourites()) @disabled($user->isAdmin()) class="mt-0.5 rounded border-gray-300 text-emerald-700 focus:ring-emerald-500">
                                                    <span><strong class="block">Boleh Urus Favourite</strong><span class="text-[11px] text-gray-500">Boleh menambah, mengemas kini dan menyahaktifkan Favourite Pembayar/Penerima organisasi.</span></span>
                                                </label>
                                                @if($user->isAdmin())
                                                    <p class="mt-1 text-[11px] text-gray-500">Admin sentiasa dibenarkan.</p>
                                                @endif
                                            </fieldset>
                                            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold py-1 px-3 rounded text-xs transition">
                                                Kemas Kini
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="p-6 text-center text-gray-500">
                                        Tiada pengguna berdaftar.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- PAGINATION -->
                <div class="mt-4">
                    {{ $users->links() }}
                </div>

            </div>
        </div>
    </div>
</x-app-layout>
