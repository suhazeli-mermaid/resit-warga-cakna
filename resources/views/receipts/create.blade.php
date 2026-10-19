<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-semibold text-gray-800">Cipta Resit Manual</h1></x-slot>
    <div class="py-6">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="bg-emerald-950 px-5 py-4 text-white"><h2 class="font-bold">Maklumat sumbangan sebenar</h2><p class="mt-1 text-xs text-emerald-100">Semua resit manual bermula sebagai TUNGGU PENGESAHAN.</p></div>
                @if ($errors->any())
                    <div class="m-5 rounded-lg border border-red-300 bg-red-50 p-4 text-sm text-red-800"><ul class="list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                @endif
                <form action="{{ route('receipts.store') }}" method="POST" class="space-y-5 p-5 sm:p-6">
                    @csrf
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div><label class="mb-1 block text-sm font-bold" for="donor_name">Nama Penderma *</label><input id="donor_name" name="donor_name" required value="{{ old('donor_name') }}" class="min-h-11 w-full rounded-md border-gray-300 focus:border-emerald-500 focus:ring-emerald-500"></div>
                        <div><label class="mb-1 block text-sm font-bold" for="donor_ic_passport">No. KP / Pasport</label><input id="donor_ic_passport" name="donor_ic_passport" value="{{ old('donor_ic_passport') }}" class="min-h-11 w-full rounded-md border-gray-300 focus:border-emerald-500 focus:ring-emerald-500"></div>
                        <div><label class="mb-1 block text-sm font-bold" for="donor_phone">No. Telefon</label><input id="donor_phone" name="donor_phone" value="{{ old('donor_phone') }}" class="min-h-11 w-full rounded-md border-gray-300 focus:border-emerald-500 focus:ring-emerald-500"></div>
                        <div><label class="mb-1 block text-sm font-bold" for="donor_email">Emel</label><input id="donor_email" type="email" name="donor_email" value="{{ old('donor_email') }}" class="min-h-11 w-full rounded-md border-gray-300 focus:border-emerald-500 focus:ring-emerald-500"></div>
                        <div><label class="mb-1 block text-sm font-bold" for="amount">Jumlah (RM) *</label><input id="amount" type="number" min="1" step="0.01" name="amount" required value="{{ old('amount') }}" class="min-h-11 w-full rounded-md border-gray-300 focus:border-emerald-500 focus:ring-emerald-500"></div>
                        <div><label class="mb-1 block text-sm font-bold" for="payment_method">Kaedah Bayaran *</label><select id="payment_method" name="payment_method" required class="min-h-11 w-full rounded-md border-gray-300 focus:border-emerald-500 focus:ring-emerald-500"><option value="bank_transfer" @selected(old('payment_method') === 'bank_transfer')>Pindahan Bank</option><option value="online_transfer" @selected(old('payment_method') === 'online_transfer')>Online Transfer</option><option value="qr_pay" @selected(old('payment_method') === 'qr_pay')>QR Pay</option><option value="cash" @selected(old('payment_method') === 'cash')>Tunai</option></select></div>
                        <div><label class="mb-1 block text-sm font-bold" for="donated_at">Tarikh Sumbangan *</label><input id="donated_at" type="date" max="{{ now()->toDateString() }}" name="donated_at" required value="{{ old('donated_at') }}" class="min-h-11 w-full rounded-md border-gray-300 focus:border-emerald-500 focus:ring-emerald-500"><p class="mt-1 text-xs text-gray-500">Tarikh sebenar bayaran diterima, bukan tarikh rekod dibuat.</p></div>
                        <div><label class="mb-1 block text-sm font-bold" for="payment_reference">No. Rujukan Bayaran</label><input id="payment_reference" name="payment_reference" value="{{ old('payment_reference') }}" class="min-h-11 w-full rounded-md border-gray-300 focus:border-emerald-500 focus:ring-emerald-500"></div>
                    </div>
                    <div><label class="mb-1 block text-sm font-bold" for="description">Tujuan / Catatan</label><textarea id="description" name="description" rows="3" class="w-full rounded-md border-gray-300 focus:border-emerald-500 focus:ring-emerald-500">{{ old('description') }}</textarea></div>
                    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end"><a href="{{ route('receipts.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-gray-200 px-5 font-bold text-gray-800">Batal</a><button class="min-h-11 rounded-lg bg-emerald-700 px-5 font-bold text-white hover:bg-emerald-800">Simpan Transaksi</button></div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
