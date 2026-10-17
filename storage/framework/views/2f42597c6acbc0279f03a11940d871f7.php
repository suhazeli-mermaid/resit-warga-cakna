<!DOCTYPE html>
<html lang="ms">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Resit Sumbangan - <?php echo e($receipt->receipt_no); ?></title>
    <style>
        @page {
            margin: 20px;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #222222;
            margin: 0;
            padding: 0;
            font-size: 11px;
        }
        .receipt-box {
            border: 2px solid #046a38;
            padding: 20px;
            background-color: #ffffff;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
        }
        .header-table td {
            vertical-align: middle;
        }
        .logo-img {
            max-height: 65px;
            width: auto;
        }
        .org-name {
            font-size: 13px;
            font-weight: bold;
            color: #046a38;
            text-transform: uppercase;
            line-height: 1.3;
        }
        .org-sub {
            font-size: 9.5px;
            color: #555555;
            margin-top: 2px;
        }
        .receipt-title {
            text-align: right;
            font-size: 18px;
            font-weight: bold;
            color: #046a38;
            letter-spacing: 1px;
        }
        .divider {
            border-top: 2px solid #046a38;
            margin: 12px 0 16px 0;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
        }
        .info-table td {
            padding: 7px 8px;
            border-bottom: 1px solid #eeeeee;
        }
        .label {
            font-weight: bold;
            color: #444444;
            width: 30%;
        }
        .amount-box {
            margin-top: 18px;
            background-color: #f0fdf4;
            border: 1px solid #bbf7d0;
            padding: 12px 15px;
            text-align: right;
        }
        .amount-title {
            font-size: 10px;
            color: #166534;
            font-weight: bold;
        }
        .amount-val {
            font-size: 20px;
            font-weight: bold;
            color: #046a38;
            margin-top: 2px;
        }

        /* SEKSI BENDAHARI & TANDATANGAN */
        .signature-section {
            width: 100%;
            margin-top: 25px;
            border-collapse: collapse;
        }
        .signature-section td {
            vertical-align: bottom;
        }
        .sign-box {
            width: 220px;
            text-align: center;
            float: right;
        }
        .sign-line {
            border-bottom: 1px dashed #046a38;
            height: 45px;
            margin-bottom: 5px;
        }
        .treasurer-title {
            font-size: 10px;
            font-weight: bold;
            color: #046a38;
            text-transform: uppercase;
        }
        .treasurer-name {
            font-size: 10px;
            color: #333333;
        }

        .footer-note {
            margin-top: 20px;
            font-size: 9px;
            color: #666666;
        }
        .footer-text {
            margin-top: 25px;
            font-size: 8.5px;
            color: #777777;
            text-align: center;
            border-top: 1px solid #dddddd;
            padding-top: 8px;
        }
    </style>
</head>
<body>
    <div class="receipt-box">
        <!-- HEADER KELAB / ORGANISASI & LOGO -->
        <table class="header-table">
            <tr>
                <td style="width: 15%; text-align: left;">
                    <?php
                        $logoPath = public_path('images/logo-warga-cakna.png');
                        $logoData = file_exists($logoPath) ? base64_encode(file_get_contents($logoPath)) : '';
                    ?>
                    <?php if($logoData): ?>
                        <img src="data:image/png;base64,<?php echo e($logoData); ?>" class="logo-img" alt="Logo">
                    <?php endif; ?>
                </td>
                <td style="width: 55%; padding-left: 10px;">
                    <div class="org-name">PERSATUAN PRIHATIN WARGA TERPINGGIR TERENGGANU</div>
                    <div class="org-sub">(WARGA CAKNA) | Reg No: PPM-005-11-12052016</div>
                    <div class="org-sub">B17, Taman Kirana, 21700 Kuala Berang, Terengganu</div>
                </td>
                <td style="width: 30%; text-align: right;">
                    <div class="receipt-title">RESIT RASMI</div>
                </td>
            </tr>
        </table>

        <div class="divider"></div>

        <!-- BUTIRAN TRANSAKSI RESIT -->
        <table class="info-table">
            <tr>
                <td class="label">No. Resit:</td>
                <td><strong><?php echo e($receipt->receipt_no); ?></strong></td>
            </tr>
            <tr>
                <td class="label">Diterima Daripada:</td>
                <td><strong><?php echo e($receipt->donor_name); ?></strong></td>
            </tr>
            <tr>
                <td class="label">Emel / No. Telefon:</td>
                <td><?php echo e($receipt->donor_email ?? '-'); ?> / <?php echo e($receipt->donor_phone ?? '-'); ?></td>
            </tr>
            <tr>
                <td class="label">Tarikh Transaksi:</td>
                <td><?php echo e(\Carbon\Carbon::parse($receipt->donated_at)->format('d/m/Y h:i A')); ?></td>
            </tr>
            <tr>
                <td class="label">Kaedah Bayaran:</td>
                <td><?php echo e(strtoupper(str_replace('_', ' ', $receipt->payment_method))); ?></td>
            </tr>
            <tr>
                <td class="label">Tujuan / Catatan:</td>
                <td><?php echo e($receipt->description ?? 'Sumbangan Tabung Kebajikan WARGA CAKNA'); ?></td>
            </tr>
        </table>

        <!-- KOTAK JUMLAH BAYARAN -->
        <div class="amount-box">
            <div class="amount-title">JUMLAH SUMBANGAN:</div>
            <div class="amount-val">RM <?php echo e(number_format($receipt->amount, 2)); ?></div>
        </div>

        <!-- SEKSI TANDATANGAN & BENDAHARI -->
        <table class="signature-section">
            <tr>
                <td style="width: 50%;">
                    <div class="footer-note">
                        * Resit ini dijana secara automatik menerusi sistem komputer WARGA CAKNA.
                    </div>
                </td>
                <td style="width: 50%; text-align: right;">
                    <div class="sign-box">
                        <div class="sign-line"></div>
                        <div class="treasurer-title">BENDAHARI HONORAIS</div>
                        <div class="treasurer-name">Persatuan Prihatin Warga Terpinggir Terengganu</div>
                    </div>
                </td>
            </tr>
        </table>

        <div class="footer-text">
            Terima kasih atas sumbangan ikhlas anda. Sebarang pertanyaan sila hubungi Urusetia WARGA CAKNA (+6019-615 7606).
        </div>
    </div>
</body>
</html><?php /**PATH C:\xampp\htdocs\sistem-resit-dana\resources\views/pdf/donation-receipt.blade.php ENDPATH**/ ?>