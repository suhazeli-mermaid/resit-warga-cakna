<x-app-layout>
    <div class="min-h-screen bg-stone-50">
        @if(session('success') || session('error'))
            <div class="mx-auto max-w-7xl px-4 pt-4 sm:px-6 lg:px-8">
                <div class="rounded-xl border px-4 py-3 text-sm font-bold {{ session('success') ? 'border-emerald-300 bg-emerald-50 text-emerald-800' : 'border-red-300 bg-red-50 text-red-800' }}">
                    {{ session('success') ?? session('error') }}
                </div>
            </div>
        @endif

        @if($hero)
            <section class="relative isolate overflow-hidden bg-emerald-950 text-white" aria-labelledby="hero-heading">
                @if($hero->banner_image)
                    <img src="{{ asset('storage/'.$hero->banner_image) }}" alt="" fetchpriority="high" class="absolute inset-0 -z-20 h-full w-full object-cover opacity-35">
                @endif
                <div class="absolute inset-0 -z-10 bg-gradient-to-r from-emerald-950 via-emerald-950/90 to-emerald-900/45"></div>
                <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 sm:py-16 lg:px-8 lg:py-24">
                    <div class="max-w-3xl">
                        @if($hero->subtitle)
                            <p class="mb-3 text-sm font-bold uppercase tracking-[0.2em] text-amber-300">{{ $hero->subtitle }}</p>
                        @endif
                        @if($hero->title)
                            <h1 id="hero-heading" class="text-3xl font-extrabold leading-tight sm:text-4xl lg:text-5xl">{{ $hero->title }}</h1>
                        @endif
                        @if($hero->body)
                            <p class="mt-4 max-w-2xl text-base leading-7 text-emerald-50 sm:text-lg">{{ $hero->body }}</p>
                        @endif
                        @if($hero->cta_text && $hero->cta_link)
                            <a href="{{ $hero->cta_link }}" class="mt-7 inline-flex min-h-12 items-center justify-center rounded-xl bg-amber-400 px-6 py-3 font-bold text-emerald-950 shadow-sm transition hover:bg-amber-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-emerald-950">
                                {{ $hero->cta_text }}
                            </a>
                        @endif
                    </div>
                </div>
            </section>
        @endif

        <section class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8" aria-labelledby="quick-actions-heading">
            <h2 id="quick-actions-heading" class="sr-only">Pautan pantas</h2>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                @php
                    $quickActions = [
                        ['label' => 'Sumbang Sekarang', 'href' => '#program-aktif', 'symbol' => '❤', 'gold' => false],
                        ['label' => 'Program Aktif', 'href' => '#program-aktif', 'symbol' => '◎', 'gold' => true],
                        ['label' => 'Resit Saya', 'href' => auth()->check() ? route('receipts.index') : route('login'), 'symbol' => '▤', 'gold' => false],
                        ['label' => 'Senarai Dana', 'href' => '#senarai-dana', 'symbol' => '▦', 'gold' => true],
                    ];
                @endphp
                @foreach($quickActions as $action)
                    <a href="{{ $action['href'] }}" class="flex min-h-24 flex-col items-center justify-center rounded-2xl border {{ $action['gold'] ? 'border-amber-100' : 'border-emerald-100' }} bg-white p-3 text-center font-bold text-gray-800 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 motion-reduce:transform-none">
                        <span class="mb-2 flex h-10 w-10 items-center justify-center rounded-full {{ $action['gold'] ? 'bg-amber-100' : 'bg-emerald-100' }} text-xl" aria-hidden="true">{{ $action['symbol'] }}</span>
                        {{ $action['label'] }}
                    </a>
                @endforeach
            </div>
        </section>

        <section id="program-aktif" class="scroll-mt-20 pb-10" aria-labelledby="program-heading">
            <div id="senarai-dana" class="mx-auto max-w-7xl scroll-mt-20 px-4 sm:px-6 lg:px-8">
                <div class="mb-6">
                    <p class="text-sm font-bold uppercase tracking-wider text-amber-700">Bersama membantu</p>
                    <h2 id="program-heading" class="mt-1 text-2xl font-extrabold text-gray-900 sm:text-3xl">Program Aktif</h2>
                    <p class="mt-2 max-w-2xl text-sm text-gray-600">Pilih kempen semasa untuk melihat maklumat atau membuat sumbangan.</p>
                </div>

                <div class="grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-3">
                    @forelse($campaigns as $campaign)
                        @php
                            $progress = $campaign->target_amount > 0
                                ? min(100, round(($campaign->collected_amount / $campaign->target_amount) * 100))
                                : 0;
                        @endphp
                        <article class="flex overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm transition hover:shadow-md md:flex-col">
                            <div class="relative h-36 w-32 shrink-0 overflow-hidden bg-gray-100 md:h-48 md:w-full">
                                @if($campaign->image_path || $campaign->image_url)
                                    <img src="{{ $campaign->image_url ?? asset('storage/'.$campaign->image_path) }}" loading="lazy" decoding="async" class="h-full w-full object-cover" alt="Poster {{ $campaign->title }}">
                                @else
                                    <div class="flex h-full items-center justify-center px-3 text-center text-xs font-bold text-gray-400">Tiada poster</div>
                                @endif
                                <span class="absolute right-2 top-2 rounded-full bg-emerald-700 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-white">Aktif</span>
                            </div>

                            <div class="flex min-w-0 flex-1 flex-col p-4 md:p-5">
                                <h3 class="line-clamp-2 text-base font-bold leading-snug text-gray-900 md:text-lg">{{ $campaign->title }}</h3>
                                <p class="mt-2 hidden line-clamp-2 text-sm leading-relaxed text-gray-600 sm:block">{{ $campaign->description }}</p>
                                <div class="mt-auto pt-3">
                                    <div class="mb-1 flex justify-between gap-2 text-[11px] font-bold text-gray-600">
                                        <span>RM {{ number_format($campaign->collected_amount ?? 0, 2) }}</span>
                                        <span>{{ $progress }}%</span>
                                    </div>
                                    <div class="h-2 overflow-hidden rounded-full bg-gray-200" role="progressbar" aria-label="Kemajuan kutipan {{ $campaign->title }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $progress }}">
                                        <div class="h-full rounded-full bg-emerald-700" style="width: {{ $progress }}%"></div>
                                    </div>
                                    <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                                        <a href="{{ route('dana.information', $campaign->slug ?? $campaign->id) }}" class="inline-flex min-h-11 flex-1 items-center justify-center rounded-xl border-2 border-emerald-800 px-3 text-sm font-bold text-emerald-800 hover:bg-emerald-50">Maklumat</a>
                                        <a href="{{ route('dana.show', $campaign->slug ?? $campaign->id) }}#sumbang" class="inline-flex min-h-11 flex-1 items-center justify-center rounded-xl bg-emerald-800 px-3 text-sm font-bold text-white hover:bg-emerald-900">Sumbang</a>
                                    </div>
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="col-span-full rounded-2xl border border-gray-200 bg-white p-8 text-center text-gray-500">Tiada kempen sumbangan dibuka pada masa ini.</div>
                    @endforelse
                </div>
            </div>
        </section>
    </div>
</x-app-layout>
