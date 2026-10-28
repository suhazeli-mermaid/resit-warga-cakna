<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Surat Akuan {{ $voucher->voucher_no }}</title>
    <style>
        body { background: #f3f4f6; color: #17211b; font-family: Arial, sans-serif; }
        .sheet { max-width: 760px; margin: 30px auto; padding: 45px; background: #fff; box-shadow: 0 2px 12px #0002; }
        .header { border-bottom: 3px solid #166534; padding-bottom: 16px; }
        .identity { display: flex; align-items: center; gap: 16px; }
        .document-logo-image { display: block; width: auto; height: auto; max-width: 92px; max-height: 92px; object-fit: contain; object-position: center; }
        .organization-name { font-size: 14px; font-weight: bold; }
        .organization-short { margin-top: 3px; color: #475569; font-size: 12px; }
        .document-title { margin: 8px 0 0; color: #166534; font-size: 22px; }
        .row { margin: 18px 0; }
        .label { color: #64748b; font-size: 11px; text-transform: uppercase; }
        .button { display: inline-block; border: 0; border-radius: 6px; background: #166534; color: #fff; padding: 11px 18px; font-weight: bold; }
        @media (max-width: 640px) { .sheet { margin: 0; padding: 22px; } .identity { align-items: flex-start; } .document-logo-image { max-width: 70px; max-height: 70px; } }
        @media print { body { background: #fff; } .sheet { margin: 0; box-shadow: none; } .button { display: none; } }
    </style>
</head>
<body>
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
        </header>

        <p>Saya, <strong>{{ $acknowledgement->recipient_name }}</strong>, mengaku telah menerima bayaran seperti dinyatakan.</p>
        <div class="row"><div class="label">No. Baucar</div><strong>{{ $voucher->voucher_no }}</strong></div>
        <div class="row"><div class="label">Tujuan</div>{{ $voucher->purpose }}</div>
        <div class="row"><div class="label">Jumlah</div><strong>RM {{ number_format($voucher->paid_amount, 2) }}</strong></div>
        <div class="row"><div class="label">Tarikh Transaksi</div>{{ $voucher->transaction_date?->format('d/m/Y') }}</div>
        <div class="row"><div class="label">Tarikh Akuan</div>{{ $acknowledgement->used_at?->format('d/m/Y H:i:s') }}</div>
        @if($acknowledgement->notes)<div class="row"><div class="label">Catatan</div>{{ $acknowledgement->notes }}</div>@endif
        <button onclick="window.print()" class="button">Cetak Surat Akuan</button>
    </main>
</body>
</html>
