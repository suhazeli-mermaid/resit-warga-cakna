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
            font-family: sans-serif;
            color: #333333;
            margin: 0;
            padding: 0;
            font-size: 12px;
        }
        .receipt-box {
            border: 2px solid #046a38;
            padding: 20px;
            background-color: #ffffff;
            position: relative;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
        }
        .header-table td {
            vertical-align: top;
        }
        .org-name {
            font-size: 14px;
            font-weight: bold;
            color: #046a38;
            text-transform: uppercase;
        }
        .org-sub {
            font-size: 10px;
            color: #555555;
            margin-top: 2px;
        }
        .receipt-title {
            text-align: right;
            font-size: 18px;
            font-weight: bold;
            color: #046a38;
        }
        .divider {
            border-top: 2px solid #046a38;
            margin: 15px 0;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
        }
        .info-table td {
            padding: 6px 8px;
            border-bottom: 1px solid #eeeeee;
        }
        .label {
            font-weight: bold;
            color: #444444;
            width: 32%;
        }
        .amount-box {
            margin-top: 20px;
            background-color: #f0fdf4;
            border: 1px solid #bbf7d0;
            padding: 12px;
            text-align: right;
        }
        .amount-title {
            font-size: 11px;
            color: #166534;
            font-weight: bold;
        }
        .amount-val {
            font-size: 20px;
            font-weight: bold;
            color: #046a38;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            font-weight: bold;
            font-size: 11px;
            border-radius: 4px;
        }
        .status-success {
            background-color: #dcfce7;
            color: #15803d;
            border: 1px solid #86efac;
        }
        .status-failed {
            background-color: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fca5a5;
        }
        .footer-text {
            margin-top: 30px;
            font-size: 9px;
            color: #777777;
            text-align: center;
            border-top: 1px solid #dddddd;
            padding-top: 8px;
        }
    </style>
</head>
<body>
    <div class="receipt-box">
        <table class="header-table">
            <tr>
                <td style="width: 70%;">
                    <div class="org-name">PERSATUAN PRIHATIN WARGA TERPINGGIR TERENGGANU</div>
                    <div class="org-sub">(WARGA CAKNA) | Reg No: PPM-005-11-12052016</div>
                    <div class="org-sub">B17, Taman Kirana, 21700 Kuala Berang, Terengganu</div>
                </td>
                <td style="width: 30%; text-align: right;">
                    <div class="receipt-title">RESIT RASMI</div>
                    <div style="margin-top: 5px;">
                        <?php if($receipt->status === 'completed'): ?>
                            <span class="status-badge status-success">✓ BAYAR / BERJAYA</span>
                        <?php else: ?>
                            <span class="status-badge status-failed">✕ BELUM BAYAR</span>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        </table>

        <div class="divider"></div>

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

        <div class="amount-box">
            <div class="amount-title">JUMLAH SUMBANGAN</div>
            <div class="amount-val">RM <?php echo e(number_format($receipt->amount, 2)); ?></div>
        </div>

        <div style="margin-top: 20px; font-size: 10px; color: #555555;">
            * Resit ini dijana secara automatik menerusi sistem komputer. Tiada tandatangan fizikal diperlukan.
        </div>

        <div class="footer-text">
            Terima kasih atas sumbangan ikhlas anda. Hubungi Urusetia WARGA CAKNA (+6019-615 7606) untuk sebarang pertanyaan.
        </div>
    </div>
</body>
</html><?php /**PATH C:\xampp\htdocs\sistem-resit-dana\resources\views/pdf/donation-receipt.blade.php ENDPATH**/ ?>