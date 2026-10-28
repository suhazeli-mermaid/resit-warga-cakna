<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-semibold text-gray-800">Favourite Contacts / Kemaskini</h1></x-slot>
    <div class="py-6"><div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
        <div class="mb-4 rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">Perubahan di sini tidak mengubah Resit atau Baucar lama kerana setiap transaksi menyimpan nilainya sendiri.</div>
        <form method="POST" action="{{ route('admin.favourite-contacts.update', $contact) }}" class="rounded-xl bg-white p-5 shadow-sm sm:p-8">
            @include('admin.favourite-contacts.partials.form')
        </form>
    </div></div>
</x-app-layout>
