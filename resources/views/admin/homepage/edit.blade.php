<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Pengurusan Muka Depan & Tetapan Resit') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <!-- MESEJ NOTIFIKASI -->
            @if(session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded text-sm font-bold">
                    {{ session('success') }}
                </div>
            @endif

            <!-- SEKSI 1: MUAT NAIK TANDATANGAN DIGITAL BENDAHARI -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <div class="border-b pb-3 mb-4">
                    <h3 class="text-lg font-bold text-gray-900">1. Muat Naik Tandatangan Digital Bendahari</h3>
                    <p class="text-xs text-gray-500">Imej tandatangan ini akan dipaparkan secara automatik di bahagian bawah resit PDF di atas nama HJ KASMADI IBRAHIM.</p>
                </div>

                <form action="{{ route('admin.upload-signature') }}" method="POST" enctype="multipart/form-data" class="flex flex-wrap items-center justify-between gap-4 bg-gray-50 p-4 rounded-lg border">
                    @csrf
                    <div class="flex items-center space-x-4">
                        @php
                            $signaturePath = \App\Models\Setting::where('key', 'treasurer_signature')->value('value');
                        @endphp
                        @if($signaturePath && file_exists(public_path('storage/' . $signaturePath)))
                            <div class="bg-white p-2 border rounded shadow-sm">
                                <img src="{{ asset('storage/' . $signaturePath) }}" class="h-12 w-auto object-contain" alt="Tandatangan Bendahari">
                            </div>
                            <span class="text-xs text-emerald-700 font-bold">✓ Tandatangan Berjaya Dimuat Naik</span>
                        @else
                            <div class="bg-gray-200 h-12 w-32 rounded flex items-center justify-center text-xs text-gray-500 font-bold border border-dashed border-gray-400">
                                Tiada Imej
                            </div>
                            <span class="text-xs text-gray-500">Sila muat naik fail imej tandatangan (PNG/JPG lutsinar)</span>
                        @endif
                    </div>

                    <div class="flex items-center space-x-2">
                        <input type="file" name="signature" accept="image/*" required class="text-xs text-gray-500 border border-gray-300 rounded p-1.5 bg-white">
                        <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-4 py-2 rounded text-xs transition">
                            Muat Naik Tandatangan
                        </button>
                    </div>
                </form>
            </div>

            <!-- SEKSI 2: PENGURUSAN TABUNG & DANA KEMPEN MUKA DEPAN -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <div class="border-b pb-3 mb-4 flex justify-between items-center">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">2. Senarai Kempen & Dana Terbuka (Kemaskini Banner, Caption & Sasaran)</h3>
                        <p class="text-xs text-gray-500">Kemaskini poster/gambar, penerangan (*caption*), dan sasaran kutipan untuk setiap kempen yang dipaparkan pada muka depan.</p>
                    </div>
                </div>

                <div class="space-y-6">
                    @forelse($campaigns as $campaign)
                        <div class="border rounded-xl p-5 bg-gray-50 hover:bg-white transition">
                            <form action="{{ route('admin.campaigns.update', $campaign->id) }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                                @csrf
                                @method('PUT')

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <!-- PAPARAN GAMBAR BANNER / POSTER -->
                                    <div>
                                        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Gambar / Poster Kempen</label>
                                        <div class="mb-2">
                                            @if($campaign->image_path || $campaign->image_url)
                                                <img src="{{ $campaign->image_url ?? asset('storage/' . $campaign->image_path) }}" class="h-32 w-full object-cover rounded-lg border">
                                            @else
                                                <div class="h-32 w-full bg-gray-200 rounded-lg flex items-center justify-center text-xs text-gray-500 font-bold border border-dashed">
                                                    Tiada Gambar
                                                </div>
                                            @endif
                                        </div>
                                        <input type="file" name="image" accept="image/*" class="w-full text-xs text-gray-500 border border-gray-300 rounded p-1.5 bg-white">
                                    </div>

                                    <!-- TAJUK, CAPTION & SASARAN -->
                                    <div class="md:col-span-2 space-y-3">
                                        <div>
                                            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Tajuk Kempen *</label>
                                            <input type="text" name="title" value="{{ old('title', $campaign->title) }}" required class="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                                        </div>

                                        <div>
                                            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Caption / Penerangan Ringkas *</label>
                                            <textarea name="description" rows="2" required class="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">{{ old('description', $campaign->description) }}</textarea>
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Sasaran Kutipan (RM) *</label>
                                                <input type="number" step="0.01" name="target_amount" value="{{ old('target_amount', $campaign->target_amount) }}" required class="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                                            </div>

                                            <div>
                                                <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Status Paparan</label>
                                                <select name="is_active" class="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                                                    <option value="1" {{ $campaign->is_active ? 'selected' : '' }}>AKTIF (Dipaparkan)</option>
                                                    <option value="0" {{ !$campaign->is_active ? 'selected' : '' }}>TIDAK AKTIF (Disembunyikan)</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="flex justify-end pt-2">
                                            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold py-2 px-5 rounded text-xs shadow transition">
                                                Simpan Kemaskini Kempen
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    @empty
                        <div class="p-6 text-center text-gray-500 text-sm">
                            Tiada kempen dijumpai.
                        </div>
                    @endforelse
                </div>
            </div>

        </div>
    </div>
</x-app-layout>