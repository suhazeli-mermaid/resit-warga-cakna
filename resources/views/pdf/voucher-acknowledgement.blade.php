<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 30px; }
        body { font-family: DejaVu Sans, sans-serif; color: #17211b; font-size: 13px; }
        .header-table { width: 100%; border-bottom: 3px solid #166534; padding-bottom: 12px; margin-bottom: 25px; }
        .logo-cell { width: 88px; text-align: center; vertical-align: middle; }
        .document-logo-image { display: block; width: auto; height: auto; max-width: 78px; max-height: 78px; object-fit: contain; margin: 0 auto; }
        .identity-cell { vertical-align: middle; }
        .organization-name { font-size: 11px; font-weight: bold; }
        .organization-short { font-size: 10px; color: #475569; }
        .document-title { margin-top: 6px; color: #166534; font-size: 17px; font-weight: bold; }
        .document-number { width: 130px; text-align: right; vertical-align: middle; }
        .row { margin: 16px 0; }
        .label { font-size: 9px; text-transform: uppercase; color: #64748b; }
        .box { border: 1px solid #ddd; padding: 16px; margin-top: 24px; }
        .endorsements { width: 100%; margin-top: 34px; text-align: center; }
        .endorsements td { width: 50%; vertical-align: top; padding: 8px; }
        .signature-frame { height: 72px; text-align: center; }
        .stamp-frame { height: 100px; margin-top: 8px; text-align: center; }
        .document-signature-image { display: block; width: auto; height: auto; max-width: 175px; max-height: 65px; object-fit: contain; margin: 0 auto; }
        .document-stamp-image { display: block; width: auto; height: auto; max-width: 155px; max-height: 92px; object-fit: contain; margin: 0 auto; }
        .name { font-weight: bold; margin-top: 4px; }
        .footer { margin-top: 36px; border-top: 1px solid #ddd; padding-top: 8px; font-size: 9px; color: #64748b; }
    </style>
</head>
<body>
    @php($doc = $voucher->document_snapshot ?? [])
    @php($applicantIdentity = $voucher->documentSignatoryIdentity('applicant'))
    @php($approverIdentity = $voucher->documentSignatoryIdentity('approver'))
    <table class="header-table">
        <tr>
            <td class="logo-cell">@if($branding['logo_data_uri'] ?? null)<img class="document-logo-image" src="{{ $branding['logo_data_uri'] }}" alt="Logo rasmi Warga Cakna">@endif</td>
            <td class="identity-cell">
                <div class="organization-name">PERSATUAN PRIHATIN WARGA TERPINGGIR TERENGGANU</div>
                <div class="organization-short">(WARGA CAKNA)</div>
                <div class="document-title">SURAT AKUAN PENERIMAAN BAYARAN</div>
            </td>
            <td class="document-number"><strong>{{ $acknowledgement->acknowledgement_no }}</strong></td>
        </tr>
    </table>

    <p>Saya, <strong>{{ $acknowledgement->recipient_name }}</strong>, mengaku telah menerima bayaran seperti dinyatakan.</p>

    <div class="box">
        <div class="row"><div class="label">No. Baucar</div><strong>{{ $voucher->voucher_no }}</strong></div>
        <div class="row"><div class="label">Tujuan</div>{{ $voucher->purpose }}</div>
        <div class="row"><div class="label">Jumlah</div><strong>RM {{ number_format($voucher->paid_amount, 2) }}</strong></div>
        <div class="row"><div class="label">Tarikh Transaksi</div>{{ $voucher->transaction_date?->format('d/m/Y') }}</div>
        <div class="row"><div class="label">Tarikh Akuan</div>{{ $acknowledgement->used_at?->format('d/m/Y H:i:s') }}</div>
        @if($acknowledgement->notes)<div class="row"><div class="label">Catatan</div>{{ $acknowledgement->notes }}</div>@endif
    </div>

    <table class="endorsements">
        <tr>
            <td>
                <div class="label">DITANDATANGANI OLEH</div>
                <div class="signature-frame">@if($images['applicant'] ?? null)<img class="document-signature-image" src="{{ $images['applicant'] }}" alt="Tandatangan pemohon">@endif</div>
                <div class="name">{{ $applicantIdentity['display_name'] }}</div>
                <div>{{ $applicantIdentity['organization_position'] ?: 'AJK Pemohon' }}</div>
            </td>
            <td>
                <div class="label">DILULUSKAN OLEH</div>
                <div class="signature-frame">@if($images['approver'] ?? null)<img class="document-signature-image" src="{{ $images['approver'] }}" alt="Tandatangan pelulus">@endif</div>
                <div class="name">{{ $approverIdentity['display_name'] }}</div>
                @if($approverIdentity['organization_position'])<div>{{ $approverIdentity['organization_position'] }}</div>@endif
                <div class="stamp-frame">@if($images['stamp'] ?? null)<img class="document-stamp-image" src="{{ $images['stamp'] }}" alt="Cop rasmi Warga Cakna">@endif</div>
            </td>
        </tr>
    </table>

    <div class="footer">Dijana oleh sistem Warga Cakna daripada snapshot dokumen rasmi. Maklumat IC dan akaun bank tidak dipaparkan.</div>
</body>
</html>
