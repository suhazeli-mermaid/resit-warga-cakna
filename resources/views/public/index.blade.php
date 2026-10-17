<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Dana & Sumbangan - WARGA CAKNA</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex flex-col">

    <header class="bg-emerald-800 text-white shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
            <div class="flex items-center space-x-3">
                <img src="{{ asset('images/logo-warga-cakna.png') }}" class="h-12 w-auto bg-white rounded-full p-1" alt="WARGA CAKNA Logo">
                <div>
                    <h1 class="text-xl font-bold leading-tight">WARGA CAKNA</h1>
                    <p class="text-xs text-emerald-200">Portal Sumbangan & Dana Kebajikan</p>
                </div>
            </div>
            <div>
                @auth
                    <a href="{{ route('receipts.index') }}" class="bg-yellow-400 hover:bg-yellow-500 text-emerald-950 font-bold px-4 py-2 rounded-lg text-sm transition">
                        Papan Pemuka / Resit Saya
                    </a>
                @else
                    <a href="{{ route('login') }}" class="bg-white text-emerald-800 hover:bg-emerald-50 font-semibold px-4 py-2 rounded-lg text-sm shadow transition">
                        Log Masuk
                    </a>
                @endauth
            </div>
        </div>
    </header>

    <section class="bg-emerald-900 text-white py-12 px-4 text-center">
        <div class="max-w-4xl mx-auto">
            <h2 class="text-3xl sm:text-4xl font-extrabold mb-3">Sumbangan Anda, Sinar Harapan Mereka</h2>
            <p class="text-emerald-200 text-base sm:text-lg">Persatuan Prihatin Warga Terpinggir Terengganu (WARGA CAKNA) menjemput anda untuk bersama-sama menyumbang ke dalam kempen kebajikan dan kemumanusiaan kami.</p>
        </div>
    </section>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 flex-grow">
        <h3 class="text-2xl font-bold text-gray-800 mb-6 border-b border-gray-200 pb-3">Tabung & Dana Kempen Terbuka</h3>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @forelse($campaigns as $campaign)
                <div class="bg-white rounded-xl shadow-md overflow-hidden border border-gray-200 flex flex-col hover:shadow-lg transition">
                    <div class="h-48 bg-emerald-100 flex items-center justify-center overflow-hidden relative">
                        @if($campaign->image_path)
                            <img src="{{ asset('storage/' . $campaign->image_path) }}" alt="{{ $campaign->title }}" class="w-full h-full object-cover">
                        @else
                            <div class="text-center p-4">
                                <span class="text-emerald-800 font-bold text-lg">{{ $campaign->title }}</span>
                            </div>
                        @endif
                        <span class="absolute top-3 right-3 bg-emerald-600 text-white text-xs font-bold px-2.5 py-1 rounded-full uppercase">Aktif</span>
                    </div>

                    <div class="p-5 flex-grow flex flex-col justify-between">
                        <div>
                            <h4 class="text-lg font-bold text-gray-900 mb-2">{{ $campaign->title }}</h4>
                            <p class="text-gray-600 text-sm line-clamp-3 mb-4">{{ $campaign->description ?? 'Sumbangan ikhlas untuk membantu mereka yang memerlukan melalui aktiviti kebajikan WARGA CAKNA.' }}</p>
                        </div>

                        <div>
                            @php
                                $percentage = $campaign->target_amount > 0 ? min(100, round(($campaign->collected_amount / $campaign->target_amount) * 100)) : 0;
                            @endphp
                            <div class="mb-3">
                                <div class="flex justify-between text-xs font-semibold text-gray-600 mb-1">
                                    <span>Terkumpul: RM {{ number_format($campaign->collected_amount, 2) }}</span>
                                    <span>{{ $percentage }}%</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2.5">
                                    <div class="bg-emerald-600 h-2.5 rounded-full" style="width: {{ $percentage }}%"></div>
                                </div>
                            </div>

                            <a href="{{ route('dana.show', $campaign->slug) }}" class="block w-full text-center bg-emerald-700 hover:bg-emerald-800 text-white font-bold py-2.5 rounded-lg text-sm shadow transition">
                                Sumbang Sekarang &rarr;
                            </a>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full bg-white p-8 text-center rounded-lg border border-gray-200 text-gray-500">
                    Sistem dalam proses kemaskini kempen. Sila kembali tidak lama lagi.
                </div>
            @endforelse
        </div>
    </main>

    <footer class="bg-gray-800 text-gray-300 text-xs py-6 text-center border-t border-gray-700">
        <p>&copy; {{ date('Y') }} Persatuan Prihatin Warga Terpinggir Terengganu (WARGA CAKNA). Hak Cipta Terpelihara.</p>
        <p class="mt-1 text-gray-400">No. PPM: PPM-001-11-12022018 | Laman Web Rasmi: www.wargacakna.org</p>
    </footer>

</body>
</html>