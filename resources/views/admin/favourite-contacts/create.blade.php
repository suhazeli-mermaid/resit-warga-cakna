<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-semibold text-gray-800">Favourite Contacts / Baharu</h1></x-slot>
    <div class="py-6"><div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
        <form method="POST" action="{{ route('admin.favourite-contacts.store') }}" class="rounded-xl bg-white p-5 shadow-sm sm:p-8">
            @include('admin.favourite-contacts.partials.form')
        </form>
    </div></div>
</x-app-layout>
