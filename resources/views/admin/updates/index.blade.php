<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-xl font-semibold text-gray-800">Sistem / Smart Update Manager v2</h1>
            <p class="text-sm text-gray-500">cPanel Safe Mode / Terminal Deployment</p>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-6xl space-y-5 px-4 sm:px-6 lg:px-8">
            @include('admin.system.partials.subnavigation')

            <section class="rounded-xl border-2 border-emerald-300 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="font-bold text-emerald-800">cPanel Safe Mode / Terminal Deployment</h2>
                        <p class="mt-1 max-w-3xl text-sm text-gray-700">PHP tidak menjalankan arahan sistem. Semakan Git, backup dan pemasangan hanya dilakukan oleh skrip terminal yang diaudit.</p>
                    </div>
                    <span class="rounded-md bg-emerald-100 px-3 py-2 text-sm font-bold text-emerald-800">TERMINAL ONLY</span>
                </div>

                @if($runtime['unavailable_functions'] !== [])
                    <p class="mt-4 text-sm text-gray-600">Fungsi PHP tidak tersedia: <span class="font-mono">{{ implode(', ', $runtime['unavailable_functions']) }}</span></p>
                @endif

                <div class="mt-5 rounded-lg bg-gray-950 p-4 text-sm text-gray-100">
                    <div class="font-mono">cd {{ $terminalDirectory }}</div>
                    <div class="font-mono">{{ $terminalCommand }}</div>
                </div>
                <p class="mt-3 text-xs text-gray-500">Pemeriksaan sahaja: <span class="font-mono">bash deploy.sh --self-check</span> atau <span class="font-mono">bash deploy.sh --dry-run</span>.</p>
            </section>

            <section class="rounded-xl bg-white p-5 shadow-sm">
                <h2 class="font-bold">Keadaan Repositori Diketahui</h2>
                <dl class="mt-4 grid gap-4 sm:grid-cols-3">
                    <div><dt class="text-xs uppercase text-gray-500">Branch</dt><dd class="font-mono text-sm">{{ $repository['branch'] ?? 'Tidak diketahui' }}</dd></div>
                    <div><dt class="text-xs uppercase text-gray-500">Commit Semasa</dt><dd class="break-all font-mono text-sm">{{ $repository['current_commit'] ?? 'Tidak diketahui' }}</dd></div>
                    <div><dt class="text-xs uppercase text-gray-500">origin/main Terakhir Diketahui</dt><dd class="break-all font-mono text-sm">{{ $repository['origin_main_commit'] ?? 'Belum diketahui' }}</dd></div>
                </dl>
                <p class="mt-3 text-xs text-gray-500">Paparan ini membaca metadata Git sahaja dan tidak menjalankan Git daripada PHP.</p>
            </section>

            <section class="overflow-hidden rounded-xl bg-white shadow-sm">
                <div class="border-b p-5"><h2 class="font-bold">Sejarah Deployment Terminal</h2></div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-600"><tr><th class="p-3">Masa</th><th class="p-3">Commit</th><th class="p-3">Status</th><th class="p-3">Backup / Checksum</th><th class="p-3">Kegagalan</th></tr></thead>
                        <tbody class="divide-y">
                            @forelse($shellDeployments as $deployment)
                                <tr class="align-top">
                                    <td class="whitespace-nowrap p-3">{{ $deployment['started_at'] ?? '—' }}<br><span class="font-mono text-xs text-gray-500">{{ $deployment['deployment_id'] }}</span></td>
                                    <td class="p-3 font-mono text-xs">{{ $deployment['installed_commit'] ?: ($deployment['target_commit'] ?: $deployment['current_commit']) }}</td>
                                    <td class="p-3 font-bold">{{ strtoupper(str_replace('_', ' ', $deployment['status'])) }}<br><span class="text-xs font-normal text-gray-500">{{ $deployment['mode'] }}</span></td>
                                    <td class="p-3 text-xs">
                                        @if($deployment['code_backup_path'] ?? '')
                                            <div class="break-all">Kod: {{ $deployment['code_backup_path'] }}</div><div class="break-all font-mono">{{ $deployment['code_backup_checksum'] ?? '' }}</div>
                                        @endif
                                        @if($deployment['database_backup_path'] ?? '')
                                            <div class="mt-2 break-all">DB: {{ $deployment['database_backup_path'] }}</div><div class="break-all font-mono">{{ $deployment['database_backup_checksum'] ?? '' }}</div>
                                        @endif
                                        @if(! ($deployment['code_backup_path'] ?? '') && ! ($deployment['database_backup_path'] ?? ''))—@endif
                                    </td>
                                    <td class="p-3 font-mono text-xs">{{ $deployment['failure_code'] ?: '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="p-8 text-center text-gray-500">Belum ada deployment terminal direkodkan.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="overflow-hidden rounded-xl bg-white shadow-sm">
                <div class="border-b p-5"><h2 class="font-bold">Sejarah Smart Update Manager v1</h2><p class="mt-1 text-sm text-gray-500">Rekod lama dikekalkan untuk audit dan paparan sahaja.</p></div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-600"><tr><th class="p-3">Masa</th><th class="p-3">Commit Sasaran</th><th class="p-3">Status</th><th class="p-3">Admin</th><th class="p-3">Laporan</th></tr></thead>
                        <tbody class="divide-y">
                            @forelse($operations as $operation)
                                <tr><td class="whitespace-nowrap p-3">{{ $operation->created_at->format('d/m/Y H:i') }}</td><td class="p-3 font-mono">{{ substr((string) $operation->target_commit, 0, 12) }}</td><td class="p-3 font-bold">{{ strtoupper(str_replace('_', ' ', $operation->status)) }}</td><td class="p-3">{{ $operation->initiator?->name ?? '—' }}</td><td class="p-3"><a class="font-bold text-emerald-700" href="{{ route('admin.updates.show', $operation) }}">Laporan</a></td></tr>
                            @empty
                                <tr><td colspan="5" class="p-8 text-center text-gray-500">Tiada sejarah v1.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
            {{ $operations->links() }}
        </div>
    </div>
</x-app-layout>
