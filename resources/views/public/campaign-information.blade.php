<x-app-layout>
    <div class="bg-gray-50 min-h-screen py-8">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

            <!-- NAVIGASI -->
            <div class="flex flex-wrap justify-between items-center gap-3">
                <a href="{{ route('public.index') }}"
                   class="text-emerald-800 font-semibold text-sm hover:underline">
                    &larr; Kembali ke Senarai Dana
                </a>

                <a href="{{ route('dana.show', $campaign->slug ?? $campaign->id) }}#sumbang"
                   class="bg-emerald-700 hover:bg-emerald-800 text-white px-5 py-3 rounded-xl font-bold text-sm">
                    Sumbang Sekarang &rarr;
                </a>
            </div>

            <!-- PROFIL KEMPEN -->
            <section class="bg-white rounded-2xl shadow-sm overflow-hidden">

                @if($campaign->image_url)
                    <img src="{{ $campaign->image_url }}"
                         alt="{{ $campaign->title }}"
                         loading="lazy"
                         decoding="async"
                         class="w-full max-h-[450px] object-contain bg-gray-100">
                @endif

                <div class="p-5 sm:p-6 md:p-10 space-y-5">
                    <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900">
                        {{ $campaign->title }}
                    </h1>

                    <p class="text-gray-600 leading-relaxed whitespace-pre-line">{{ $campaign->description }}</p>

                    <div class="border-t pt-5">
                        @php
                            $target = (float) $campaign->target_amount;
                            $collected = (float) ($campaign->collected_amount ?? 0);
                            $percentage = $target > 0
                                ? min(100, max(0, round($collected / $target * 100)))
                                : 0;
                        @endphp

                        <div class="flex justify-between text-sm font-bold mb-2">
                            <span>Terkumpul: RM {{ number_format($collected, 2) }}</span>
                            <span>{{ $percentage }}%</span>
                        </div>

                        <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
                            <div class="bg-emerald-700 h-3 rounded-full"
                                 style="width: {{ $percentage }}%"></div>
                        </div>

                        <p class="text-xs text-gray-500 mt-2">
                            Sasaran: RM {{ number_format($target, 2) }}
                        </p>
                    </div>
                </div>
            </section>

            <!-- MAKLUMAT LENGKAP -->
            @if($detail)
                <section class="bg-white rounded-2xl shadow-sm p-5 sm:p-6 md:p-10 space-y-6">
                    <h2 class="text-2xl font-bold text-gray-900">
                        Maklumat Lengkap Kempen
                    </h2>

                    @foreach([
                        'Latar Belakang' => $detail->background,
                        'Objektif Kempen' => $detail->objectives,
                        'Penerima Manfaat' => $detail->beneficiaries
                    ] as $heading => $content)
                        @if($content)
                            <div>
                                <h3 class="font-bold text-emerald-800 mb-2">
                                    {{ $heading }}
                                </h3>
                                <p class="text-gray-700 leading-relaxed whitespace-pre-line">{{ $content }}</p>
                            </div>
                        @endif
                    @endforeach

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm border-t pt-5">
                        @if($detail->location)
                            <p><strong>Lokasi:</strong> {{ $detail->location }}</p>
                        @endif

                        @if($detail->organiser)
                            <p><strong>Penganjur:</strong> {{ $detail->organiser }}</p>
                        @endif

                        @if($detail->start_date)
                            <p><strong>Tarikh Mula:</strong>
                                {{ $detail->start_date->format('d/m/Y') }}
                            </p>
                        @endif

                        @if($detail->end_date)
                            <p><strong>Tarikh Tamat:</strong>
                                {{ $detail->end_date->format('d/m/Y') }}
                            </p>
                        @endif

                        @if($detail->contact_info)
                            <p><strong>Maklumat Hubungan:</strong>
                                {{ $detail->contact_info }}
                            </p>
                        @endif
                    </div>
                </section>
            @endif

            <!-- TIMELINE PROGRAM -->
            <section class="bg-white rounded-2xl shadow-sm p-5 sm:p-6 md:p-10">
                <h2 class="text-2xl font-bold mb-7">
                    Perkembangan & Aktiviti Program
                </h2>

                @forelse($updates as $activity)
                    <article class="border-l-4 border-emerald-600 pl-5 pb-9 mb-4">

                        <time class="text-sm font-bold text-emerald-700">
                            {{ $activity->activity_date->format('d/m/Y') }}
                        </time>

                        <h3 class="text-xl font-bold text-gray-900 mt-2">
                            {{ $activity->title }}
                        </h3>

                        @if($activity->location)
                            <p class="text-sm text-gray-500 mt-1">
                                Lokasi: {{ $activity->location }}
                            </p>
                        @endif

                        @if($activity->description)
                            <p class="text-gray-700 mt-4 whitespace-pre-line leading-relaxed">{{ $activity->description }}</p>
                        @endif

                        <!-- GALERI AKTIVITI -->
                        @php
                            $images = $activity->media
                                ->where('media_type', 'image')
                                ->whereNotNull('file_path');
                        @endphp

                        @if($images->isNotEmpty())
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mt-6">
                                @foreach($images as $item)
                                    <figure>
                                        <a href="{{ asset('storage/' . $item->file_path) }}"
                                           target="_blank"
                                           rel="noopener noreferrer">
                                            <img
                                                src="{{ asset('storage/' . $item->file_path) }}"
                                                alt="{{ $item->caption ?: $activity->title }}"
                                                loading="lazy"
                                    decoding="async"
                                    class="w-full h-36 sm:h-44 object-cover rounded-xl border hover:opacity-90">
                                        </a>

                                        @if($item->caption)
                                            <figcaption class="text-xs text-gray-500 mt-2">
                                                {{ $item->caption }}
                                            </figcaption>
                                        @endif
                                    </figure>
                                @endforeach
                            </div>
                        @endif

                        <!-- VIDEO YOUTUBE -->
                        @foreach($activity->media->where('media_type', 'youtube') as $video)
                            @php
                                $youtubeId = null;
                                $url = $video->youtube_url ?? '';
                                $parts = parse_url($url);
                                $host = strtolower($parts['host'] ?? '');
                                $path = $parts['path'] ?? '';
                                parse_str($parts['query'] ?? '', $query);

                                if (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com'], true)) {
                                    if ($path === '/watch') {
                                        $youtubeId = $query['v'] ?? null;
                                    } elseif (preg_match('~^/(embed|shorts|live)/([A-Za-z0-9_-]{11})$~', $path, $matches)) {
                                        $youtubeId = $matches[2];
                                    }
                                } elseif (in_array($host, ['youtu.be', 'www.youtu.be'], true)) {
                                    $youtubeId = ltrim($path, '/');
                                } elseif (in_array($host, ['youtube-nocookie.com', 'www.youtube-nocookie.com'], true)) {
                                    if (preg_match('~^/embed/([A-Za-z0-9_-]{11})$~', $path, $matches)) {
                                        $youtubeId = $matches[1];
                                    }
                                }

                                if (!is_string($youtubeId) || !preg_match('/^[A-Za-z0-9_-]{11}$/', $youtubeId)) {
                                    $youtubeId = null;
                                }
                            @endphp

                            @if($youtubeId)
                                <div class="mt-6 max-w-3xl">
                                    <div class="aspect-video rounded-xl overflow-hidden bg-black">
                                        <iframe
                                            src="https://www.youtube-nocookie.com/embed/{{ $youtubeId }}"
                                            title="{{ $video->caption ?: 'Video aktiviti program' }}"
                                            class="w-full h-full"
                                            loading="lazy"
                                            referrerpolicy="strict-origin-when-cross-origin"
                                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                                            allowfullscreen>
                                        </iframe>
                                    </div>

                                    @if($video->caption)
                                        <p class="text-sm text-gray-500 mt-2">
                                            {{ $video->caption }}
                                        </p>
                                    @endif
                                </div>
                            @endif
                        @endforeach

                    </article>
                @empty
                    <p class="text-gray-500 text-sm">
                        Belum ada perkembangan program yang diterbitkan.
                    </p>
                @endforelse
            </section>

            <!-- BUTANG SUMBANGAN -->
            <section class="bg-emerald-900 text-white rounded-2xl p-8 text-center">
                <h2 class="text-2xl font-bold mb-3">
                    Bersama Kita Membantu
                </h2>

                <p class="text-emerald-100 mb-6">
                    Sumbangan anda membantu menjayakan kempen ini.
                </p>

                <a href="{{ route('dana.show', $campaign->slug ?? $campaign->id) }}#sumbang"
                   class="inline-block bg-white text-emerald-900 font-bold px-8 py-3 rounded-xl hover:bg-emerald-50">
                    Sumbang Sekarang &rarr;
                </a>
            </section>

        </div>
    </div>
</x-app-layout>
