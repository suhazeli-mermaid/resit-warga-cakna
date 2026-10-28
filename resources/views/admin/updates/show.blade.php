<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div><h1 class="text-xl font-semibold text-gray-800">Rekod Smart Update Manager — Tidak Aktif</h1><p class="font-mono text-xs text-gray-500">{{ $operation->public_id }}</p></div>
            <a href="{{ route('admin.updates.index') }}" class="font-bold text-emerald-700">← Sejarah</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-5xl space-y-5 px-4 sm:px-6 lg:px-8">
            @include('admin.system.partials.subnavigation')
            @php($details = $operation->details ?? [])

            <section class="rounded-xl border border-amber-300 bg-amber-50 p-5 text-sm text-amber-900">
                <h2 class="font-bold">Rekod audit baca sahaja</h2>
                <p class="mt-1">Tiada tindakan semakan, backup atau pemasangan boleh dijalankan daripada halaman ini.</p>
            </section>

            <section class="rounded-xl bg-white p-5 shadow-sm">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div><div class="text-xs uppercase text-gray-500">Commit Semasa</div><div class="break-all font-mono text-sm">{{ $operation->current_commit }}</div></div>
                    <div><div class="text-xs uppercase text-gray-500">Commit Sasaran</div><div class="break-all font-mono text-sm">{{ $operation->target_commit }}</div></div>
                    <div><div class="text-xs uppercase text-gray-500">Fail Berubah</div><div class="text-xl font-bold">{{ $operation->changed_files_count }}</div></div>
                    <div><div class="text-xs uppercase text-gray-500">Status</div><div class="font-bold">{{ strtoupper(str_replace('_', ' ', $operation->status)) }}</div></div>
                </div>
                @if($operation->target_message)<div class="mt-5 border-t pt-4"><div class="text-xs uppercase text-gray-500">Mesej Commit</div><p class="font-semibold">{{ $operation->target_message }}</p></div>@endif
                @if($operation->failure_message)<div class="mt-4 rounded-md bg-red-50 p-3 text-sm text-red-800">{{ $operation->failure_message }}</div>@endif
            </section>

            <section class="rounded-xl bg-white p-5 shadow-sm">
                <h2 class="font-bold">Butiran Direkodkan</h2>
                <div class="mt-4 grid gap-5 lg:grid-cols-2">
                    <div><h3 class="text-sm font-bold">Commit</h3><ul class="mt-2 space-y-2 text-sm">@forelse($details['commits'] ?? [] as $commit)<li class="rounded bg-gray-50 p-2 font-mono">{{ $commit }}</li>@empty<li class="text-gray-500">Tiada commit direkodkan.</li>@endforelse</ul></div>
                    <div><h3 class="text-sm font-bold">Migrasi</h3><ul class="mt-2 space-y-2 text-sm">@forelse($details['migrations'] ?? [] as $migration)<li class="break-all rounded bg-amber-50 p-2">{{ $migration }}</li>@empty<li class="text-gray-500">Tiada migrasi direkodkan.</li>@endforelse</ul></div>
                </div>
            </section>

            @if(isset($details['backup']))
                <section class="rounded-xl bg-white p-5 shadow-sm">
                    <h2 class="font-bold">Metadata Backup Lama</h2>
                    <pre class="mt-3 max-h-72 overflow-auto whitespace-pre-wrap break-all rounded bg-gray-950 p-4 text-xs text-gray-100">{{ json_encode($details['backup'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                </section>
            @endif
        </div>
    </div>
</x-app-layout>
