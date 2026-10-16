<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Resit Rasmi Derma - WARGA CAKNA</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #222; margin: 0; padding: 15px; font-size: 12px; }
        .header-table { width: 100%; border-bottom: 3px solid #046a38; padding-bottom: 12px; margin-bottom: 20px; }
        .logo-img { width: 95px; height: auto; }
        .org-details { padding-left: 12px; }
        .org-name { font-size: 15px; font-weight: bold; color: #046a38; text-transform: uppercase; margin: 0; }
        .org-sub { font-size: 10.5px; color: #444; margin-top: 3px; line-height: 1.4; }
        .receipt-title-container { text-align: right; vertical-align: top; }
        .receipt-badge { background-color: #fdd835; color: #000; padding: 6px 12px; font-weight: bold; font-size: 13px; border-radius: 4px; display: inline-block; border: 1px solid #d4ac0d; }
        .info-table { width: 100%; margin-bottom: 20px; }
        .info-box { width: 48%; vertical-align: top; }
        .info-card { background-color: #f8f9fa; border: 1px solid #e0e0e0; padding: 10px 12px; border-radius: 4px; line-height: 1.6; }
        .details-table { width: 100%; border-collapse: collapse; margin-top: 10px; margin-bottom: 20px; }
        .details-table th { background-color: #046a38; color: #ffffff; padding: 8px 10px; text-align: left; font-size: 11.5px; }
        .details-table td { border: 1px solid #dddddd; padding: 10px; }
        .amount-box { background-color: #e8f5e9; border: 2px dashed #046a38; padding: 12px; text-align: right; font-size: 15px; font-weight: bold; color: #046a38; margin-bottom: 30px; border-radius: 4px; }
        .footer-table { width: 100%; margin-top: 40px; }
        .sig-space { text-align: center; width: 220px; }
        .sig-line { border-bottom: 1px solid #333; margin-top: 50px; margin-bottom: 5px; }
    </style>
</head>
<body>

    <table class="header-table">
        <tr>
            <td style="width: 95px; vertical-align: middle;">
                <img src="{{ public_path('images/logo-warga-cakna.png') }}" class="logo-img" alt="Logo WARGA CAKNA">
            </td>
            <td class="org-details" style="vertical-align: middle;">
                <div class="org-name">Persatuan Prihatin Warga Terpinggir Terengganu</div>
                <div style="font-weight: bold; color: #d32f2f; font-size: 11px;">(WARGA CAKNA)</div>
                <div class="org-sub">
                    <strong>No. PPM:</strong> PPM-001-11-12022018<br>
                    <strong>Alamat:</strong> B17, Tingkat Atas Bangunan Kedai Taman Kirana, Batu 22, 21700 Kuala Berang, Terengganu<br>
                    <strong>Tel:</strong> +6019-615 7606 | <strong>E-mel:</strong> wargacakna@wargacakna.org<br>
                    <strong>Laman Web:</strong> www.wargacakna.org
                </div>
            </td>
            <td class="receipt-title-container">
                <div class="receipt-badge">RESIT RASMI DERMA</div>
            </td>
        </tr>
    </table>

    <table class="info-table">
        <tr>
            <td class="info-box">
                <div class="info-card">
                    <strong style="color: #046a38;">DITERIMA DARIPADA:</strong><br>
                    <strong>Nama:</strong> {{ $receipt->donor_name }}<br>
                    <strong>No. KP / Passport:</strong> {{ $receipt->donor_ic_passport ?? '-' }}<br>
                    <strong>No. Telefon:</strong> {{ $receipt->donor_phone ?? '-' }}
                </div>
            </td>
            <td style="width: 4%;"></td>
            <td class="info-box">
                <div class="info-card">
                    <strong style="color: #046a38;">BUTIRAN RESIT:</strong><br>
                    <strong>No. Resit:</strong> {{ $receipt->receipt_no }}<br>
                    <strong>Tarikh Sumbangan:</strong> {{ \Carbon\Carbon::parse($receipt->donated_at)->format('d/m/Y') }}<br>
                    <strong>Kaedah Bayaran:</strong> {{ strtoupper($receipt->payment_method) }}<br>
                    <strong>No. Rujukan:</strong> {{ $receipt->payment_reference ?? '-' }}
                </div>
            </td>
        </tr>
    </table>

    <table class="details-table">
        <thead>
            <tr>
                <th style="width: 70%;">Keterangan / Tujuan Sumbangan</th>
                <th style="text-align: right; width: 30%;">Jumlah (RM)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $receipt->description ?? 'Sumbangan Dana Tabung Kebajikan & Bantuan Warga Terpinggir' }}</td>
                <td style="text-align: right; font-weight: bold;">{{ number_format($receipt->amount, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="amount-box">
        JUMLAH DITERIMA: RM {{ number_format($receipt->amount, 2) }}
    </div>

    <table class="footer-table">
        <tr>
            <td style="vertical-align: bottom; color: #666; font-size: 10px; width: 60%;">
                * Resit ini dijana secara komputer dan sah tanpa cetakan fizikal sekiranya dikeluarkan secara digital.<br>
                * Terima kasih atas sumbangan dan keprihatinan anda bersama WARGA CAKNA.
            </td>
            <td class="sig-space">
                <div class="sig-line"></div>
                <strong>HJ KASMADI IBRAHIM</strong><br>
                <small style="color: #555;">Bendahari WARGA CAKNA</small>
            </td>
        </tr>
    </table>

</body>
</html>