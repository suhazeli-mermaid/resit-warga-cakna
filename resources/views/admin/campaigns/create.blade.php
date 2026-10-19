<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800">
            Tambah Kempen Dana Baharu
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="bg-white rounded-xl shadow-sm p-4 sm:p-6">

                <div class="mb-6">
                    <h3 class="text-lg font-bold text-gray-900">
                        Maklumat Kempen Baharu
                    </h3>
                    <p class="text-sm text-gray-500">
                        Kempen akan disimpan sebagai tidak aktif
                        sehingga diterbitkan oleh Admin.
                    </p>
                </div>

                @if($errors->any())
                    <div class="mb-5 bg-red-50 border border-red-200 rounded-lg p-4">
                        <ul class="list-disc pl-5 text-sm text-red-700">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form
                    action="{{ route('admin.cms.store') }}"
                    method="POST"
                    enctype="multipart/form-data"
                    class="space-y-5"
                >
                    @csrf

                    <div>
                        <label class="block text-sm font-semibold mb-2">
                            Tajuk Kempen *
                        </label>

                        <input
                            type="text"
                            name="title"
                            value="{{ old('title') }}"
                            required
                            maxlength="255"
                            class="w-full rounded-lg border-gray-300"
                            placeholder="Contoh: Dana Bantuan Banjir 2026"
                        >
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-2">
                            Penerangan Ringkas *
                        </label>

                        <textarea
                            name="description"
                            rows="4"
                            required
                            class="w-full rounded-lg border-gray-300"
                            placeholder="Terangkan tujuan utama kempen"
                        >{{ old('description') }}</textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-2">
                            Sasaran Kutipan (RM) *
                        </label>

                        <input
                            type="number"
                            name="target_amount"
                            min="1"
                            step="0.01"
                            value="{{ old('target_amount') }}"
                            required
                            class="w-full rounded-lg border-gray-300"
                        >
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-2">
                            Poster Kempen
                        </label>

                        <input
                            type="file"
                            name="image"
                            accept=".jpg,.jpeg,.png,.webp"
                            class="w-full text-sm border rounded-lg p-3"
                        >

                        <p class="text-xs text-gray-500 mt-2">
                            JPG, PNG atau WebP. Maksimum 3 MB.
                        </p>
                    </div>

                    <div class="flex flex-wrap justify-between gap-3 border-t pt-5">
                        <a
                            href="{{ route('admin.homepage.edit') }}"
                            class="px-5 py-3 rounded-lg bg-gray-100 text-gray-700 font-semibold text-sm"
                        >
                            Kembali
                        </a>

                        <button
                            type="submit"
                            class="px-6 py-3 rounded-lg bg-emerald-700 hover:bg-emerald-800 text-white font-semibold text-sm"
                        >
                            Simpan Kempen Baharu
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</x-app-layout>
