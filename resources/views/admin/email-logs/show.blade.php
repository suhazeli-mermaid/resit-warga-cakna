<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold text-gray-800">Butiran Log E-mel</h1>
                <p class="break-all font-mono text-xs text-gray-500">{{ $emailLog->public_id }}</p>
            </div>
            <a href="{{ route('admin.email-logs.index') }}" class="font-bold text-emerald-700">← Senarai Log</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-5xl space-y-5 px-4 sm:px-6 lg:px-8">
            @include('admin.system.partials.subnavigation')

            @if(session('success'))
                <div class="rounded-lg border border-green-300 bg-green-50 p-4 text-green-800">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="rounded-lg border border-red-300 bg-red-50 p-4 text-red-800">{{ session('error') }}</div>
            @endif
            @if($errors->any())
                <div class="rounded-lg border border-red-300 bg-red-50 p-4 text-red-800">{{ $errors->first() }}</div>
            @endif

            <section class="rounded-xl bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div class="text-xs font-bold uppercase text-gray-500">{{ $emailLog->mail_type_label }}</div>
                        <h2 class="mt-1 text-lg font-bold text-gray-900">{{ $emailLog->subject }}</h2>
                    </div>
                    <span class="rounded-full border px-3 py-1.5 text-xs font-bold {{ $emailLog->status_badge_classes }}">{{ $emailLog->status_label }}</span>
                </div>

                <dl class="mt-6 grid gap-5 sm:grid-cols-2">
                    <div><dt class="text-xs font-bold uppercase text-gray-500">Penerima</dt><dd class="mt-1 font-semibold">{{ $emailLog->recipient_name ?: '—' }}</dd></div>
                    <div><dt class="text-xs font-bold uppercase text-gray-500">E-mel</dt><dd class="mt-1 break-all">{{ $emailLog->recipient_email }}</dd></div>
                    <div><dt class="text-xs font-bold uppercase text-gray-500">Rujukan</dt><dd class="mt-1">{{ $emailLog->related_reference ?: '—' }}</dd></div>
                    <div><dt class="text-xs font-bold uppercase text-gray-500">Dicuba</dt><dd class="mt-1">{{ $emailLog->attempted_at?->format('d/m/Y H:i:s') ?: '—' }}</dd></div>
                    <div><dt class="text-xs font-bold uppercase text-gray-500">Dihantar</dt><dd class="mt-1">{{ $emailLog->sent_at?->format('d/m/Y H:i:s') ?: '—' }}</dd></div>
                    <div><dt class="text-xs font-bold uppercase text-gray-500">Gagal</dt><dd class="mt-1">{{ $emailLog->failed_at?->format('d/m/Y H:i:s') ?: '—' }}</dd></div>
                    @if($emailLog->resentBy)
                        <div><dt class="text-xs font-bold uppercase text-gray-500">Dihantar semula oleh</dt><dd class="mt-1">{{ $emailLog->resentBy->name }}</dd></div>
                    @endif
                    @if($emailLog->resendOf)
                        <div><dt class="text-xs font-bold uppercase text-gray-500">Salinan daripada</dt><dd class="mt-1"><a class="font-bold text-emerald-700" href="{{ route('admin.email-logs.show', $emailLog->resendOf) }}">Lihat log asal</a></dd></div>
                    @endif
                </dl>

                @if($emailLog->failure_message)
                    <div class="mt-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                        <div class="font-bold">Sebab kegagalan yang disanitasi</div>
                        <p class="mt-1 break-words">{{ $emailLog->failure_message }}</p>
                    </div>
                @endif

                <div class="mt-6 flex flex-wrap gap-3 border-t pt-5">
                    @if($relatedLink)
                        <a href="{{ $relatedLink['url'] }}" class="inline-flex min-h-11 items-center rounded-md border border-gray-300 px-4 font-bold text-gray-700 hover:bg-gray-50">{{ $relatedLink['label'] }}</a>
                    @endif
                    <form method="POST" action="{{ route('admin.email-logs.resend', $emailLog) }}" onsubmit="return confirm('Hantar e-mel baharu daripada rekod sumber semasa? Pautan selamat akan dijana semula dan token lama tidak akan digunakan.');">
                        @csrf
                        <input type="hidden" name="confirmed" value="1">
                        <button class="min-h-11 rounded-md bg-emerald-700 px-5 font-bold text-white hover:bg-emerald-800">Hantar Semula</button>
                    </form>
                </div>
            </section>

            <section class="rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">
                Status <strong>DIHANTAR</strong> hanya mengesahkan mail transport menerima mesej tanpa exception. Ia tidak membuktikan penerima membacanya atau mesej masuk ke Inbox.
            </section>

            <section class="rounded-xl bg-white p-5 shadow-sm">
                <h2 class="font-bold text-gray-900">Sejarah Hantar Semula</h2>
                <div class="mt-4 space-y-3">
                    @forelse($emailLog->resends->sortByDesc('created_at') as $resend)
                        <a href="{{ route('admin.email-logs.show', $resend) }}" class="flex min-h-12 flex-wrap items-center justify-between gap-3 rounded-lg border p-3 hover:bg-gray-50">
                            <span><strong>{{ $resend->created_at->format('d/m/Y H:i:s') }}</strong><br><span class="text-sm text-gray-500">{{ $resend->resentBy?->name ?: 'Admin tidak lagi aktif' }}</span></span>
                            <span class="rounded-full border px-2.5 py-1 text-xs font-bold {{ $resend->status_badge_classes }}">{{ $resend->status_label }}</span>
                        </a>
                    @empty
                        <p class="text-sm text-gray-500">Belum pernah dihantar semula melalui Log E-mel.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
