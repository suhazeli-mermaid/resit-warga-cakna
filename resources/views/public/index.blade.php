<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Awam Sumbangan & Dana Kebajikan - WARGA CAKNA</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">

    <!-- HEADER & BAR NAVIGASI UTAMA AWAM -->
    <header class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3 flex justify-between items-center">
            
            <!-- LOGO WARGA CAKNA -->
            <a href="{{ route('public.index') }}" class="flex items-center space-x-3">
                <img src="{{ asset('images/logo-warga-cakna.png') }}" class="h-10 w-auto" alt="Logo WARGA CAKNA">
                <div>
                    <span class="text-base font-extrabold text-emerald-900 block leading-none">WARGA CAKNA</span>
                    <span class="text-[10px] text-gray-500 font-medium">Dana Kebajikan & Kemanusiaan</span>
                </div>
            </a>

            <!-- KAWASAN MENU AKAUN / LOGIN / REGISTER / LOGOUT (SPESIFIK) -->
            <div class="flex items-center space-x-3">
                @if (Route::has('login'))
                    @auth
                        <!-- STATUS APABILA PENGGUNA/AJK SUDAH LOG MASUK -->
                        <div class="flex items-center space-x-2">
                            <a href="{{ route('receipts.index') }}" class="bg-emerald-800 hover:bg-emerald-900 text-white font-bold text-xs px-3.5 py-2 rounded-lg shadow transition flex items-center gap-1">
                                <span>Papan Pemuka</span> &rarr;
                            </a>

                            <form method="POST" action="{{ route('logout') }}" class="inline">
                                @csrf
                                <button type="submit" class="bg-red-50 border border-red-200 text-red-600 hover:bg-red-600 hover:text-white font-bold text-xs px-3.5 py-2 rounded-lg transition">
                                    Log Keluar
                                </button>
                            </form>
                        </div>
                    @else
                        <!-- STATUS APABILA PENGGUNA BELUM LOG MASUK -->
                        <a href="{{ route('login') }}" class="text-xs font-bold text-emerald-900 hover:text-emerald-700 px-3 py-2 transition border border-emerald-800 rounded-lg">
                            Log Masuk
                        </a>

                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="bg-emerald-800 hover:bg-emerald-900 text-white font-bold text-xs px-3.5 py-2 rounded-lg shadow transition">
                                Daftar Akaun
                            </a>
                        @endif
                    @endauth
                @endif
            </div>

        </div>
    </header>

    <!-- KANDUNGAN PORTAL AWAM -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        @if(session('success'))
            <div class="bg-emerald-100 border border-emerald-400 text-emerald-800 px-4 py-3 rounded-xl mb-6 text-sm font-bold">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="bg-red-100 border border-red-400 text-red-800 px-4 py-3 rounded-xl mb-6 text-sm font-bold">
                {{ session('error') }}
            </div>
        @endif

        <!-- BANNER HERO BANNER -->
        <div class="bg-emerald-900 text-white rounded-2xl p-8 md:p-12 mb-10 shadow-md">
            <div class="max-w-3xl">
                <h1 class="text-3xl md:text-4xl font-extrabold mb-4 leading-tight">
                    Sumbangan Anda, Sinar Harapan Mereka
                </h1>
                <p class="text-emerald-100 text-sm md:text-base leading-relaxed mb-6">
                    Persatuan Prihatin Warga Terpinggir Terengganu (WARGA CAKNA) menjemput anda untuk bersama-sama menyumbang ke dalam kempen kebajikan dan kemanusiaan kami.
                </p>
            </div>
        </div>

        <!-- TAJUK SEKSI KEMPEN -->
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-gray-900">Tabung & Dana Kempen Terbuka</h2>
            <p class="text-xs text-gray-500">Pilih kempen di bawah untuk menyumbang secara terus menerusi perbankan dalam talian / ToyyibPay / QR Pay.</p>
        </div>

        <!-- SENARAI KEMPEN DANA -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @forelse($campaigns as $campaign)
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden flex flex-col justify-between hover:shadow-md transition">
                    <div>
                        <!-- GAMBAR BANNER KEMPEN -->
                        <div class="relative h-48 bg-gray-200 overflow-hidden">
                            @if($campaign->image_path || $campaign->image_url)
                                <img src="{{ $campaign->image_url ?? asset('storage/' . $campaign->image_path) }}" class="w-full h-full object-cover alt="{{ $campaign->title }}">
                            @else
                                <div class="w-full h-full flex items-center justify-center text-xs text-gray-400 font-bold">
                                    Tiada Poster
                                </div>
                            @endif
                            <span class="absolute top-3 right-3 bg-emerald-700 text-white text-[10px] font-bold px-2.5 py-1 rounded-full uppercase tracking-wider">
                                AKTIF
                            </span>
                        </div>

                        <!-- MAKLUMAT KEMPEN -->
                        <div class="p-5">
                            <h3 class="text-lg font-bold text-gray-900 mb-2 leading-snug">
                                {{ $campaign->title }}
                            </h3>
                            <p class="text-gray-600 text-xs leading-relaxed line-clamp-3 mb-4">
                                {{ $campaign->description }}
                            </p>
                        </div>
                    </div>

                    <!-- INDIKATOR KUTIPAN & BUTANG SUMBANG -->
                    <div class="p-5 pt-0">
                        <div class="border-t border-gray-100 pt-4 mb-4">
                            <div class="flex justify-between text-xs font-bold text-gray-700 mb-1">
                                <span>Terkumpul: RM {{ number_format($campaign->collected_amount ?? 0, 2) }}</span>
                                <span>{{ $campaign->target_amount > 0 ? round(($campaign->collected_amount / $campaign->target_amount) * 100) : 0 }}%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-emerald-700 h-2 rounded-full" style="width: {{ $campaign->target_amount > 0 ? min(100, round(($campaign->collected_amount / $campaign->target_amount) * 100)) : 0 }}%"></div>
                            </div>
                        </div>

                        <a href="{{ route('dana.show', $campaign->slug ?? $campaign->id) }}" class="block w-full text-center bg-emerald-800 hover:bg-emerald-900 text-white font-bold py-3 rounded-xl shadow-sm text-sm transition">
                            Sumbang Sekarang &rarr;
                        </a>
                    </div>
                </div>
            @empty
                <div class="col-span-full bg-white p-8 text-center rounded-2xl border border-gray-200 text-gray-500">
                    Tiada kempen sumbangan dibuka pada masa ini.
                </div>
            @endforelse
        </div>

    </main>

    <footer class="bg-white border-t border-gray-200 mt-16 py-6 text-center text-xs text-gray-500">
        &copy; {{ date('Y') }} Persatuan Prihatin Warga Terpinggir Terengganu (WARGA CAKNA). Hak Cipta Terelihara.
    </footer>

</body>
</html>