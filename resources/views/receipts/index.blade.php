<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-semibold leading-tight text-gray-800">{{ $isAdmin ? 'Pengurusan Resit' : 'Resit Saya' }}</h1>
            @if($isAdmin)
                <a href="{{ route('receipts.create') }}" class="inline-flex min-h-11 items-center rounded-lg bg-emerald-700 px-4 py-2 text-sm font-bold text-white hover:bg-emerald-800">Cipta Resit</a>
            @endif
        </div>
    </x-slot>

    <div x-data="{ deleteOpen: false, deleting: { id: null, receiptNo: '', donor: '', amount: '', status: '' } }"
         @open-delete-modal.window="deleting = $event.detail; deleteOpen = true; $nextTick(() => $refs.deleteReason.focus())"
         @keydown.escape.window="deleteOpen = false"
         class="py-5 sm:py-7">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            @if(session('success') || session('error'))
                <div class="mb-4 rounded-xl border px-4 py-3 text-sm {{ session('success') ? 'border-emerald-300 bg-emerald-50 text-emerald-800' : 'border-red-300 bg-red-50 text-red-800' }}">
                    {{ session('success') ?? session('error') }}
                </div>
            @endif

            @if($isAdmin)
                @php
                    $filters = [
                        'all' => 'Semua', 'pending' => 'Menunggu', 'completed' => 'Berjaya',
                        'rejected' => 'Ditolak', 'failed' => 'Gagal', 'archived' => 'Diarkibkan', 'deleted' => 'Dihapus',
                    ];
                @endphp
                <nav aria-label="Penapis resit" class="mb-4 -mx-4 overflow-x-auto px-4 pb-1 sm:mx-0 sm:px-0">
                    <div class="flex min-w-max gap-2">
                        @foreach($filters as $value => $label)
                            <a href="{{ route('receipts.index', ['filter' => $value]) }}" @if($filter === $value) aria-current="page" @endif
                               class="inline-flex min-h-11 items-center rounded-full border px-4 py-2 text-sm font-bold {{ $filter === $value ? 'border-emerald-800 bg-emerald-800 text-white' : 'border-gray-300 bg-white text-gray-700 hover:border-emerald-600 hover:text-emerald-800' }}">
                                {{ $label }} ({{ $filterCounts[$value] ?? 0 }})
                            </a>
                        @endforeach
                    </div>
                </nav>
            @endif

            <div class="rounded-2xl bg-white p-4 shadow-sm sm:p-6">
                <form method="GET" action="{{ route('receipts.index') }}" class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-4">
                    <input type="hidden" name="filter" value="{{ $filter }}">
                    <div class="md:col-span-2">
                        <label for="receipt_search" class="mb-1 block text-xs font-bold uppercase text-gray-700">Carian</label>
                        <input id="receipt_search" type="search" name="search" value="{{ request('search') }}" placeholder="No. resit, nama, emel atau telefon" class="min-h-11 w-full rounded-md border-gray-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                    </div>
                    <div>
                        <label for="receipt_sort" class="mb-1 block text-xs font-bold uppercase text-gray-700">Susunan</label>
                        <select id="receipt_sort" name="sort" class="min-h-11 w-full rounded-md border-gray-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                            <option value="latest" @selected(request('sort', 'latest') === 'latest')>Tarikh sumbangan terkini</option>
                            <option value="oldest" @selected(request('sort') === 'oldest')>Tarikh sumbangan terlama</option>
                            <option value="amount_high" @selected(request('sort') === 'amount_high')>Jumlah tertinggi</option>
                            <option value="amount_low" @selected(request('sort') === 'amount_low')>Jumlah terendah</option>
                        </select>
                    </div>
                    <div class="flex items-end gap-2">
                        <button type="submit" class="min-h-11 flex-1 rounded-lg bg-emerald-700 px-4 py-2 text-sm font-bold text-white hover:bg-emerald-800">Cari</button>
                        <a href="{{ route('receipts.index', ['filter' => $filter]) }}" class="inline-flex min-h-11 items-center rounded-lg bg-gray-200 px-4 py-2 text-sm font-bold text-gray-800 hover:bg-gray-300">Reset</a>
                    </div>
                </form>

                <div class="space-y-4 md:hidden" data-testid="mobile-receipt-list">
                    @forelse($receipts as $receipt)
                        <article class="rounded-2xl border border-gray-200 p-4 shadow-sm">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate font-extrabold text-emerald-900">{{ $receipt->receipt_no }}</p>
                                    <p class="mt-1 truncate text-sm font-bold text-gray-900">{{ $receipt->donor_name }}</p>
                                </div>
                                <span class="shrink-0 rounded-full border px-2.5 py-1 text-[10px] font-bold {{ $receipt->status_badge_classes }}">{{ $receipt->status_label }}</span>
                            </div>
                            <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                                <div><dt class="text-xs text-gray-500">Tarikh sumbangan</dt><dd class="mt-1 font-semibold text-gray-800">{{ $receipt->donation_date_label }}</dd></div>
                                <div class="text-right"><dt class="text-xs text-gray-500">Jumlah</dt><dd class="mt-1 text-lg font-extrabold text-emerald-900">RM {{ number_format($receipt->amount, 2) }}</dd></div>
                            </dl>
                            @if($receipt->trashed() && $receipt->latestAudit?->reason)
                                <p class="mt-3 rounded-lg bg-red-50 p-2 text-xs text-red-800"><strong>Sebab:</strong> {{ $receipt->latestAudit->reason }}</p>
                            @endif
                            <details class="mt-4 rounded-xl border border-gray-200 bg-gray-50">
                                <summary class="flex min-h-11 cursor-pointer items-center justify-between px-4 py-2 text-sm font-bold text-gray-800">Tindakan <span aria-hidden="true">⌄</span></summary>
                                <div class="border-t border-gray-200 p-3">
                                    @include('receipts.partials.actions', ['receipt' => $receipt, 'isAdmin' => $isAdmin])
                                </div>
                            </details>
                        </article>
                    @empty
                        <p class="rounded-xl bg-gray-50 p-6 text-center text-gray-500">Tiada rekod resit ditemui.</p>
                    @endforelse
                </div>

                <div class="hidden overflow-x-auto md:block">
                    <table class="w-full text-left">
                        <thead class="bg-emerald-950 text-xs uppercase text-white">
                            <tr><th class="p-3">No. Resit / Penderma</th><th class="p-3">Tarikh Sumbangan</th><th class="p-3">Status</th><th class="p-3 text-right">Jumlah</th><th class="p-3">Tindakan</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-sm">
                            @forelse($receipts as $receipt)
                                <tr class="align-top hover:bg-gray-50">
                                    <td class="p-3"><p class="font-bold text-emerald-900">{{ $receipt->receipt_no }}</p><p class="font-semibold text-gray-900">{{ $receipt->donor_name }}</p><p class="text-xs text-gray-500">{{ $receipt->donor_email ?? '-' }}</p></td>
                                    <td class="p-3 text-sm text-gray-700">{{ $receipt->donation_date_label }}</td>
                                    <td class="p-3"><span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-bold {{ $receipt->status_badge_classes }}">{{ $receipt->status_label }}</span></td>
                                    <td class="p-3 text-right font-extrabold text-emerald-900">RM {{ number_format($receipt->amount, 2) }}</td>
                                    <td class="min-w-64 p-3">@include('receipts.partials.actions', ['receipt' => $receipt, 'isAdmin' => $isAdmin])</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="p-8 text-center text-gray-500">Tiada rekod resit ditemui.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-5">{{ $receipts->links() }}</div>
            </div>
        </div>

        <div x-cloak x-show="deleteOpen" x-transition.opacity class="fixed inset-0 z-[70] flex items-end justify-center bg-black/60 p-0 sm:items-center sm:p-4" role="dialog" aria-modal="true" aria-labelledby="delete-title" @click.self="deleteOpen = false">
            <div class="w-full max-w-lg rounded-t-2xl bg-white p-5 shadow-xl sm:rounded-2xl sm:p-6">
                <h2 id="delete-title" class="text-lg font-extrabold text-red-800">Sahkan Penghapusan</h2>
                <dl class="mt-4 grid grid-cols-2 gap-3 rounded-xl bg-gray-50 p-4 text-sm">
                    <div><dt class="text-gray-500">No. Resit</dt><dd class="font-bold" x-text="deleting.receiptNo"></dd></div>
                    <div><dt class="text-gray-500">Status</dt><dd class="font-bold" x-text="deleting.status"></dd></div>
                    <div><dt class="text-gray-500">Penderma</dt><dd class="font-bold" x-text="deleting.donor"></dd></div>
                    <div><dt class="text-gray-500">Jumlah</dt><dd class="font-bold" x-text="deleting.amount"></dd></div>
                </dl>
                <form method="POST" :action="'{{ url('/receipts') }}/' + deleting.id" class="mt-4">
                    @csrf
                    @method('DELETE')
                    <label for="deletion_reason" class="block text-sm font-bold text-gray-800">Sebab Penghapusan <span class="text-red-600">*</span></label>
                    <textarea x-ref="deleteReason" id="deletion_reason" name="deletion_reason" required maxlength="2000" rows="3" class="mt-1 w-full rounded-lg border-gray-300 focus:border-red-500 focus:ring-red-500"></textarea>
                    <div class="mt-4 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button type="button" @click="deleteOpen = false" class="min-h-11 rounded-lg bg-gray-200 px-4 py-2 font-bold text-gray-800 hover:bg-gray-300">Batal</button>
                        <button type="submit" class="min-h-11 rounded-lg bg-red-700 px-4 py-2 font-bold text-white hover:bg-red-800">Pindah ke Tong Sampah</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
