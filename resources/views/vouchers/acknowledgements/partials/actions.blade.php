@php($voucher = $acknowledgement->voucher)
<a href="{{ route('vouchers.acknowledgements.show', $acknowledgement) }}" class="font-bold text-violet-700 hover:text-violet-900">Papar</a>
@if($acknowledgement->used_at)
    <a href="{{ route('vouchers.acknowledgements.document', $acknowledgement) }}" class="font-bold text-emerald-700 hover:text-emerald-900">Papar / Cetak Surat</a>
    <a href="{{ route('vouchers.acknowledgements.pdf', $acknowledgement) }}" class="font-bold text-gray-700 hover:text-gray-900">PDF Surat Akuan</a>
@endif
<a href="{{ route('vouchers.show', $voucher) }}" class="font-bold text-emerald-700 hover:text-emerald-900">Lihat Baucar</a>
@can('resend', $acknowledgement)
    <form method="POST" action="{{ route('vouchers.resend-acknowledgement', $voucher) }}">@csrf
        <button class="font-bold text-amber-700 hover:text-amber-900">Hantar Semula Pautan</button>
    </form>
@endcan
<a href="{{ route('vouchers.show', $voucher) }}#sejarah" class="font-bold text-blue-700 hover:text-blue-900">Sejarah</a>
