<!DOCTYPE html>
<html lang="ms">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Resit Rasmi Derma - {{ $receipt->receipt_no }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 15mm;
        }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            color: #1a1a1a;
            margin: 0;
            padding: 0;
            font-size: 11px;
            line-height: 1.4;
        }
        .container {
            border: 2px solid #046a38;
            padding: 20px;
            background-color: #ffffff;
            box-sizing: border-box;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .header-table td {
            vertical-align: top;
        }
        .logo-cell {
            width: 70px;
        }
        .logo-img {
            max-height: 65px;
            width: auto;
        }
        .org-title {
            font-size: 13px;
            font-weight: bold;
            color: #046a38;
            text-transform: uppercase;
        }
        .org-details {
            font-size: 9.5px;
            color: #444444;
            margin-top: 2px;
        }
        .receipt-badge {
            text-align: right;
            font-size: 18px;
            font-weight: bold;
            color: #046a38;
            letter-spacing: 1px;
        }
        .divider {
            border-top: 2px solid #046a38;
            margin: 10px 0 15px 0;
        }
        .details-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .details-grid td {
            width: 50%;
            vertical-align: top;
            padding-right: 15px;
        }
        .section-title {
            font-size: 11px;
            font-weight: bold;
            color: #046a38;
            text-transform: uppercase;
            border-bottom: 1px solid #046a38;
            padding-bottom: 3px;
            margin-bottom: 8px;
        }
        .field-table {
            width: 100%;
            border-collapse: collapse;
        }
        .field-table td {
            padding: 3px 0;
            vertical-align: top;
        }
        .field-label {
            width: 40%;
            font-weight: bold;
            color: #555555;
        }
        .field-value {
            width: 60%;
            color: #111111;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            margin-bottom: 15px;
        }
        .items-table th {
            background-color: #046a38;
            color: #ffffff;
            font-size: 10px;
            text-transform: uppercase;
            padding: 6px 8px;
            text-align: left;
            border: 1px solid #046a38;
        }
        .items-table td {
            padding: 8px;
            border: 1px solid #cccccc;
        }
        .amount-row {
            background-color: #f0fdf4;
            font-weight: bold;
            font-size: 13px;
            color: #046a38;
        }
        .footer-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .footer-table td {
            vertical-align: bottom;
        }
        .note-text {
            font-size: 9px;
            color: #555555;
            line-height: 1.3;
        }
        .signature-box {
            text-align: center;
            width: 220px;
            float: right;
        }
        .signature-img {
            max-height: 45px;
            width: auto;
            margin-bottom: -10px;
            display: block;
            margin-left: auto;
            margin-right: auto;
        }
        .signature-line {
            border-bottom: 1px dashed #046a38;
            height: 35px;
            margin-bottom: 5px;
        }
        .treasurer-name {
            font-size: 11px;
            font-weight: bold;
            color: #046a38;
            text-transform: uppercase;
        }
        .treasurer-title {
            font-size: 9.5px;
            color: #444444;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- HEADER -->
        <table class="header-table">
            <tr>
                <td class="logo-cell">
                    @php
                        $logoPath = public_path('images/logo-warga-cakna.png');
                        $logoData = file_exists($logoPath) ? base64_encode(file_get_contents($logoPath)) : '';
                    @endphp
                    @if($logoData)
                        <img src="data:image/png;base64,{{ $logoData }}" class="logo-img" alt="WARGA CAKNA">
                    @endif
                </td>
                <td style="padding-left: 10px;">
                    <div class="org-title">PERSATUAN PRIHATIN WARGA TERPINGGIR TERENGGANU</div>
                    <div class="org-details">(WARGA CAKNA) | No. PPM: PPM-001-11-12022018</div>
                    <div class="org-details">Alamat: B17, Tingkat Atas Bangunan Kedai Taman Kirana, Batu 22, 21700 Kuala Berang, Terengganu</div>
                    <div class="org-details">Tel: +6019-615 7606 | E-mel: wargacakna@wargacakna.org | Laman Web: www.wargacakna.org</div>
                </td>
                <td style="width: 25%; text-align: right;">
                    <div class="receipt-badge">RESIT RASMI<br>DERMA</div>
                </td>
            </tr>
        </table>

        <div class="divider"></div>

        <!-- TWO COLUMN DETAILS -->
        <table class="details-grid">
            <tr>
                <!-- LEFT COLUMN -->
                <td>
                    <div class="section-title">DITERIMA DARIPADA:</div>
                    <table class="field-table">
                        <tr>
                            <td class="field-label">Nama:</td>
                            <td class="field-value"><strong>{{ $receipt->donor_name }}</strong></td>
                        </tr>
                        <tr>
                            <td class="field-label">No. KP / Passport:</td>
                            <td class="field-value">{{ $receipt->ic_passport ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="field-label">No. Telefon:</td>
                            <td class="field-value">{{ $receipt->donor_phone ?? '-' }}</td>
                        </tr>
                    </table>
                </td>

                <!-- RIGHT COLUMN -->
                <td style="padding-right: 0; padding-left: 15px;">
                    <div class="section-title">BUTIRAN RESIT:</div>
                    <table class="field-table">
                        <tr>
                            <td class="field-label">No. Resit:</td>
                            <td class="field-value"><strong>{{ $receipt->receipt_no }}</strong></td>
                        </tr>
                        <tr>
                            <td class="field-label">Tarikh Sumbangan:</td>
                            <td class="field-value">{{ \Carbon\Carbon::parse($receipt->donated_at)->format('d/m/Y') }}</td>
                        </tr>
                        <tr>
                            <td class="field-label">Kaedah Bayaran:</td>
                            <td class="field-value">{{ strtoupper(str_replace('_', ' ', $receipt->payment_method)) }}</td>
                        </tr>
                        <tr>
                            <td class="field-label">No. Rujukan:</td>
                            <td class="field-value">{{ $receipt->payment_reference ?: '-' }}</td>
                        </tr>
                        <tr>
                            <td class="field-label">Status:</td>
                            <td class="field-value"><strong>{{ $receipt->status_label }}</strong></td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <!-- ITEMS & AMOUNT TABLE -->
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 75%;">Keterangan / Tujuan Sumbangan</th>
                    <th style="width: 25%; text-align: right;">Jumlah (RM)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ $receipt->description ?? 'Sumbangan Tabung Kebajikan WARGA CAKNA' }}</td>
                    <td style="text-align: right;">{{ number_format($receipt->amount, 2) }}</td>
                </tr>
                <tr class="amount-row">
                    <td style="text-align: right; text-transform: uppercase;">JUMLAH DITERIMA:</td>
                    <td style="text-align: right;">RM {{ number_format($receipt->amount, 2) }}</td>
                </tr>
            </tbody>
        </table>

        <!-- FOOTER & SIGNATURE BENDAHARI -->
        <table class="footer-table">
            <tr>
                <td style="width: 60%;">
                    <div class="note-text">
                        * Resit ini dijana secara komputer dan sah tanpa cetakan fizikal sekiranya dikeluarkan secara digital.<br>
                        Terima kasih atas sumbangan dan keprihatinan anda bersama WARGA CAKNA.
                    </div>
                </td>
                <td style="width: 40%; text-align: right;">
                    <div class="signature-box">
                        @php
                            $sigSetting = \App\Models\Setting::where('key', 'treasurer_signature')->value('value');
                        @endphp
                        @if($sigSetting && file_exists(public_path('storage/' . $sigSetting)))
                            @php
                                $sigData = base64_encode(file_get_contents(public_path('storage/' . $sigSetting)));
                            @endphp
                            <img src="data:image/png;base64,{{ $sigData }}" class="signature-img" alt="Tandatangan Bendahari">
                        @endif
                        <div class="signature-line"></div>
                        <div class="treasurer-name">HJ KASMADI IBRAHIM</div>
                        <div class="treasurer-title">Bendahari WARGA CAKNA</div>
                    </div>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
