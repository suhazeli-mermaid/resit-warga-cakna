<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resit Sumbangan - WARGA CAKNA</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            background-color: #f4f6f8;
            margin: 0;
            padding: 0;
            -webkit-font-smoothing: antialiased;
        }
        .wrapper {
            width: 100%;
            table-layout: fixed;
            background-color: #f4f6f8;
            padding: 30px 0;
        }
        .main-card {
            background-color: #ffffff;
            margin: 0 auto;
            max-width: 600px;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid #e5e7eb;
        }
        .header {
            background-color: #046a38;
            padding: 25px 20px;
            text-align: center;
        }
        .header img {
            max-height: 70px;
            width: auto;
            background-color: #ffffff;
            padding: 6px;
            border-radius: 50%;
        }
        .header h1 {
            color: #ffffff;
            font-size: 18px;
            margin: 12px 0 0 0;
            font-weight: bold;
            letter-spacing: 0.5px;
        }
        .content {
            padding: 30px 25px;
            color: #333333;
            line-height: 1.6;
        }
        .greeting {
            font-size: 16px;
            font-weight: bold;
            color: #046a38;
            margin-bottom: 10px;
        }
        .receipt-box {
            background-color: #f8faf9;
            border: 1px solid #d1fae5;
            border-left: 4px solid #046a38;
            padding: 18px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .receipt-table {
            width: 100%;
            border-collapse: collapse;
        }
        .receipt-table td {
            padding: 6px 0;
            font-size: 14px;
        }
        .receipt-table td.label {
            color: #6b7280;
            width: 40%;
        }
        .receipt-table td.value {
            font-weight: bold;
            color: #111827;
            text-align: right;
        }
        .amount-row td {
            font-size: 16px !important;
            color: #046a38 !important;
            border-top: 1px dashed #cbd5e1;
            padding-top: 10px !important;
        }
        .btn-container {
            text-align: center;
            margin: 30px 0 20px 0;
        }
        .btn-primary {
            background-color: #046a38;
            color: #ffffff !important;
            text-decoration: none;
            padding: 12px 28px;
            font-weight: bold;
            font-size: 14px;
            border-radius: 6px;
            display: inline-block;
            box-shadow: 0 2px 4px rgba(4, 106, 56, 0.2);
        }
        .disclaimer {
            background-color: #fffbe2;
            border: 1px solid #fef08a;
            padding: 12px 15px;
            font-size: 12px;
            color: #854d0e;
            border-radius: 6px;
            margin-top: 25px;
            line-height: 1.5;
        }
        .footer {
            background-color: #f9fafb;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #6b7280;
            border-top: 1px solid #f3f4f6;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="main-card">
            
            <!-- HEADER BERSERTA LOGO -->
            <div class="header">
                <img src="{{ asset('images/logo-warga-cakna.png') }}" alt="WARGA CAKNA Logo">
                <h1>PERSATUAN PRIHATIN WARGA TERPINGGIR TERENGGANU</h1>
                <div style="color: #a7f3d0; font-size: 12px; margin-top: 4px;">(WARGA CAKNA)</div>
            </div>

            <!-- KANDUNGAN UTAMA -->
            <div class="content">
                <div class="greeting">Salam Sejahtera / Assalamu’alaikum,</div>
                <p>Terima kasih atas keprihatinan dan sumbangan murni yang telah anda hulurkan. Pihak <strong>WARGA CAKNA</strong> mengesahkan penerimaan bayaran sumbangan anda seperti butiran berikut:</p>

                <!-- KOTAK BUTIRAN RESIT -->
                <div class="receipt-box">
                    <table class="receipt-table">
                        <tr>
                            <td class="label">Nama Penderma:</td>
                            <td class="value">{{ $receipt->donor_name }}</td>
                        </tr>
                        <tr>
                            <td class="label">No. Resit:</td>
                            <td class="value">{{ $receipt->receipt_no }}</td>
                        </tr>
                        <tr>
                            <td class="label">Tujuan Sumbangan:</td>
                            <td class="value">{{ $receipt->description ?? 'Tabung Kebajikan WARGA CAKNA' }}</td>
                        </tr>
                        <tr>
                            <td class="label">Tarikh Sumbangan:</td>
                            <td class="value">{{ \Carbon\Carbon::parse($receipt->donated_at)->format('d/m/Y') }}</td>
                        </tr>
                        <tr>
                            <td class="label">Kaedah Bayaran:</td>
                            <td class="value">{{ strtoupper(str_replace('_', ' ', $receipt->payment_method)) }}</td>
                        </tr>
                        <tr class="amount-row">
                            <td class="label" style="font-weight: bold; color: #046a38;">Jumlah Sumbangan:</td>
                            <td class="value">RM {{ number_format($receipt->amount, 2) }}</td>
                        </tr>
                    </table>
                </div>

                <p>Sumbangan anda amat bermakna dalam membantu melancarkan program kebajikan dan pengagihan bantuan kepada golongan terpinggir yang memerlukan.</p>

                <!-- BUTANG PAUTAN PORTAL -->
                <div class="btn-container">
                    <a href="{{ route('receipts.view-pdf', $receipt->id) }}" class="btn-primary" target="_blank">
                        Lihat Resit & Sejarah Bayaran
                    </a>
                </div>

                <!-- PENAFIAN / DISCLAIMER -->
                <div class="disclaimer">
                    <strong>PENAFIAN / DISCLAIMER:</strong><br>
                    Sekiranya terdapat sebarang kesilapan maklumat pada resit ini atau anda memerlukan bantuan lanjut, sila hubungi <strong>Urusetia WARGA CAKNA</strong> di talian <strong>+6019-615 7606</strong> atau emel ke <strong>wargacakna@wargacakna.org</strong>.
                </div>
            </div>

            <!-- FOOTER EMEL -->
            <div class="footer">
                &copy; {{ date('Y') }} Persatuan Prihatin Warga Terpinggir Terengganu (WARGA CAKNA).<br>
                B17, Taman Kirana, 21700 Kuala Berang, Terengganu.<br>
                <small>Emel ini dijana secara automatik oleh Sistem Resit WARGA CAKNA.</small>
            </div>

        </div>
    </div>
</body>
</html>