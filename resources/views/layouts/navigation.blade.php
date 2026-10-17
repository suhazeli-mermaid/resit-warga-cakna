<nav x-data="{ open: false }" class="bg-white border-b border-gray-100 shadow-sm">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex items-center">
                <!-- LOGO WARGA CAKNA -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('receipts.index') }}" class="flex items-center gap-2">
                        <img src="{{ asset('images/logo-warga-cakna.png') }}" alt="WARGA CAKNA" class="h-10 w-auto">
                        <span class="font-bold text-gray-800 text-sm hidden md:inline-block">WARGA CAKNA</span>
                    </a>
                </div>

                <!-- NAVIGATION LINKS -->
                <div class="hidden space-x-6 sm:-my-px sm:ms-8 sm:flex">
                    <!-- SENARAI RESIT (LANDING PAGE MAIN MENU) -->
                    <x-nav-link :href="route('receipts.index')" :active="request()->routeIs('receipts.index')">
                        {{ __('Senarai Resit') }}
                    </x-nav-link>

                    <x-nav-link :href="route('receipts.create')" :active="request()->routeIs('receipts.create')">
                        {{ __('+ Cipta Resit Baharu') }}
                    </x-nav-link>

                    <!-- ADMIN HUB (FOR ADMIN/AJK ONLY) -->
                    @if(Auth::user()->role === 'admin' || Auth::user()->role === 'ajk')
                        <x-nav-link :href="route('users.index')" :active="request()->routeIs('users.*')">
                            {{ __('Admin Hub') }}
                        </x-nav-link>
                        <x-nav-link :href="route('admin.homepage.edit')" :active="request()->routeIs('admin.homepage.*')">
                            {{ __('Pengurusan Muka Depan') }}
                        </x-nav-link>
                    @endif
                </div>
            </div>

            <!-- SETTINGS DROPDOWN -->
            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm font-medium rounded-md text-gray-700 bg-white hover:text-gray-900 focus:outline-none transition">
                            <div class="font-bold text-emerald-800">{{ Auth::user()->name }}</div>
                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4 text-emerald-800" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">
                            {{ __('Profil') }}
                        </x-dropdown-link>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">
                                {{ __('Log Keluar') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>
        </div>
    </div>
</nav>