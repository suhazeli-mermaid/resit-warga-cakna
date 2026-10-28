<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-xl font-semibold text-gray-800">Smart Update Manager — Tidak Aktif</h1>
            <p class="text-sm text-gray-500">Arkib sejarah operasi kemas kini terdahulu</p>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-6xl space-y-5 px-4 sm:px-6 lg:px-8">
            @include('admin.system.partials.subnavigation')

            <section class="rounded-xl border border-amber-300 bg-amber-50 p-5 text-amber-900 shadow-sm">
                <h2 class="font-bold">Deployment automatik tidak digunakan</h2>
                <p class="mt-1 text-sm">Aplikasi ini tidak menyemak, membuat backup atau memasang kod melalui web. Deployment production diurus secara terkawal melalui cPanel Terminal mengikut runbook dalaman.</p>
                <p class="mt-2 text-sm font-semibold">Halaman ini hanya memaparkan rekod sejarah dan tidak mempunyai tindakan deployment.</p>
            </section>

            <section class="overflow-hidden rounded-xl bg-white shadow-sm">
                <div class="border-b p-5"><h2 class="font-bold">Sejarah Operasi Lama</h2></div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-600">
                            <tr><th class="p-3">Masa</th><th class="p-3">Commit Sasaran</th><th class="p-3">Status</th><th class="p-3">Admin</th><th class="p-3">Laporan</th></tr>
                        </thead>
                        <tbody class="divide-y">
                            @forelse($operations as $operation)
                                <tr>
                                    <td class="whitespace-nowrap p-3">{{ $operation->created_at->format('d/m/Y H:i') }}</td>
                                    <td class="p-3 font-mono">{{ substr((string) $operation->target_commit, 0, 12) }}</td>
                                    <td class="p-3 font-bold">{{ strtoupper(str_replace('_', ' ', $operation->status)) }}</td>
                                    <td class="p-3">{{ $operation->initiator?->name ?? '—' }}</td>
                                    <td class="p-3"><a class="font-bold text-emerald-700" href="{{ route('admin.updates.show', $operation) }}">Lihat rekod</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="p-8 text-center text-gray-500">Tiada rekod sejarah.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            {{ $operations->links() }}
        </div>
    </div>
</x-app-layout>
