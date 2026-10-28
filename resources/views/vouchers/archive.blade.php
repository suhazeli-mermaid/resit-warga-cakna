<x-app-layout>
    <x-slot name="header"><div><h1 class="text-xl font-semibold text-gray-800">Arkib Baucar Bayaran</h1><p class="text-sm text-gray-500">Rekod kewangan diarkibkan tanpa hard-delete</p></div></x-slot>

    <div class="py-6"><div class="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
        @if(session('success'))<div class="rounded-lg border border-green-300 bg-green-50 p-4 text-sm text-green-800">{{ session('success') }}</div>@endif
        @include('vouchers.partials.subnavigation')

        <form method="GET" class="grid gap-3 rounded-xl bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-5">
            <input name="search" value="{{ request('search') }}" placeholder="No. baucar, penerima, tujuan atau pemohon" class="rounded-md border-gray-300 sm:col-span-2" />
            @if($applicants->isNotEmpty())<select name="applicant_id" class="rounded-md border-gray-300"><option value="">Semua pemohon</option>@foreach($applicants as $applicant)<option value="{{ $applicant->id }}" @selected((string) request('applicant_id') === (string) $applicant->id)>{{ $applicant->name }}</option>@endforeach</select>@endif
            <input name="date_from" value="{{ request('date_from') }}" type="date" class="rounded-md border-gray-300" aria-label="Tarikh Baucar dari" />
            <input name="date_to" value="{{ request('date_to') }}" type="date" class="rounded-md border-gray-300" aria-label="Tarikh Baucar hingga" />
            <div class="flex gap-2"><button class="min-h-11 flex-1 rounded-md bg-gray-800 px-4 text-sm font-bold text-white">Tapis</button><a href="{{ route('vouchers.archive.index') }}" class="inline-flex min-h-11 items-center rounded-md border border-gray-300 px-4 text-sm font-bold">Reset</a></div>
        </form>

        <div class="space-y-3 md:hidden" data-testid="mobile-voucher-archive-list">
            @forelse($vouchers as $voucher)
                <article class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm"><div class="flex items-start justify-between gap-3"><div><a class="font-bold text-emerald-800" href="{{ route('vouchers.show', $voucher) }}">{{ $voucher->voucher_no }}</a><p class="mt-1 font-semibold">{{ $voucher->recipient_name }}</p></div><strong class="whitespace-nowrap text-emerald-800">RM {{ number_format($voucher->amount, 2) }}</strong></div><p class="mt-2 text-sm text-gray-600">{{ $voucher->purpose }}</p><div class="mt-3 flex justify-between text-xs text-gray-600"><span>{{ $voucher->applicant?->name }}</span><span>{{ $voucher->archived_at?->format('d/m/Y') }}</span></div><div class="mt-4 border-t border-gray-100 pt-3">@include('vouchers.partials.actions', ['voucher' => $voucher])</div></article>
            @empty<div class="rounded-xl bg-white p-8 text-center text-gray-500">Tiada Baucar diarkibkan.</div>@endforelse
        </div>

        <div class="hidden overflow-x-auto rounded-xl bg-white shadow-sm md:block"><table class="min-w-full divide-y divide-gray-200 text-sm"><thead class="bg-gray-800 text-left text-xs uppercase text-white"><tr>@foreach(['No. Baucar','Penerima','Tujuan','Jumlah','Pemohon','Tarikh Arkib','Akuan','Tindakan'] as $heading)<th class="p-3">{{ $heading }}</th>@endforeach</tr></thead><tbody class="divide-y divide-gray-100">@forelse($vouchers as $voucher)<tr class="align-top"><td class="whitespace-nowrap p-3 font-bold">{{ $voucher->voucher_no }}</td><td class="p-3">{{ $voucher->recipient_name }}</td><td class="max-w-xs p-3 text-xs text-gray-600">{{ $voucher->purpose }}</td><td class="whitespace-nowrap p-3">RM {{ number_format($voucher->amount, 2) }}</td><td class="p-3">{{ $voucher->applicant?->name }}</td><td class="whitespace-nowrap p-3">{{ $voucher->archived_at?->format('d/m/Y H:i') }}</td><td class="p-3 text-xs font-semibold">{{ $voucher->latestAcknowledgement?->status_label ?? 'BELUM ADA' }}</td><td class="min-w-48 p-3">@include('vouchers.partials.actions', ['voucher' => $voucher])</td></tr>@empty<tr><td colspan="8" class="p-8 text-center text-gray-500">Tiada Baucar diarkibkan.</td></tr>@endforelse</tbody></table></div>
        {{ $vouchers->links() }}
    </div></div>
</x-app-layout>
