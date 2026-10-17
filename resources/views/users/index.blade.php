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
                                <th class="p-3 border text-center">TARIKH DAFTAR</th>
                                <th class="p-3 border text-center">TINDAKAN KEMASKINI ROLE</th>
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
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-bold bg-gray-100 text-gray-800 border border-gray-300">
                                                PENGGUNA
                                            </span>
                                        @endif
                                    </td>
                                    <td class="p-3 border text-center text-xs text-gray-500 whitespace-nowrap">
                                        {{ $user->created_at ? $user->created_at->format('d/m/Y h:i A') : '-' }}
                                    </td>
                                    <td class="p-3 border text-center">
                                        <form action="{{ route('users.update-role', $user->id) }}" method="POST" class="flex items-center justify-center gap-2">
                                            @csrf
                                            @method('PATCH')
                                            <select name="role" class="text-xs rounded border-gray-300 py-1 px-2 focus:ring-emerald-500 focus:border-emerald-500">
                                                <option value="user" {{ $user->role === 'user' || !$user->role ? 'selected' : '' }}>Pengguna</option>
                                                <option value="ajk" {{ $user->role === 'ajk' ? 'selected' : '' }}>AJK</option>
                                                <option value="admin" {{ $user->role === 'admin' ? 'selected' : '' }}>Admin</option>
                                            </select>
                                            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold py-1 px-3 rounded text-xs transition">
                                                Kemaskini
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="p-6 text-center text-gray-500">
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