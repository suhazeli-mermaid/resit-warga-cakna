<nav
    x-data="{ navigationOpen: false, accountOpen: false }"
    @keydown.escape.window="if (accountOpen) { accountOpen = false; $nextTick(() => $refs.mobileAccountTrigger.focus()); } else if (navigationOpen) { navigationOpen = false; $nextTick(() => $refs.mobileNavigationTrigger.focus()); }"
    class="relative z-50 bg-white border-b border-gray-100 shadow-sm"
>
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
                    <x-dropdown id="account-menu" align="right" width="56">
                        <x-slot name="trigger">
                            <button
                                type="button"
                                x-ref="trigger"
                                @click="open = ! open"
                                @keydown.arrow-down.prevent="open = true; $nextTick(() => { const item = $refs.menu.querySelector('[role=menuitem]'); if (item) item.focus(); })"
                                :aria-expanded="open.toString()"
                                aria-controls="account-menu"
                                aria-haspopup="menu"
                                class="inline-flex min-h-11 items-center px-3 py-2 border border-transparent text-sm font-medium rounded-md text-gray-700 bg-white hover:text-gray-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2 transition"
                            >
                                <span class="font-bold text-emerald-800">{{ __('Warga Cakna') }}</span>
                                <span class="ms-1" aria-hidden="true">
                                    <svg class="fill-current h-4 w-4 text-emerald-800" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </span>
                            </button>
                        </x-slot>

                        <x-slot name="content">
                            <x-dropdown-link :href="route('profile.edit')" role="menuitem">
                                {{ __('Profil Pengguna') }}
                            </x-dropdown-link>

                            <form method="POST" action="{{ route('account.password.email') }}" role="none">
                                @csrf
                                <button type="submit" role="menuitem" class="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-emerald-600 transition duration-150 ease-in-out">
                                    {{ __('Tukar Kata Laluan') }}
                                </button>
                            </form>

                            <form method="POST" action="{{ route('logout') }}" role="none">
                                @csrf
                                <button type="submit" role="menuitem" class="block w-full px-4 py-2 text-start text-sm font-bold leading-5 text-red-600 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-emerald-600 transition duration-150 ease-in-out">
                                    {{ __('Logout') }}
                                </button>
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
                <button
                    type="button"
                    x-ref="mobileNavigationTrigger"
                    @click="navigationOpen = ! navigationOpen"
                    :aria-expanded="navigationOpen.toString()"
                    aria-controls="mobile-navigation"
                    aria-label="{{ __('Buka menu navigasi') }}"
                    class="inline-flex min-h-11 min-w-11 items-center justify-center p-2 rounded-md text-gray-500 hover:text-gray-700 hover:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2 transition"
                >
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': navigationOpen, 'inline-flex': ! navigationOpen }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'inline-flex': navigationOpen, 'hidden': ! navigationOpen }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- RESPONSIVE MENU MOBILE -->
    <div id="mobile-navigation" x-cloak x-show="navigationOpen" class="sm:hidden bg-white border-t border-gray-100">
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

                <div class="mt-3" @click.outside="accountOpen = false">
                    <button
                        type="button"
                        x-ref="mobileAccountTrigger"
                        @click="accountOpen = ! accountOpen"
                        :aria-expanded="accountOpen.toString()"
                        aria-controls="mobile-account-menu"
                        class="flex min-h-12 w-full items-center justify-between px-4 py-3 text-start text-base font-bold text-emerald-800 hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-emerald-600"
                    >
                        <span>{{ __('Warga Cakna') }}</span>
                        <svg class="h-5 w-5 transition-transform" :class="{ 'rotate-180': accountOpen }" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>

                    <div id="mobile-account-menu" x-cloak x-show="accountOpen" class="space-y-1 border-t border-gray-100 bg-gray-50 py-1">
                        <x-responsive-nav-link :href="route('profile.edit')" class="min-h-12 py-3">
                            {{ __('Profil Pengguna') }}
                        </x-responsive-nav-link>

                        <form method="POST" action="{{ route('account.password.email') }}">
                            @csrf
                            <button type="submit" class="block min-h-12 w-full border-l-4 border-transparent px-4 py-3 text-start text-base font-medium text-gray-600 hover:border-gray-300 hover:bg-gray-100 hover:text-gray-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-emerald-600">
                                {{ __('Tukar Kata Laluan') }}
                            </button>
                        </form>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="block min-h-12 w-full border-l-4 border-transparent px-4 py-3 text-start text-base font-bold text-red-600 hover:border-gray-300 hover:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-emerald-600">
                                {{ __('Logout') }}
                            </button>
                        </form>
                    </div>
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
