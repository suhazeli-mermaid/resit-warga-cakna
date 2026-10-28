<div class="flex flex-wrap items-center gap-x-3 gap-y-2 text-sm">
    <a href="{{ route('vouchers.show', $voucher) }}" class="font-bold text-emerald-700 hover:text-emerald-900">Papar</a>

    @can('update', $voucher)
        <a href="{{ route('vouchers.edit', $voucher) }}" class="font-bold text-blue-700 hover:text-blue-900">Kemaskini</a>
    @endcan

    @if($voucher->isIssuedDocument())
        <a href="{{ route('vouchers.pdf', $voucher) }}" class="font-bold text-gray-700 hover:text-gray-900">PDF</a>
    @endif

    @can('submit', $voucher)
        @if($voucher->applicant?->hasDigitalSignature())
            <form method="POST" action="{{ route('vouchers.submit', $voucher) }}">@csrf
                <button class="font-bold text-emerald-700 hover:text-emerald-900">Hantar untuk Kelulusan</button>
            </form>
        @else
            <a href="{{ route('vouchers.show', $voucher) }}#kelulusan" class="font-bold text-amber-700 hover:text-amber-900">Tandatangan Belum Lengkap</a>
        @endif
    @endcan

    @can('approve', $voucher)
        @if(auth()->user()->hasDigitalSignature())
            <form method="POST" action="{{ route('vouchers.approve', $voucher) }}">@csrf
                <button class="font-bold text-emerald-700 hover:text-emerald-900">Lulus</button>
            </form>
        @else
            <a href="{{ route('profile.edit') }}#digital-signature" class="font-bold text-amber-700 hover:text-amber-900">Muat Naik Tandatangan</a>
        @endif
        <a href="{{ route('vouchers.show', $voucher) }}#kelulusan" class="font-bold text-red-700 hover:text-red-900">Tolak</a>
    @endcan

    @can('issue', $voucher)
        <a href="{{ route('vouchers.show', $voucher) }}#pengeluaran" class="font-bold text-emerald-700 hover:text-emerald-900">Keluarkan Baucar</a>
    @endcan

    @can('confirmPayment', $voucher)
        <a href="{{ route('vouchers.show', $voucher) }}#bayaran" class="font-bold text-amber-700 hover:text-amber-900">Sahkan Bayaran</a>
    @endcan

    @if($voucher->latestAcknowledgement)
        @can('view', $voucher->latestAcknowledgement)
            <a href="{{ route('vouchers.acknowledgements.show', $voucher->latestAcknowledgement) }}" class="font-bold text-violet-700 hover:text-violet-900">Lihat Akuan</a>
        @endcan
    @endif

    @can('archive', $voucher)
        <form method="POST" action="{{ route('vouchers.archive', $voucher) }}">@csrf
            <button class="font-bold text-gray-700 hover:text-gray-900">Arkib</button>
        </form>
    @endcan
</div>
