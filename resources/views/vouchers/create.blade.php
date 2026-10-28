<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-semibold text-gray-800">Kewangan / Baucar Bayaran / Draf Baharu</h1></x-slot>
    <div class="py-6"><div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
        <form method="POST" action="{{ route('vouchers.store') }}" class="rounded-xl bg-white p-5 shadow-sm sm:p-8">
            <p class="mb-6 text-sm text-gray-600">Nombor baucar dijana secara atomik apabila draf disimpan.</p>
            @include('vouchers.partials.form')
        </form>
    </div></div>
</x-app-layout>
