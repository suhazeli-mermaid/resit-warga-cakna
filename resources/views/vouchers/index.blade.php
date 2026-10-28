<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div><h1 class="text-xl font-semibold text-gray-800">Senarai Baucar Bayaran</h1><p class="text-sm text-gray-500">Rekod Baucar aktif sahaja; Surat Akuan diurus dalam senarai berasingan</p></div>
            @can('create', App\Models\PaymentVoucher::class)
                <a href="{{ route('vouchers.create') }}" class="inline-flex min-h-11 items-center rounded-lg bg-emerald-700 px-4 text-sm font-bold text-white">+ Draf Baharu</a>
            @endcan
        </div>
    </x-slot>

    <div class="py-6"><div class="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
        @if(session('success'))<div class="rounded-lg border border-green-300 bg-green-50 p-4 text-sm text-green-800">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="rounded-lg border border-red-300 bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>@endif
        @include('vouchers.partials.subnavigation')

        <form method="GET" class="grid gap-3 rounded-xl bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-6">
            <input name="search" value="{{ request('search') }}" placeholder="No. baucar, penerima, tujuan atau pemohon" class="rounded-md border-gray-300 sm:col-span-2" />
            <select name="status" class="rounded-md border-gray-300">
                <option value="">Semua status aktif</option>
                @foreach($statuses as $status)
                    @php
                        $label = (new App\Models\PaymentVoucher(['status' => $status]))->status_label;
                    @endphp
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ $label }}</option>
                @endforeach
            </select>
            @if($applicants->isNotEmpty())
                <select name="applicant_id" class="rounded-md border-gray-300"><option value="">Semua pemohon</option>@foreach($applicants as $applicant)<option value="{{ $applicant->id }}" @selected((string) request('applicant_id') === (string) $applicant->id)>{{ $applicant->name }}</option>@endforeach</select>
            @endif
            <input name="date_from" value="{{ request('date_from') }}" type="date" class="rounded-md border-gray-300" aria-label="Tarikh Baucar dari" />
            <input name="date_to" value="{{ request('date_to') }}" type="date" class="rounded-md border-gray-300" aria-label="Tarikh Baucar hingga" />
            <div class="flex gap-2"><button class="min-h-11 flex-1 rounded-md bg-gray-800 px-4 text-sm font-bold text-white">Tapis</button><a href="{{ route('vouchers.index') }}" class="inline-flex min-h-11 items-center rounded-md border border-gray-300 px-4 text-sm font-bold">Reset</a></div>
        </form>

        <div class="space-y-3 md:hidden" data-testid="mobile-voucher-list">
            @forelse($vouchers as $voucher)
                @php
                    $paymentLabel = $voucher->payment_confirmed_at ? 'DIBAYAR' : ($voucher->isIssuedDocument() ? 'MENUNGGU BAYARAN' : 'BELUM DIKELUARKAN');
                    $ackLabel = $voucher->latestAcknowledgement?->status_label ?? 'BELUM ADA';
                @endphp
                <article class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-3"><div><a class="font-bold text-emerald-800" href="{{ route('vouchers.show', $voucher) }}">{{ $voucher->voucher_no }}</a><p class="mt-1 font-semibold text-gray-900">{{ $voucher->recipient_name }}</p></div><strong class="whitespace-nowrap text-emerald-800">RM {{ number_format($voucher->amount, 2) }}</strong></div>
                    <p class="mt-3 line-clamp-2 text-sm text-gray-600">{{ $voucher->purpose }}</p>
                    <dl class="mt-3 grid grid-cols-2 gap-2 text-xs"><div><dt class="text-gray-500">Pemohon</dt><dd class="font-semibold">{{ $voucher->applicant?->name }}</dd></div><div><dt class="text-gray-500">Tarikh</dt><dd class="font-semibold">{{ $voucher->voucher_date->format('d/m/Y') }}</dd></div><div><dt class="text-gray-500">Lifecycle</dt><dd><span class="inline-flex rounded-full bg-gray-100 px-2 py-1 font-bold">{{ $voucher->status_label }}</span></dd></div><div><dt class="text-gray-500">Bayaran / Akuan</dt><dd class="mt-1 font-semibold">{{ $paymentLabel }} · {{ $ackLabel }}</dd></div></dl>
                    <div class="mt-4 border-t border-gray-100 pt-3">@include('vouchers.partials.actions', ['voucher' => $voucher])</div>
                </article>
            @empty
                <div class="rounded-xl bg-white p-8 text-center text-gray-500">Tiada Baucar aktif ditemui.</div>
            @endforelse
        </div>

        <div class="hidden overflow-x-auto rounded-xl bg-white shadow-sm md:block">
            <table class="min-w-full divide-y divide-gray-200 text-sm"><thead class="bg-emerald-900 text-left text-xs uppercase text-white"><tr>
                @foreach(['No. Baucar','Penerima / Tujuan','Jumlah','AJK Pemohon','Tarikh Baucar','Lifecycle','Bayaran','Akuan','Tindakan'] as $heading)<th class="p-3">{{ $heading }}</th>@endforeach
            </tr></thead><tbody class="divide-y divide-gray-100">
                @forelse($vouchers as $voucher)
                    @php
                        $paymentLabel = $voucher->payment_confirmed_at ? 'DIBAYAR' : ($voucher->isIssuedDocument() ? 'MENUNGGU BAYARAN' : 'BELUM DIKELUARKAN');
                        $ackLabel = $voucher->latestAcknowledgement?->status_label ?? 'BELUM ADA';
                    @endphp
                    <tr class="align-top"><td class="whitespace-nowrap p-3 font-bold">{{ $voucher->voucher_no }}</td><td class="p-3"><div class="font-semibold">{{ $voucher->recipient_name }}</div><div class="mt-1 max-w-xs text-xs text-gray-500">{{ $voucher->purpose }}</div></td><td class="whitespace-nowrap p-3">RM {{ number_format($voucher->amount, 2) }}</td><td class="p-3">{{ $voucher->applicant?->name }}</td><td class="whitespace-nowrap p-3">{{ $voucher->voucher_date->format('d/m/Y') }}</td><td class="p-3"><span class="inline-flex rounded-full bg-gray-100 px-2 py-1 text-xs font-bold">{{ $voucher->status_label }}</span></td><td class="p-3 text-xs font-semibold">{{ $paymentLabel }}</td><td class="p-3 text-xs font-semibold">{{ $ackLabel }}</td><td class="min-w-48 p-3">@include('vouchers.partials.actions', ['voucher' => $voucher])</td></tr>
                @empty<tr><td colspan="9" class="p-8 text-center text-gray-500">Tiada Baucar aktif ditemui.</td></tr>@endforelse
            </tbody></table>
        </div>
        {{ $vouchers->links() }}
    </div></div>
</x-app-layout>
