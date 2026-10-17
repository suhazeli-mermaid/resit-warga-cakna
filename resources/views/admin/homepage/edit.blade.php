<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Admin Hub — Pengurusan Muka Depan (Homepage Editor)') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">

                @if(session('success'))
                    <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                        {{ session('success') }}
                    </div>
                @endif

                <form action="{{ route('admin.homepage.update') }}" method="POST" enctype="multipart/form-data" class="space-y-8">
                    @csrf
                    @method('PUT')

                    <!-- HERO SECTION -->
                    <div class="border p-4 rounded-md bg-gray-50">
                        <div class="flex justify-between items-center mb-4 border-b pb-2">
                            <h3 class="text-lg font-bold text-emerald-900">1. Hero Section (Banner Utama)</h3>
                            <label class="inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="hero_published" value="1" {{ $hero->is_published ? 'checked' : '' }} class="sr-only peer">
                                <div class="relative w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
                                <span class="ms-3 text-sm font-medium text-gray-900">Terbitkan Section</span>
                            </label>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold uppercase mb-1">Tajuk Utama Hero *</label>
                                <input type="text" name="hero_title" value="{{ old('hero_title', $hero->title) }}" required class="w-full text-sm rounded-md border-gray-300 shadow-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-bold uppercase mb-1">Subtajuk Hero</label>
                                <input type="text" name="hero_subtitle" value="{{ old('hero_subtitle', $hero->subtitle) }}" class="w-full text-sm rounded-md border-gray-300 shadow-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-bold uppercase mb-1">Teks Butang CTA</label>
                                <input type="text" name="hero_cta_text" value="{{ old('hero_cta_text', $hero->cta_text) }}" class="w-full text-sm rounded-md border-gray-300 shadow-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-bold uppercase mb-1">Pautan Butang CTA</label>
                                <input type="text" name="hero_cta_link" value="{{ old('hero_cta_link', $hero->cta_link) }}" class="w-full text-sm rounded-md border-gray-300 shadow-sm">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs font-bold uppercase mb-1">Muat Naik Gambar Banner</label>
                                <input type="file" name="hero_banner" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100">
                                @if($hero->banner_image)
                                    <div class="mt-2">
                                        <img src="{{ asset('storage/' . $hero->banner_image) }}" class="h-24 rounded border">
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- ABOUT SECTION -->
                    <div class="border p-4 rounded-md bg-gray-50">
                        <div class="flex justify-between items-center mb-4 border-b pb-2">
                            <h3 class="text-lg font-bold text-emerald-900">2. Section Pengenalan (About)</h3>
                            <label class="inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="about_published" value="1" {{ $about->is_published ? 'checked' : '' }} class="sr-only peer">
                                <div class="relative w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
                                <span class="ms-3 text-sm font-medium text-gray-900">Terbitkan Section</span>
                            </label>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs font-bold uppercase mb-1">Tajuk Pengenalan *</label>
                                <input type="text" name="about_title" value="{{ old('about_title', $about->title) }}" required class="w-full text-sm rounded-md border-gray-300 shadow-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-bold uppercase mb-1">Penerangan / Visi & Misi *</label>
                                <textarea name="about_body" rows="4" required class="w-full text-sm rounded-md border-gray-300 shadow-sm">{{ old('about_body', $about->body) }}</textarea>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="bg-emerald-800 hover:bg-emerald-900 text-white font-bold py-2.5 px-6 rounded-md shadow text-sm transition">
                            Simpan & Terbitkan Kemaskini
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</x-app-layout>