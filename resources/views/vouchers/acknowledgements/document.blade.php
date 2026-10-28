<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ $acknowledgement->acknowledgement_no }}</title>
    <style>
        body { margin: 0; background: #f3f4f6; color: #17211b; font-family: Arial, sans-serif; }
        .actions { display: flex; max-width: 800px; margin: 15px auto; gap: 10px; flex-wrap: wrap; }
        .actions a, .actions button { border: 0; border-radius: 7px; background: #166534; color: #fff; padding: 11px 16px; font-weight: bold; text-decoration: none; }
        .sheet { max-width: 800px; margin: 28px auto; padding: 42px; background: #fff; box-shadow: 0 2px 15px #0002; }
        .header { border-bottom: 3px solid #166534; padding-bottom: 16px; }
        .identity { display: flex; align-items: center; gap: 16px; }
        .document-logo-image { display: block; width: auto; height: auto; max-width: 92px; max-height: 92px; object-fit: contain; object-position: center; }
        .organization-name { font-size: 14px; font-weight: bold; }
        .organization-short { margin-top: 3px; color: #475569; font-size: 12px; }
        .document-title { margin: 8px 0 0; color: #166534; font-size: 22px; }
        .green { color: #166534; }
        .box { margin-top: 24px; border: 1px solid #d1d5db; border-radius: 8px; padding: 18px; }
        .details { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
        .label { color: #64748b; font-size: 10px; text-transform: uppercase; }
        .value { margin-top: 4px; font-weight: bold; }
        .endorsements { display: grid; grid-template-columns: 1fr 1fr; gap: 50px; margin-top: 45px; text-align: center; }
        .document-image-frame { display: flex; width: 100%; align-items: center; justify-content: center; }
        .signature-frame { min-height: 92px; }
        .stamp-frame { min-height: 125px; margin-top: 10px; }
        .document-signature-image { display: block; width: auto; height: auto; max-width: 220px; max-height: 80px; object-fit: contain; object-position: center; }
        .document-stamp-image { display: block; width: auto; height: auto; max-width: 190px; max-height: 115px; object-fit: contain; object-position: center; }
        .footer { margin-top: 40px; border-top: 1px solid #ddd; padding-top: 10px; color: #64748b; font-size: 10px; }
        @media (max-width: 640px) { .sheet { margin: 0; padding: 22px; } .identity { align-items: flex-start; } .document-logo-image { max-width: 70px; max-height: 70px; } .details, .endorsements { display: block; } .endorsements > div { margin-top: 35px; } }
        @media print { body { background: #fff; } .actions { display: none; } .sheet { max-width: none; margin: 0; box-shadow: none; } }
    </style>
</head>
<body>
    @php($doc = $voucher->document_snapshot ?? [])
    @php($applicantIdentity = $voucher->documentSignatoryIdentity('applicant'))
    @php($approverIdentity = $voucher->documentSignatoryIdentity('approver'))
    <div class="actions">
        <button type="button" onclick="window.print()">Cetak</button>
        <a href="{{ route('vouchers.acknowledgements.pdf', $acknowledgement) }}">Muat Turun PDF</a>
        <a href="{{ route('vouchers.acknowledgements.show', $acknowledgement) }}">Kembali</a>
    </div>

    <main class="sheet">
        <header class="header">
            <div class="identity">
                @if($branding['logo_browser_url'] ?? null)<img class="document-logo-image" src="{{ $branding['logo_browser_url'] }}" alt="Logo rasmi Warga Cakna">@endif
                <div>
                    <div class="organization-name">PERSATUAN PRIHATIN WARGA TERPINGGIR TERENGGANU</div>
                    <div class="organization-short">(WARGA CAKNA)</div>
                    <h1 class="document-title">SURAT AKUAN PENERIMAAN BAYARAN</h1>
                </div>
            </div>
            <div style="margin-top:10px"><strong>{{ $acknowledgement->acknowledgement_no }}</strong></div>
        </header>

        <p>Saya, <strong>{{ $acknowledgement->recipient_name }}</strong>, mengaku telah menerima bayaran seperti dinyatakan.</p>

        <section class="box details">
            <div><div class="label">No. Baucar</div><div class="value">{{ $voucher->voucher_no }}</div></div>
            <div><div class="label">Jumlah</div><div class="value">RM {{ number_format($voucher->paid_amount, 2) }}</div></div>
            <div><div class="label">Tarikh Transaksi</div><div class="value">{{ $voucher->transaction_date?->format('d/m/Y') }}</div></div>
            <div><div class="label">Tarikh Akuan</div><div class="value">{{ $acknowledgement->used_at?->format('d/m/Y H:i:s') }}</div></div>
            <div style="grid-column:1/-1"><div class="label">Tujuan</div><div class="value">{{ $voucher->purpose }}</div></div>
            @if($acknowledgement->notes)<div style="grid-column:1/-1"><div class="label">Catatan</div><div>{{ $acknowledgement->notes }}</div></div>@endif
        </section>

        <section class="endorsements">
            <div>
                <div class="label">DITANDATANGANI OLEH</div>
                <div class="document-image-frame signature-frame">@if($images['applicant'] ?? null)<img class="document-signature-image" src="{{ $images['applicant'] }}" alt="Tandatangan pemohon">@endif</div>
                <div class="value">{{ $applicantIdentity['display_name'] }}</div>
                <div>{{ $applicantIdentity['organization_position'] ?: 'AJK Pemohon' }}</div>
            </div>
            <div>
                <div class="label">DILULUSKAN OLEH</div>
                <div class="document-image-frame signature-frame">@if($images['approver'] ?? null)<img class="document-signature-image" src="{{ $images['approver'] }}" alt="Tandatangan pelulus">@endif</div>
                <div class="value">{{ $approverIdentity['display_name'] }}</div>
                @if($approverIdentity['organization_position'])<div>{{ $approverIdentity['organization_position'] }}</div>@endif
                <div class="document-image-frame stamp-frame">@if($images['stamp'] ?? null)<img class="document-stamp-image" src="{{ $images['stamp'] }}" alt="Cop rasmi Warga Cakna">@endif</div>
            </div>
        </section>

        <footer class="footer">Dokumen ini menggunakan snapshot asal. Imej tandatangan dan cop tidak diubah, dipotong atau ditulis semula.</footer>
    </main>
</body>
</html>
