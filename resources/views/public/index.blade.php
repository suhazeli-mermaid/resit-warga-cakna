<x-app-layout>
    <div class="py-6 bg-gray-50 min-h-screen">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
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

            <!-- BANNER HERO -->
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
                            <div class="relative h-48 bg-gray-200 overflow-hidden">
                                @if($campaign->image_path || $campaign->image_url)
                                    <img src="{{ $campaign->image_url ?? asset('storage/' . $campaign->image_path) }}" class="w-full h-full object-cover" alt="{{ $campaign->title }}">
                                @else
                                    <div class="w-full h-full flex items-center justify-center text-xs text-gray-400 font-bold">
                                        Tiada Poster
                                    </div>
                                @endif
                                <span class="absolute top-3 right-3 bg-emerald-700 text-white text-[10px] font-bold px-2.5 py-1 rounded-full uppercase tracking-wider">
                                    AKTIF
                                </span>
                            </div>

                            <div class="p-5">
                                <h3 class="text-lg font-bold text-gray-900 mb-2 leading-snug">
                                    {{ $campaign->title }}
                                </h3>
                                <p class="text-gray-600 text-xs leading-relaxed line-clamp-3 mb-4">
                                    {{ $campaign->description }}
                                </p>
                            </div>
                        </div>

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

                            
<div class="grid grid-cols-1 sm:grid-cols-2 gap-3">

    
<a href="{{ route('dana.information', $campaign->slug ?? $campaign->id) }}"
   class="block w-full text-center border-2 border-emerald-800 text-emerald-800 hover:bg-emerald-50 font-bold py-3 rounded-xl text-sm transition">
    Lihat Maklumat
</a>

    <a href="{{ route('dana.show', $campaign->slug ?? $campaign->id) }}#sumbang"
       class="block w-full text-center bg-emerald-800 hover:bg-emerald-900 text-white font-bold py-3 rounded-xl shadow-sm text-sm transition">
        Sumbang Sekarang &rarr;
    </a>

</div>

                        </div>
                    </div>
                @empty
                    <div class="col-span-full bg-white p-8 text-center rounded-2xl border border-gray-200 text-gray-500">
                        Tiada kempen sumbangan dibuka pada masa ini.
                    </div>
                @endforelse
            </div>

        </div>
    </div>
</x-app-layout>