<x-app-layout>
    <x-slot name="header"><div class="flex flex-wrap items-center justify-between gap-3"><div><h1 class="text-xl font-semibold text-gray-800">{{ $voucher->voucher_no }}</h1><p class="text-sm text-gray-500">{{ $voucher->status_label }}</p></div><a href="{{ route('vouchers.index') }}" class="text-sm font-bold text-emerald-700">← Senarai Baucar</a></div></x-slot>
    <div class="py-6"><div class="mx-auto max-w-5xl space-y-5 px-4 sm:px-6 lg:px-8">
        @if(session('success'))<div class="rounded-lg border border-green-300 bg-green-50 p-4 text-sm text-green-800">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="rounded-lg border border-red-300 bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>@endif
        @if($errors->any())<div class="rounded-lg border border-red-300 bg-red-50 p-4 text-sm text-red-800"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @include('vouchers.partials.subnavigation')
        <section class="rounded-xl bg-white p-5 shadow-sm sm:p-7">
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <div><span class="text-xs uppercase text-gray-500">Penerima</span><p class="font-semibold">{{ $voucher->recipient_name }}</p><p class="text-sm text-gray-600">{{ $voucher->recipient_phone }} · {{ $voucher->recipient_email }}</p></div>
                <div><span class="text-xs uppercase text-gray-500">Jumlah</span><p class="text-xl font-bold text-emerald-800">RM {{ number_format($voucher->amount, 2) }}</p></div>
                <div><span class="text-xs uppercase text-gray-500">Tarikh Baucar</span><p class="font-semibold">{{ $voucher->voucher_date->format('d/m/Y') }}</p></div>
                <div class="sm:col-span-2 lg:col-span-3"><span class="text-xs uppercase text-gray-500">Tujuan</span><p class="whitespace-pre-line">{{ $voucher->purpose }}</p></div>
                <div><span class="text-xs uppercase text-gray-500">AJK Pemohon</span><p>{{ $voucher->applicant?->signatoryName() }}</p><p class="text-sm text-gray-500">{{ $voucher->applicant?->organization_position ?: 'Jawatan belum ditetapkan' }}</p></div>
                <div><span class="text-xs uppercase text-gray-500">Pelulus</span><p>{{ $voucher->approver?->signatoryName() ?: '—' }}</p>@if($voucher->approver)<p class="text-sm text-gray-500">{{ $voucher->approver->organization_position ?: 'Jawatan belum ditetapkan' }}</p>@endif</div>
                <div><span class="text-xs uppercase text-gray-500">Tarikh Kelulusan</span><p>{{ $voucher->approved_at?->format('d/m/Y H:i') ?? '—' }}</p></div>
                @if($canViewSensitive)
                    <div><span class="text-xs uppercase text-gray-500">IC (akses terhad)</span><p>{{ $voucher->recipient_ic ?: '—' }}</p></div>
                    <div><span class="text-xs uppercase text-gray-500">Bank (akses terhad)</span><p>{{ $voucher->bank_name ?: '—' }} {{ $voucher->bank_account_no ?: '' }}</p></div>
                    <div><span class="text-xs uppercase text-gray-500">Rujukan transaksi</span><p>{{ $voucher->transaction_reference ?: '—' }}</p></div>
                @endif
            </div>
            <div class="mt-6 flex flex-wrap gap-2">
                @can('update', $voucher)<a href="{{ route('vouchers.edit', $voucher) }}" class="inline-flex min-h-11 items-center rounded-md border border-gray-300 px-4 text-sm font-bold">Kemaskini Draf</a>@endcan
                @if($voucher->isIssuedDocument())<a href="{{ route('vouchers.document', $voucher) }}" class="inline-flex min-h-11 items-center rounded-md bg-gray-800 px-4 text-sm font-bold text-white">Lihat / Cetak</a><a href="{{ route('vouchers.pdf', $voucher) }}" class="inline-flex min-h-11 items-center rounded-md border border-gray-300 px-4 text-sm font-bold">PDF Baucar</a>@endif
                @if($voucher->latestAcknowledgement) @can('view', $voucher->latestAcknowledgement)<a href="{{ route('vouchers.acknowledgements.show', $voucher->latestAcknowledgement) }}" class="inline-flex min-h-11 items-center rounded-md border border-violet-300 px-4 text-sm font-bold text-violet-800">Lihat Surat Akuan</a>@endcan @endif
                @if(in_array($voucher->status, [App\Models\PaymentVoucher::COMPLETED, App\Models\PaymentVoucher::ARCHIVED], true))<a href="{{ route('vouchers.acknowledgement.pdf', $voucher) }}" class="inline-flex min-h-11 items-center rounded-md border border-gray-300 px-4 text-sm font-bold">PDF Surat Akuan</a>@endif
                @if($canViewSensitive && $voucher->payment_proof_path)<a href="{{ route('vouchers.payment-proof', $voucher) }}" class="inline-flex min-h-11 items-center rounded-md border border-gray-300 px-4 text-sm font-bold">Bukti Bayaran</a>@endif
            </div>
        </section>
        <section id="kelulusan" class="grid scroll-mt-24 gap-4 lg:grid-cols-2">
            @can('submit', $voucher)
                <div class="rounded-xl bg-white p-5 shadow-sm">
                    <h2 class="font-bold">Kesediaan Penghantaran</h2>
                    <div class="mt-3 space-y-1 text-sm font-bold">
                        <p class="{{ $applicantReadiness['full_name'] ? 'text-green-700' : 'text-amber-700' }}">Nama Penuh Pemohon: {{ $applicantReadiness['full_name'] ? '✓ Lengkap' : '⚠ Belum Lengkap' }}</p>
                        <p class="{{ $applicantReadiness['position'] ? 'text-green-700' : 'text-amber-700' }}">Jawatan Pemohon: {{ $applicantReadiness['position'] ? '✓ Lengkap' : '⚠ Belum Ditetapkan' }}</p>
                        <p class="{{ $applicantReadiness['signature'] ? 'text-green-700' : 'text-amber-700' }}">Tandatangan Pemohon: {{ $applicantReadiness['signature'] ? '✓ Lengkap' : '⚠ Belum Dimuat Naik' }}</p>
                    </div>
                    @if($applicantReadiness['ready'])
                        <form method="POST" action="{{ route('vouchers.submit', $voucher) }}" class="mt-4">@csrf<p class="mb-3 text-sm">Tandatangan profil pemohon akan disalin sebagai snapshot.</p><button class="min-h-11 rounded-md bg-emerald-700 px-4 font-bold text-white">Hantar untuk Kelulusan</button></form>
                    @else
                        <div class="mt-4 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
                            <p>Profil pemohon belum lengkap. Nama individu, jawatan Warga Cakna dan tandatangan digital diperlukan sebelum Baucar boleh dihantar.</p>
                            @if(auth()->id() === $voucher->applicant_id)
                                <a href="{{ route('profile.edit') }}#profile-information" class="mt-3 inline-flex min-h-11 items-center rounded-md bg-amber-700 px-4 font-bold text-white">Lengkapkan Profil Pengguna</a>
                                @if(! $applicantReadiness['signature'])<a href="{{ route('profile.edit') }}#digital-signature" class="mt-3 ml-2 inline-flex min-h-11 items-center rounded-md border border-amber-700 px-4 font-bold">Muat Naik Tandatangan</a>@endif
                            @elseif(auth()->user()->isAdmin())
                                <a href="{{ route('users.index') }}" class="mt-3 inline-flex min-h-11 items-center rounded-md bg-amber-700 px-4 font-bold text-white">Lengkapkan Profil Pengguna</a>
                            @else
                                <p class="mt-2 font-semibold">Pemohon {{ $voucher->applicant?->name }} dan Admin perlu melengkapkan profil pemohon.</p>
                            @endif
                        </div>
                        <button type="button" disabled class="mt-4 min-h-11 cursor-not-allowed rounded-md bg-gray-300 px-4 font-bold text-gray-600">Hantar untuk Kelulusan</button>
                    @endif
                </div>
            @endcan
            @can('approve', $voucher)
                <div class="rounded-xl bg-white p-5 shadow-sm">
                    <h2 class="font-bold">Kelulusan Baucar</h2>
                    <div class="mt-3 space-y-1 text-sm font-bold">
                        <p class="{{ $currentUserReadiness['full_name'] ? 'text-green-700' : 'text-amber-700' }}">Nama Penuh Pelulus: {{ $currentUserReadiness['full_name'] ? '✓ Lengkap' : '⚠ Belum Lengkap' }}</p>
                        <p class="{{ $currentUserReadiness['position'] ? 'text-green-700' : 'text-amber-700' }}">Jawatan Pelulus: {{ $currentUserReadiness['position'] ? '✓ Lengkap' : '⚠ Belum Ditetapkan' }}</p>
                        <p class="{{ $currentUserReadiness['signature'] ? 'text-green-700' : 'text-amber-700' }}">Tandatangan Pelulus: {{ $currentUserReadiness['signature'] ? '✓ Lengkap' : '⚠ Belum Dimuat Naik' }}</p>
                    </div>
                    @if($currentUserReadiness['ready'])
                        <form method="POST" action="{{ route('vouchers.approve', $voucher) }}" class="mt-4">@csrf<button class="min-h-11 rounded-md bg-emerald-700 px-4 font-bold text-white">Luluskan</button></form>
                    @else
                        <div class="mt-4 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900"><p>Profil pelulus belum lengkap. Nama individu, jawatan Warga Cakna dan tandatangan digital diperlukan.</p><a href="{{ route('profile.edit') }}#profile-information" class="mt-3 inline-flex min-h-11 items-center rounded-md bg-amber-700 px-4 font-bold text-white">Lengkapkan Profil Pengguna</a>@if(! $currentUserReadiness['signature'])<a href="{{ route('profile.edit') }}#digital-signature" class="mt-3 ml-2 inline-flex min-h-11 items-center rounded-md border border-amber-700 px-4 font-bold">Muat Naik Tandatangan</a>@endif</div>
                        <button type="button" disabled class="mt-4 min-h-11 cursor-not-allowed rounded-md bg-gray-300 px-4 font-bold text-gray-600">Luluskan</button>
                    @endif
                    <form method="POST" action="{{ route('vouchers.reject', $voucher) }}" class="mt-4">@csrf<textarea name="reason" required placeholder="Sebab penolakan" class="w-full rounded-md border-gray-300"></textarea><button class="mt-2 min-h-11 rounded-md bg-red-700 px-4 font-bold text-white">Tolak</button></form>
                </div>
            @endcan
            @can('issue', $voucher)
                <div id="pengeluaran" class="scroll-mt-24 rounded-xl bg-white p-5 shadow-sm">
                    <h2 class="font-bold">Pengeluaran Baucar Rasmi</h2>
                    <p class="mt-3 text-sm font-bold {{ $issueReadiness['applicant_identity'] ? 'text-green-700' : 'text-amber-700' }}">Identiti Pemohon: {{ $issueReadiness['applicant_identity'] ? '✓ Lengkap' : '⚠ Belum Lengkap' }}</p>
                    <p class="mt-1 text-sm font-bold {{ $issueReadiness['approver_identity'] ? 'text-green-700' : 'text-amber-700' }}">Identiti Pelulus: {{ $issueReadiness['approver_identity'] ? '✓ Lengkap' : '⚠ Belum Lengkap' }}</p>
                    <p class="mt-3 text-sm font-bold {{ $officialStampReady ? 'text-green-700' : 'text-amber-700' }}">Cop Rasmi: {{ $officialStampReady ? '✓ Lengkap' : '⚠ Belum Dimuat Naik' }}</p>
                    @if($officialStampReady && $issueReadiness['ready'])
                        <form method="POST" action="{{ route('vouchers.issue', $voucher) }}" class="mt-4">@csrf<p class="mb-3 text-sm">Mengunci snapshot gelaran, nama penuh, jawatan, tandatangan dan cop rasmi.</p><button class="min-h-11 rounded-md bg-emerald-700 px-4 font-bold text-white">Keluarkan Baucar Rasmi</button></form>
                    @else
                        <div class="mt-4 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
                            @if(! $issueReadiness['ready'])<p>Profil penandatangan atau snapshot tandatangan belum lengkap. Lengkapkan profil sebelum mengeluarkan Baucar rasmi.</p><a href="{{ auth()->user()->isAdmin() ? route('users.index') : route('profile.edit') }}" class="mt-3 inline-flex min-h-11 items-center rounded-md bg-amber-700 px-4 font-bold text-white">Lengkapkan Profil Pengguna</a>@endif
                            @if(! $officialStampReady)<p class="mt-2">Cop rasmi Warga Cakna belum dimuat naik.</p>@if(auth()->user()->isAdmin())<a href="{{ route('admin.organization.edit') }}" class="mt-3 inline-flex min-h-11 items-center rounded-md bg-amber-700 px-4 font-bold text-white">Tetapan Organisasi</a>@else<p class="mt-2 font-semibold">Sila hubungi Admin untuk memuat naik cop rasmi.</p>@endif @endif
                        </div>
                        <button type="button" disabled class="mt-4 min-h-11 cursor-not-allowed rounded-md bg-gray-300 px-4 font-bold text-gray-600">Keluarkan Baucar Rasmi</button>
                    @endif
                </div>
            @endcan
            @can('confirmPayment', $voucher)<form id="bayaran" method="POST" enctype="multipart/form-data" action="{{ route('vouchers.confirm-payment', $voucher) }}" class="scroll-mt-24 space-y-3 rounded-xl bg-white p-5 shadow-sm">@csrf<h2 class="font-bold">Sahkan Bayaran</h2><input name="paid_amount" type="number" step="0.01" min="0.01" value="{{ old('paid_amount', $voucher->amount) }}" required class="w-full rounded-md border-gray-300" aria-label="Jumlah Bayaran"><input name="transaction_date" type="date" required class="w-full rounded-md border-gray-300" aria-label="Tarikh Transaksi"><input name="transaction_reference" required maxlength="255" placeholder="Rujukan bank / transaksi sebenar" class="w-full rounded-md border-gray-300"><input name="payment_proof" type="file" accept="image/png,image/jpeg,application/pdf" class="block w-full text-sm"><button class="min-h-11 rounded-md bg-emerald-700 px-4 font-bold text-white">Sahkan Bayaran</button></form>@endcan
            @can('resendAcknowledgement', $voucher)<form method="POST" action="{{ route('vouchers.resend-acknowledgement', $voucher) }}" class="rounded-xl bg-white p-5 shadow-sm">@csrf<p class="mb-3 text-sm">Menjana token baharu dan membatalkan pautan akuan lama yang belum digunakan.</p><button class="min-h-11 rounded-md bg-amber-600 px-4 font-bold text-white">Hantar Semula Pautan Akuan</button></form>@endcan
            @can('cancel', $voucher)<form method="POST" action="{{ route('vouchers.cancel', $voucher) }}" class="rounded-xl border border-red-200 bg-white p-5 shadow-sm">@csrf<textarea name="reason" required placeholder="Sebab pembatalan" class="w-full rounded-md border-gray-300"></textarea><button class="mt-2 min-h-11 rounded-md bg-red-700 px-4 font-bold text-white">Batalkan Baucar</button></form>@endcan
            @can('reissue', $voucher)<form method="POST" action="{{ route('vouchers.reissue', $voucher) }}" class="rounded-xl bg-white p-5 shadow-sm">@csrf<button class="min-h-11 rounded-md bg-amber-600 px-4 font-bold text-white">Cipta Baucar Gantian</button></form>@endcan
            @can('archive', $voucher)<form method="POST" action="{{ route('vouchers.archive', $voucher) }}" class="rounded-xl bg-white p-5 shadow-sm">@csrf<input name="reason" maxlength="2000" placeholder="Catatan arkib (pilihan)" class="w-full rounded-md border-gray-300"><button class="mt-2 min-h-11 rounded-md bg-gray-800 px-4 font-bold text-white">Arkibkan</button></form>@endcan
        </section>
        <section id="sejarah" class="scroll-mt-24 rounded-xl bg-white p-5 shadow-sm"><h2 class="font-bold">Sejarah Audit</h2><ol class="mt-4 space-y-3">@foreach($voucher->audits->sortByDesc('created_at') as $audit)<li class="border-l-2 border-emerald-600 pl-3 text-sm"><strong>{{ strtoupper(str_replace('_', ' ', $audit->event)) }}</strong> · {{ $audit->created_at->format('d/m/Y H:i:s') }}<div class="text-gray-600">{{ $audit->actor?->name ?? ucfirst($audit->actor_type) }} @if($audit->reason) — {{ $audit->reason }} @endif</div></li>@endforeach</ol></section>
    </div></div>
</x-app-layout>
