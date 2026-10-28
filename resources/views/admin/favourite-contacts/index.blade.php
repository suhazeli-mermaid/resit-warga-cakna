<x-app-layout>
    <x-slot name="header"><div class="flex flex-wrap items-center justify-between gap-3"><div><h1 class="text-xl font-semibold text-gray-800">Favourite Contacts</h1><p class="text-sm text-gray-500">Unified Favourite Contact Registry untuk Pembayar/Penderma dan Penerima Baucar</p></div><a href="{{ route('admin.favourite-contacts.create') }}" class="inline-flex min-h-11 items-center rounded-md bg-emerald-700 px-4 font-bold text-white">+ Favourite Baharu</a></div></x-slot>
    <div class="py-6"><div class="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
        @if(session('success'))<div class="rounded-lg bg-emerald-100 p-4 text-sm font-semibold text-emerald-900">{{ session('success') }}</div>@endif
        <form method="GET" class="grid gap-3 rounded-xl bg-white p-4 shadow-sm sm:grid-cols-4">
            <input name="search" value="{{ request('search') }}" placeholder="Nama, telefon atau e-mel" class="min-h-11 rounded-md border-gray-300 sm:col-span-2">
            <select name="usage" class="min-h-11 rounded-md border-gray-300"><option value="">Semua kategori</option><option value="donor" @selected(request('usage')==='donor')>Pembayar / Penderma</option><option value="voucher" @selected(request('usage')==='voucher')>Penerima Baucar</option><option value="both" @selected(request('usage')==='both')>Kedua-duanya</option></select>
            <select name="status" class="min-h-11 rounded-md border-gray-300"><option value="">Semua status</option><option value="active" @selected(request('status')==='active')>Aktif</option><option value="inactive" @selected(request('status')==='inactive')>Tidak Aktif</option></select>
            <div class="flex gap-2 sm:col-span-4"><button class="min-h-11 rounded-md bg-gray-800 px-4 font-bold text-white">Cari / Tapis</button><a href="{{ route('admin.favourite-contacts.index') }}" class="inline-flex min-h-11 items-center rounded-md border border-gray-300 px-4 font-bold">Reset</a></div>
        </form>

        <div class="grid gap-3 md:hidden">
            @forelse($contacts as $contact)
                <article class="rounded-xl bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-3"><div><h2 class="font-bold text-gray-900">{{ $contact->name }}</h2><p class="text-sm text-gray-600">{{ $contact->usageLabel() }}</p></div><span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $contact->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-200 text-gray-700' }}">{{ $contact->is_active ? 'AKTIF' : 'TIDAK AKTIF' }}</span></div>
                    <dl class="mt-3 grid gap-2 text-sm"><div><dt class="text-xs uppercase text-gray-500">Telefon / E-mel</dt><dd>{{ $contact->phone ?: '—' }}<br>{{ $contact->email ?: '—' }}</dd></div><div><dt class="text-xs uppercase text-gray-500">IC / Akaun</dt><dd>{{ $contact->maskedIdentity() }} / {{ $contact->maskedBankAccount() }}</dd></div></dl>
                    <div class="mt-4 flex flex-wrap gap-2"><a href="{{ route('admin.favourite-contacts.edit', $contact) }}" class="inline-flex min-h-11 items-center rounded-md bg-emerald-700 px-4 font-bold text-white">Kemaskini</a><form method="POST" action="{{ route('admin.favourite-contacts.toggle', $contact) }}">@csrf @method('PATCH')<input type="hidden" name="is_active" value="{{ $contact->is_active ? 0 : 1 }}"><button class="min-h-11 rounded-md border border-gray-300 px-4 font-bold">{{ $contact->is_active ? 'Nyahaktif' : 'Aktifkan' }}</button></form></div>
                </article>
            @empty<div class="rounded-xl bg-white p-8 text-center text-gray-500">Tiada Favourite ditemui.</div>@endforelse
        </div>

        <div class="hidden overflow-x-auto rounded-xl bg-white shadow-sm md:block"><table class="min-w-full divide-y divide-gray-200 text-sm"><thead class="bg-emerald-950 text-left text-xs uppercase text-white"><tr><th class="p-3">Nama</th><th class="p-3">Telefon / E-mel</th><th class="p-3">Kategori</th><th class="p-3">IC / Akaun (mask)</th><th class="p-3">Status</th><th class="p-3">Tindakan</th></tr></thead><tbody class="divide-y divide-gray-100">
            @forelse($contacts as $contact)<tr><td class="p-3 font-bold">{{ $contact->name }}</td><td class="p-3">{{ $contact->phone ?: '—' }}<br><span class="text-gray-600">{{ $contact->email ?: '—' }}</span></td><td class="p-3">{{ $contact->usageLabel() }}</td><td class="p-3">{{ $contact->maskedIdentity() }}<br>{{ $contact->maskedBankAccount() }}</td><td class="p-3"><span class="rounded-full px-2 py-1 text-xs font-bold {{ $contact->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-200 text-gray-700' }}">{{ $contact->is_active ? 'AKTIF' : 'TIDAK AKTIF' }}</span></td><td class="p-3"><div class="flex gap-3"><a href="{{ route('admin.favourite-contacts.edit', $contact) }}" class="font-bold text-emerald-700">Kemaskini</a><form method="POST" action="{{ route('admin.favourite-contacts.toggle', $contact) }}">@csrf @method('PATCH')<input type="hidden" name="is_active" value="{{ $contact->is_active ? 0 : 1 }}"><button class="font-bold text-gray-700">{{ $contact->is_active ? 'Nyahaktif' : 'Aktifkan' }}</button></form></div></td></tr>
            @empty<tr><td colspan="6" class="p-8 text-center text-gray-500">Tiada Favourite ditemui.</td></tr>@endforelse
        </tbody></table></div>
        {{ $contacts->links() }}
    </div></div>
</x-app-layout>
