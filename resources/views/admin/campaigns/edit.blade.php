<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800">
            Pengurusan Maklumat Kempen
        </h2>
    </x-slot>

    <div class="py-6 bg-gray-50 min-h-screen">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <div class="flex flex-wrap justify-between items-center gap-3">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">
                        {{ $campaign->title }}
                    </h1>
                    <p class="text-sm text-gray-500">
                        Pengurusan kandungan dan aktiviti kempen
                    </p>
                </div>

                <a href="{{ route('admin.homepage.edit') }}"
                   class="bg-gray-200 px-4 py-2 rounded-lg text-sm font-semibold">
                    Kembali
                </a>
            </div>

            @if(session('success'))
                <div class="bg-green-100 border border-green-300 text-green-800 p-4 rounded-lg">
                    {{ session('success') }}
                </div>
            @endif

            @if($errors->any())
                <div class="bg-red-50 border border-red-300 text-red-700 p-4 rounded-lg">
                    <ul class="list-disc pl-5 text-sm">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- PROFIL KEMPEN -->
            <section class="bg-white rounded-xl shadow-sm p-4 sm:p-6">
                <h2 class="text-lg font-bold mb-4">
                    1. Maklumat Lengkap Kempen
                </h2>

                <form method="POST"
                      action="{{ route('admin.cms.details', $campaign->id) }}"
                      class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div>
                        <label class="block text-sm font-semibold mb-1">
                            Latar Belakang
                        </label>
                        <textarea name="background" rows="5"
                            class="w-full rounded-lg border-gray-300">{{ old('background', $detail?->background) }}</textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-1">
                            Objektif Kempen
                        </label>
                        <textarea name="objectives" rows="4"
                            class="w-full rounded-lg border-gray-300">{{ old('objectives', $detail?->objectives) }}</textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-1">
                            Penerima Manfaat
                        </label>
                        <textarea name="beneficiaries" rows="4"
                            class="w-full rounded-lg border-gray-300">{{ old('beneficiaries', $detail?->beneficiaries) }}</textarea>
                    </div>

                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold mb-1">Lokasi</label>
                            <input type="text" name="location"
                                value="{{ old('location', $detail?->location) }}"
                                class="w-full rounded-lg border-gray-300">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold mb-1">Penganjur</label>
                            <input type="text" name="organiser"
                                value="{{ old('organiser', $detail?->organiser) }}"
                                class="w-full rounded-lg border-gray-300">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold mb-1">Tarikh Mula</label>
                            <input type="date" name="start_date"
                                value="{{ old('start_date', $detail?->start_date?->format('Y-m-d')) }}"
                                class="w-full rounded-lg border-gray-300">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold mb-1">Tarikh Tamat</label>
                            <input type="date" name="end_date"
                                value="{{ old('end_date', $detail?->end_date?->format('Y-m-d')) }}"
                                class="w-full rounded-lg border-gray-300">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-1">
                            Maklumat Perhubungan
                        </label>
                        <input type="text" name="contact_info"
                            value="{{ old('contact_info', $detail?->contact_info) }}"
                            class="w-full rounded-lg border-gray-300">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-1">
                            Status Penerbitan Maklumat
                        </label>
                        <select name="is_published" class="w-full rounded-lg border-gray-300">
                            <option value="0" @selected(!old('is_published', $detail?->is_published ?? false))>
                                Draf
                            </option>
                            <option value="1" @selected(old('is_published', $detail?->is_published ?? false))>
                                Terbit
                            </option>
                        </select>
                    </div>

                    <button type="submit"
                        class="bg-emerald-700 hover:bg-emerald-800 text-white px-6 py-3 rounded-lg font-semibold">
                        Simpan Maklumat Kempen
                    </button>
                </form>
            </section>

            <!-- TAMBAH AKTIVITI -->
            <section class="bg-white rounded-xl shadow-sm p-4 sm:p-6">
                <h2 class="text-lg font-bold mb-4">
                    2. Tambah Aktiviti Timeline
                </h2>

                <form method="POST"
                    action="{{ route('admin.cms.updates.store', $campaign->id) }}"
                    class="space-y-4">
                    @csrf

                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold mb-1">
                                Tarikh Aktiviti *
                            </label>
                            <input type="date" name="activity_date"
                                value="{{ old('activity_date') }}"
                                required class="w-full rounded-lg border-gray-300">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold mb-1">
                                Lokasi Aktiviti
                            </label>
                            <input type="text" name="location"
                                value="{{ old('location') }}"
                                class="w-full rounded-lg border-gray-300">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-1">
                            Tajuk Aktiviti *
                        </label>
                        <input type="text" name="title"
                            value="{{ old('title') }}"
                            required class="w-full rounded-lg border-gray-300">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-1">
                            Laporan Aktiviti
                        </label>
                        <textarea name="description" rows="4"
                            class="w-full rounded-lg border-gray-300">{{ old('description') }}</textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-1">
                            Status
                        </label>
                        <select name="is_published" class="w-full rounded-lg border-gray-300">
                            <option value="0">Draf</option>
                            <option value="1">Terbit</option>
                        </select>
                    </div>

                    <button type="submit"
                        class="bg-emerald-700 text-white px-6 py-3 rounded-lg font-semibold">
                        Tambah Aktiviti
                    </button>
                </form>
            </section>

            <!-- SENARAI TIMELINE -->
            <section class="bg-white rounded-xl shadow-sm p-4 sm:p-6">
                <h2 class="text-lg font-bold mb-4">
                    3. Senarai Aktiviti Program
                </h2>

                <div class="space-y-5">
                    @forelse($updates as $activity)
                        <div class="border-l-4 border-emerald-600 bg-gray-50 p-5 rounded-r-xl">
                            <div class="flex flex-wrap justify-between gap-2 mb-3">
                                <div>
                                    <p class="text-xs text-emerald-700 font-bold">
                                        {{ $activity->activity_date?->format('d/m/Y') }}
                                    </p>
                                    <h3 class="font-bold text-gray-900">
                                        {{ $activity->title }}
                                    </h3>
                                </div>
                                <span class="text-xs font-semibold">
                                    {{ $activity->is_published ? 'Terbit' : 'Draf' }}
                                </span>
                            </div>

                            <p class="text-sm text-gray-600 whitespace-pre-line mb-3">
                                {{ $activity->description }}
                            </p>

                            <details class="mt-3">
                                <summary class="text-sm font-semibold text-emerald-700 cursor-pointer">
                                    Edit Aktiviti
                                </summary>

                                <form method="POST"
                                    action="{{ route('admin.cms.updates.update', $activity->id) }}"
                                    class="space-y-3 mt-4">
                                    @csrf
                                    @method('PUT')

                                    <input type="date" name="activity_date"
                                        value="{{ $activity->activity_date?->format('Y-m-d') }}"
                                        required class="w-full rounded-lg border-gray-300">

                                    <input type="text" name="title"
                                        value="{{ $activity->title }}"
                                        required class="w-full rounded-lg border-gray-300">

                                    <input type="text" name="location"
                                        value="{{ $activity->location }}"
                                        placeholder="Lokasi"
                                        class="w-full rounded-lg border-gray-300">

                                    <textarea name="description" rows="3"
                                        class="w-full rounded-lg border-gray-300">{{ $activity->description }}</textarea>

                                    <select name="is_published"
                                        class="w-full rounded-lg border-gray-300">
                                        <option value="0" @selected(!$activity->is_published)>Draf</option>
                                        <option value="1" @selected($activity->is_published)>Terbit</option>
                                    </select>

                                    <button type="submit"
                                        class="bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm">
                                        Simpan Perubahan
                                    </button>
                                </form>
                            </details>

                            @if($activity->media->count())
                                <p class="text-xs text-gray-500 mt-3">
                                    {{ $activity->media->count() }} media berkaitan
                                </p>
                            @endif
                        </div>
                    @empty
                        <p class="text-gray-500 text-sm">
                            Belum ada aktiviti direkodkan.
                        </p>
                    @endforelse
                </div>
            </section>

            <!-- GALERI DAN YOUTUBE -->
            <section class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="text-lg font-bold mb-4">
                    4. Galeri Gambar & Video YouTube
                </h2>

                <form method="POST"
                    action="{{ route('admin.cms.media.store', $campaign->id) }}"
                    enctype="multipart/form-data"
                    class="space-y-4">
                    @csrf

                    <div>
                        <label class="block text-sm font-semibold mb-1">
                            Jenis Media
                        </label>
                        <select name="media_type" id="media_type"
                            onchange="toggleCampaignMediaFields()"
                            class="w-full rounded-lg border-gray-300">
                            <option value="image">Gambar Program</option>
                            <option value="youtube">Video YouTube</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-1">
                            Aktiviti Berkaitan
                        </label>
                        <select name="campaign_update_id"
                            class="w-full rounded-lg border-gray-300">
                            <option value="">Galeri Umum Kempen</option>
                            @foreach($updates as $activity)
                                <option value="{{ $activity->id }}">
                                    {{ $activity->activity_date?->format('d/m/Y') }}
                                    — {{ $activity->title }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div id="campaign_image_fields">
                        <label class="block text-sm font-semibold mb-1">
                            Muat Naik Gambar
                        </label>
                        <input type="file" name="images[]"
                            multiple accept=".jpg,.jpeg,.png,.webp"
                            class="w-full border rounded-lg p-3 text-sm">
                        <p class="text-xs text-gray-500 mt-1">
                            Maksimum 20 gambar setiap muat naik, 3 MB setiap gambar.
                        </p>
                    </div>

                    <div id="campaign_youtube_fields" class="hidden">
                        <label class="block text-sm font-semibold mb-1">
                            URL Video YouTube
                        </label>
                        <input type="url" name="youtube_url"
                            placeholder="https://www.youtube.com/watch?v=..."
                            class="w-full rounded-lg border-gray-300">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-1">
                            Kapsyen
                        </label>
                        <input type="text" name="caption" maxlength="255"
                            class="w-full rounded-lg border-gray-300">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-1">
                            Status Media
                        </label>
                        <select name="is_published"
                            class="w-full rounded-lg border-gray-300">
                            <option value="0">Draf</option>
                            <option value="1">Terbit</option>
                        </select>
                    </div>

                    <button type="submit"
                        class="bg-emerald-700 text-white px-6 py-3 rounded-lg font-semibold">
                        Simpan Media
                    </button>
                </form>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mt-8">
                    @forelse($media as $item)
                        <div class="border rounded-xl overflow-hidden bg-gray-50">
                            @if($item->media_type === 'image' && $item->image_url)
                                <img src="{{ $item->image_url }}"
                                    alt="{{ $item->caption ?: 'Gambar program' }}"
                                    class="w-full h-40 object-cover">
                            @elseif($item->media_type === 'youtube')
                                <div class="h-40 bg-gray-900 text-white flex items-center justify-center">
                                    Video YouTube
                                </div>
                            @endif

                            <div class="p-3 space-y-2">
                                <p class="text-sm font-semibold break-words">
                                    {{ $item->caption ?: 'Tanpa kapsyen' }}
                                </p>

                                <p class="text-xs text-gray-500">
                                    {{ $item->is_published ? 'Terbit' : 'Draf' }}
                                </p>

                                @if($item->media_type === 'youtube')
                                    <a href="{{ $item->youtube_url }}"
                                        target="_blank" rel="noopener noreferrer"
                                        class="text-sm text-emerald-700 underline break-all">
                                        Buka video
                                    </a>
                                @endif

                                <form method="POST"
                                    action="{{ route('admin.cms.media.delete', $item->id) }}"
                                    onsubmit="return confirm('Keluarkan media ini daripada galeri?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                        class="text-sm text-red-700 font-semibold">
                                        Buang Media
                                    </button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">
                            Belum ada gambar atau video.
                        </p>
                    @endforelse
                </div>
            </section>

        </div>
    </div>

    <script>
        function toggleCampaignMediaFields() {
            const type = document.getElementById('media_type').value;
            const imageFields = document.getElementById('campaign_image_fields');
            const youtubeFields = document.getElementById('campaign_youtube_fields');

            imageFields.classList.toggle('hidden', type !== 'image');
            youtubeFields.classList.toggle('hidden', type !== 'youtube');

            imageFields.querySelector('input').disabled = type !== 'image';
            youtubeFields.querySelector('input').disabled = type !== 'youtube';
        }

        document.addEventListener('DOMContentLoaded', toggleCampaignMediaFields);
    </script>
</x-app-layout>
