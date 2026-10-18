<nav x-data="{ open: false }" class="bg-white border-b border-gray-100 shadow-sm">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex items-center">
                <!-- LOGO WARGA CAKNA -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('public.index') }}" class="flex items-center gap-2">
                        <img src="{{ asset('images/logo-warga-cakna.png') }}" alt="WARGA CAKNA" class="h-10 w-auto">
                        <span class="font-bold text-gray-800 text-sm hidden md:inline-block">WARGA CAKNA</span>
                    </a>
                </div>

                <!-- NAVIGATION LINKS DESKTOP -->
                <div class="hidden space-x-6 sm:-my-px sm:ms-8 sm:flex">
                    <x-nav-link :href="route('public.index')" :active="request()->routeIs('public.*')">
                        {{ __('Portal Awam') }}
                    </x-nav-link>

                    @auth
                        <x-nav-link :href="route('receipts.index')" :active="request()->routeIs('receipts.index')">
                            {{ __('Senarai Resit') }}
                        </x-nav-link>

                        <x-nav-link :href="route('receipts.create')" :active="request()->routeIs('receipts.create')">
                            {{ __('+ Cipta Resit Baharu') }}
                        </x-nav-link>

                        @if(Auth::user()->role === 'admin' || Auth::user()->role === 'ajk')
                            <x-nav-link :href="route('users.index')" :active="request()->routeIs('users.*')">
                                {{ __('Admin Hub') }}
                            </x-nav-link>
                            <x-nav-link :href="route('admin.homepage.edit')" :active="request()->routeIs('admin.homepage.*')">
                                {{ __('Pengurusan Muka Depan') }}
                            </x-nav-link>
                        @endif
                    @endauth
                </div>
            </div>

            <!-- USER MENU / AUTH BUTTONS DESKTOP -->
            <div class="hidden sm:flex sm:items-center sm:ms-6">
                @auth
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
                                {{ __('Profil Akaun') }}
                            </x-dropdown-link>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();" class="text-red-600 font-bold">
                                    {{ __('Log Keluar') }}
                                </x-dropdown-link>
                            </form>
                        </x-slot>
                    </x-dropdown>
                @else
                    <div class="flex items-center space-x-3">
                        <a href="{{ route('login') }}" class="text-sm font-bold text-emerald-800 hover:text-emerald-950 px-3 py-2 transition">
                            Log Masuk
                        </a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-xs px-4 py-2 rounded-lg shadow transition">
                                Daftar Akaun
                            </a>
                        @endif
                    </div>
                @endauth
            </div>

            <!-- HAMBURGER BUTTON MOBILE -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none transition">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'inline-flex': ! open, 'hidden': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- RESPONSIVE MENU MOBILE -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden bg-white border-t border-gray-100">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('public.index')" :active="request()->routeIs('public.*')">
                {{ __('Portal Awam') }}
            </x-responsive-nav-link>

            @auth
                <x-responsive-nav-link :href="route('receipts.index')" :active="request()->routeIs('receipts.index')">
                    {{ __('Senarai Resit') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('receipts.create')" :active="request()->routeIs('receipts.create')">
                    {{ __('+ Cipta Resit Baharu') }}
                </x-responsive-nav-link>
                @if(Auth::user()->role === 'admin' || Auth::user()->role === 'ajk')
                    <x-responsive-nav-link :href="route('users.index')" :active="request()->routeIs('users.*')">
                        {{ __('Admin Hub') }}
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('admin.homepage.edit')" :active="request()->routeIs('admin.homepage.*')">
                        {{ __('Pengurusan Muka Depan') }}
                    </x-responsive-nav-link>
                @endif
            @endauth
        </div>

        <div class="pt-4 pb-3 border-t border-gray-200">
            @auth
                <div class="px-4">
                    <div class="font-bold text-base text-emerald-800">{{ Auth::user()->name }}</div>
                    <div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }}</div>
                </div>

                <div class="mt-3 space-y-1">
                    <x-responsive-nav-link :href="route('profile.edit')">
                        {{ __('Profil Akaun') }}
                    </x-responsive-nav-link>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <x-responsive-nav-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();" class="text-red-600 font-bold">
                            {{ __('Log Keluar') }}
                        </x-responsive-nav-link>
                    </form>
                </div>
            @else
                <div class="px-4 space-y-2">
                    <a href="{{ route('login') }}" class="block w-full text-center bg-gray-100 hover:bg-gray-200 text-emerald-900 font-bold py-2 rounded-lg text-sm transition">
                        Log Masuk
                    </a>
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="block w-full text-center bg-emerald-700 hover:bg-emerald-800 text-white font-bold py-2 rounded-lg text-sm transition">
                            Daftar Akaun
                        </a>
                    @endif
                </div>
            @endauth
        </div>
    </div>
</nav>