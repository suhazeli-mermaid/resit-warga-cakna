<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-xl font-semibold text-gray-800">Sistem / Log E-mel</h1>
            <p class="text-sm text-gray-500">Audit penghantaran e-mel aplikasi tanpa menyimpan token atau kandungan rahsia.</p>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
            @include('admin.system.partials.subnavigation')

            @if(session('success'))
                <div class="rounded-lg border border-green-300 bg-green-50 p-4 text-green-800">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="rounded-lg border border-red-300 bg-red-50 p-4 text-red-800">{{ session('error') }}</div>
            @endif

            <div class="rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">
                <strong>DIHANTAR</strong> bermaksud aplikasi berjaya menyerahkan mesej kepada mail transport yang dikonfigurasi. Ia bukan bukti e-mel telah dibaca atau diterima dalam Inbox.
            </div>

            <form method="GET" action="{{ route('admin.email-logs.index') }}" class="rounded-xl bg-white p-5 shadow-sm">
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-5">
                    <label class="lg:col-span-2">
                        <span class="text-sm font-bold text-gray-700">Carian</span>
                        <input name="search" value="{{ request('search') }}" maxlength="255" placeholder="Nama, e-mel, subjek atau rujukan" class="mt-1 min-h-11 w-full rounded-md border-gray-300">
                    </label>
                    <label>
                        <span class="text-sm font-bold text-gray-700">Status</span>
                        <select name="status" class="mt-1 min-h-11 w-full rounded-md border-gray-300">
                            <option value="">Semua</option>
                            <option value="sent" @selected(request('status') === 'sent')>Dihantar</option>
                            <option value="failed" @selected(request('status') === 'failed')>Gagal</option>
                            <option value="pending" @selected(request('status') === 'pending')>Menunggu</option>
                        </select>
                    </label>
                    <label class="lg:col-span-2">
                        <span class="text-sm font-bold text-gray-700">Jenis E-mel</span>
                        <select name="mail_type" class="mt-1 min-h-11 w-full rounded-md border-gray-300">
                            <option value="">Semua jenis</option>
                            @foreach($types as $value => $label)
                                <option value="{{ $value }}" @selected(request('mail_type') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        <span class="text-sm font-bold text-gray-700">Dari</span>
                        <input type="date" name="date_from" value="{{ request('date_from') }}" class="mt-1 min-h-11 w-full rounded-md border-gray-300">
                    </label>
                    <label>
                        <span class="text-sm font-bold text-gray-700">Hingga</span>
                        <input type="date" name="date_to" value="{{ request('date_to') }}" class="mt-1 min-h-11 w-full rounded-md border-gray-300">
                    </label>
                </div>
                <div class="mt-4 flex flex-wrap gap-2">
                    <button class="min-h-11 rounded-md bg-emerald-700 px-5 font-bold text-white hover:bg-emerald-800">Cari / Tapis</button>
                    <a href="{{ route('admin.email-logs.index') }}" class="min-h-11 rounded-md border border-gray-300 px-5 py-2.5 font-bold text-gray-700 hover:bg-gray-50">Reset</a>
                </div>
            </form>

            <section class="hidden overflow-hidden rounded-xl bg-white shadow-sm md:block">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-600">
                            <tr>
                                <th class="p-3">Tarikh/Masa</th>
                                <th class="p-3">Jenis E-mel</th>
                                <th class="p-3">Penerima</th>
                                <th class="p-3">Subjek / Rujukan</th>
                                <th class="p-3">Status</th>
                                <th class="p-3">Tindakan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($logs as $log)
                                <tr>
                                    <td class="whitespace-nowrap p-3">{{ ($log->attempted_at ?? $log->created_at)?->format('d/m/Y H:i:s') }}</td>
                                    <td class="p-3 font-semibold">{{ $log->mail_type_label }}</td>
                                    <td class="p-3"><div class="font-semibold">{{ $log->recipient_name ?: '—' }}</div><div class="break-all text-xs text-gray-500">{{ $log->recipient_email }}</div></td>
                                    <td class="max-w-sm p-3"><div>{{ $log->subject }}</div><div class="mt-1 text-xs font-medium text-gray-500">{{ $log->related_reference ?: 'Tiada rujukan' }}</div></td>
                                    <td class="p-3"><span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-bold {{ $log->status_badge_classes }}">{{ $log->status_label }}</span></td>
                                    <td class="p-3"><a href="{{ route('admin.email-logs.show', $log) }}" class="font-bold text-emerald-700 hover:underline">Papar</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="p-10 text-center text-gray-500">Belum ada log e-mel yang sepadan.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="space-y-3 md:hidden">
                @forelse($logs as $log)
                    <article class="rounded-xl bg-white p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="text-xs font-bold uppercase text-gray-500">{{ $log->mail_type_label }}</div>
                                <div class="mt-1 truncate font-bold text-gray-900">{{ $log->recipient_name ?: $log->recipient_email }}</div>
                                <div class="break-all text-sm text-gray-600">{{ $log->recipient_email }}</div>
                            </div>
                            <span class="shrink-0 rounded-full border px-2.5 py-1 text-xs font-bold {{ $log->status_badge_classes }}">{{ $log->status_label }}</span>
                        </div>
                        <div class="mt-3 text-sm text-gray-700">{{ $log->subject }}</div>
                        <div class="mt-2 flex flex-wrap justify-between gap-2 text-xs text-gray-500">
                            <span>{{ ($log->attempted_at ?? $log->created_at)?->format('d/m/Y H:i') }}</span>
                            <span>{{ $log->related_reference ?: 'Tiada rujukan' }}</span>
                        </div>
                        <a href="{{ route('admin.email-logs.show', $log) }}" class="mt-4 inline-flex min-h-11 w-full items-center justify-center rounded-md bg-emerald-700 px-4 font-bold text-white">Papar</a>
                    </article>
                @empty
                    <div class="rounded-xl bg-white p-8 text-center text-gray-500 shadow-sm">Belum ada log e-mel yang sepadan.</div>
                @endforelse
            </section>

            {{ $logs->links() }}
        </div>
    </div>
</x-app-layout>
