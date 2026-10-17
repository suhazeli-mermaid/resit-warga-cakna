<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Resit Sumbangan - {{ $receipt->receipt_no }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #333333;
            margin: 0;
            padding: 20px;
            font-size: 13px;
        }
        .container {
            border: 2px solid #046a38;
            padding: 25px;
            position: relative;
            background-color: #ffffff;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .header-table td {
            vertical-align: middle;
        }
        .title-main {
            font-size: 16px;
            font-weight: bold;
            color: #046a38;
            text-transform: uppercase;
        }
        .title-sub {
            font-size: 11px;
            color: #555555;
        }
        .receipt-badge {
            text-align: right;
            font-size: 18px;
            font-weight: bold;
            color: #046a38;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .info-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #eeeeee;
        }
        .info-label {
            font-weight: bold;
            color: #555555;
            width: 35%;
        }
        .amount-box {
            margin-top: 25px;
            background-color: #f0fdf4;
            border: 1px solid #bbf7d0;
            padding: 15px;
            text-align: right;
        }
        .amount-label {
            font-size: 12px;
            color: #166534;
            font-weight: bold;
        }
        .amount-value {
            font-size: 22px;
            font-weight: bold;
            color: #046a38;
        }
        
        /* WATERMARK / TAG STATUS BAYARAN */
        .status-stamp {
            position: absolute;
            top: 220px;
            right: 40px;
            padding: 10px 20px;
            font-size: 20px;
            font-weight: bold;
            border-radius: 8px;
            text-transform: uppercase;
            transform: rotate(-10deg);
            opacity: 0.85;
        }
        .status-success {
            color: #15803d;
            border: 3px solid #15803d;
            background-color: #dcfce7;
        }
        .status-pending {
            color: #b91c1c;
            border: 3px solid #b91c1c;
            background-color: #fee2e2;
        }

        .footer-note {
            margin-top: 40px;
            font-size: 10px;
            color: #777777;
            text-align: center;
            border-top: 1px solid #dddddd;
            padding-top: 10px;
        }
    </style>
</head>
<body>
    <div class="container">

        <!-- TAG WATERMARK STATUS -->
        @if($receipt->status === 'completed')
            <div class="status-stamp status-success">
                ✓ BAYAR / BERJAYA
            </div>
        @else
            <div class="status-stamp status-pending">
                ✕ BELUM SELESAI BAYARAN
            </div>
        @endif

        <!-- HEADER LOGO & INFO -->
        <table class="header-table">
            <tr>
                <td>
                    <div class="title-main">PERSATUAN PRIHATIN WARGA TERPINGGIR TERENGGANU</div>
                    <div class="title-sub">(WARGA CAKNA) | Reg No: PPM-005-11-12052016</div>
                    <div class="title-sub">B17, Taman Kirana, 21700 Kuala Berang, Terengganu</div>
                </td>
                <td class="receipt-badge">
                    RESIT RASMI
                </td>
            </tr>
        </table>

        <hr style="border: 0; border-top: 2px solid #046a38;">

        <!-- BUTIRAN TRANSAKSI -->
        <table class="info-table">
            <tr>
                <td class="info-label">No. Resit:</td>
                <td><strong>{{ $receipt->receipt_no }}</strong></td>
            </tr>
            <tr>
                <td class="info-label">Diterima Daripada:</td>
                <td>{{ $receipt->donor_name }}</td>
            </tr>
            <tr>
                <td class="info-label">Emel / Telefon:</td>
                <td>{{ $receipt->donor_email ?? '-' }} / {{ $receipt->donor_phone ?? '-' }}</td>
            </tr>
            <tr>
                <td class="info-label">Tarikh Bayaran:</td>
                <td>{{ \Carbon\Carbon::parse($receipt->donated_at)->format('d/m/Y h:i A') }}</td>
            </tr>
            <tr>
                <td class="info-label">Kaedah Bayaran:</td>
                <td>{{ strtoupper(str_replace('_', ' ', $receipt->payment_method)) }}</td>
            </tr>
            <tr>
                <td class="info-label">Tujuan / Catatan:</td>
                <td>{{ $receipt->description ?? 'Sumbangan Tabung Kebajikan WARGA CAKNA' }}</td>
            </tr>
        </table>

        <!-- KOTAK JUMLAH BAYARAN -->
        <div class="amount-box">
            <div class="amount-label">JUMLAH SUMBANGAN:</div>
            <div class="amount-value">RM {{ number_format($receipt->amount, 2) }}</div>
        </div>

        <div style="margin-top: 30px; font-size: 11px; color: #444;">
            * Resit ini dijana secara komputer. Tiada tandatangan fizikal diperlukan.
        </div>

        <div class="footer-note">
            Terima kasih atas sumbangan murni anda. Sebarang pertanyaan sila hubungi Urusetia WARGA CAKNA (+6019-615 7606).
        </div>
    </div>
</body>
</html>