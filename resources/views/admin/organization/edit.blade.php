<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-semibold text-gray-800">Admin Hub / Tetapan Organisasi</h1></x-slot>

    <div class="py-6"><div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
        <section class="rounded-xl bg-white p-6 shadow-sm">
            <h2 class="text-lg font-bold">Cop Rasmi Warga Cakna</h2>
            <p class="mt-2 text-sm text-gray-600">PNG atau JPG/JPEG, maksimum 2 MB, dimensi 100 × 100 hingga 3000 × 3000 piksel. PNG lutsinar disokong. Disimpan pada storan peribadi. Baucar yang telah dikeluarkan menggunakan snapshot dan tidak berubah apabila cop ini diganti.</p>
            @if(session('success'))<div class="my-4 rounded border border-green-300 bg-green-50 p-3 text-sm text-green-800">{{ session('success') }}</div>@endif
            @if($errors->has('official_stamp'))<div class="my-4 rounded border border-red-300 bg-red-50 p-3 text-sm text-red-800">{{ $errors->first('official_stamp') }}</div>@endif

            @if($hasStamp)
                <div class="mt-5 rounded-lg border border-gray-200 bg-gray-50 p-4"><div class="mb-2 text-xs font-bold uppercase text-gray-500">Pratonton Cop Semasa</div><img src="{{ route('admin.organization.stamp.show') }}" alt="Cop rasmi Warga Cakna semasa" class="max-h-48 max-w-full rounded bg-white object-contain p-2"></div>
            @endif

            <form method="POST" enctype="multipart/form-data" action="{{ route('admin.organization.stamp') }}" class="mt-5 space-y-4">
                @csrf @method('PUT')
                <input type="file" name="official_stamp" accept="image/png,image/jpeg" required class="block w-full rounded-md border border-gray-300 p-3">
                <x-input-error :messages="$errors->get('official_stamp')" />
                <button class="min-h-11 rounded-md bg-emerald-700 px-5 font-bold text-white">{{ $hasStamp ? 'Ganti Cop Rasmi' : 'Muat Naik Cop Rasmi' }}</button>
            </form>
            <p class="mt-4 text-sm font-semibold {{ $hasStamp ? 'text-green-700' : 'text-amber-700' }}">{{ $hasStamp ? 'Cop rasmi telah dikonfigurasi.' : 'Cop rasmi belum dikonfigurasi; pengeluaran baucar akan disekat.' }}</p>
        </section>
    </div></div>
</x-app-layout>
