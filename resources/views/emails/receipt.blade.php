<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Resit Sumbangan WARGA CAKNA</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f4f6f8; margin: 0; padding: 20px;">
    <table align="center" width="600" style="background-color: #ffffff; border-radius: 8px; overflow: hidden; border: 1px solid #e5e7eb; border-collapse: collapse;">
        <!-- HEADER EMEL & LOGO -->
        <tr style="background-color: #046a38; text-align: center;">
            <td style="padding: 25px;">
                <img src="https://dana.wargacakna.org/images/logo-warga-cakna.png" alt="WARGA CAKNA" style="max-height: 70px; width: auto; margin-bottom: 10px;">
                <h2 style="color: #ffffff; margin: 0; font-size: 18px; font-weight: bold; text-transform: uppercase;">
                    PERSATUAN PRIHATIN WARGA TERPINGGIR TERENGGANU
                </h2>
                <p style="color: #dcfce7; margin: 5px 0 0 0; font-size: 12px;">(WARGA CAKNA) | Reg No: PPM-005-11-12052016</p>
            </td>
        </tr>

        <!-- KANDUNGAN UTAMA EMEL -->
        <tr>
            <td style="padding: 30px; color: #333333; font-size: 14px; line-height: 1.6;">
                <p style="margin-top: 0;">Salam Sejahtera <strong>{{ $receipt->donor_name }}</strong>,</p>
                <p>Terima kasih atas sumbangan murni anda kepada Tabung Kebajikan WARGA CAKNA. Sumbangan anda telah selamat diterima.</p>

                <!-- KOTAK BUTIRAN RESIT -->
                <table width="100%" style="background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; margin: 20px 0; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 10px 15px; font-weight: bold; border-bottom: 1px solid #e5e7eb; color: #555555; width: 40%;">No. Resit:</td>
                        <td style="padding: 10px 15px; border-bottom: 1px solid #e5e7eb; font-weight: bold; color: #046a38;">{{ $receipt->receipt_no }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 10px 15px; font-weight: bold; border-bottom: 1px solid #e5e7eb; color: #555555;">Tarikh:</td>
                        <td style="padding: 10px 15px; border-bottom: 1px solid #e5e7eb;">{{ \Carbon\Carbon::parse($receipt->donated_at)->format('d/m/Y h:i A') }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 10px 15px; font-weight: bold; border-bottom: 1px solid #e5e7eb; color: #555555;">Jumlah Sumbangan:</td>
                        <td style="padding: 10px 15px; border-bottom: 1px solid #e5e7eb; font-weight: bold; color: #046a38; font-size: 16px;">RM {{ number_format($receipt->amount, 2) }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 10px 15px; font-weight: bold; color: #555555;">Tujuan / Catatan:</td>
                        <td style="padding: 10px 15px;">{{ $receipt->description ?? 'Sumbangan Tabung Kebajikan WARGA CAKNA' }}</td>
                    </tr>
                </table>

                <p style="font-size: 13px; color: #4b5563;">
                    * Resit rasmi berbentuk fail <strong>PDF</strong> telah dilampirkan bersama e-mel ini untuk simpanan atau rujukan anda.
                </p>

                <div style="text-align: center; margin-top: 25px;">
                    <a href="https://dana.wargacakna.org/receipts/{{ $receipt->id }}/view-pdf" target="_blank" style="background-color: #046a38; color: #ffffff; text-decoration: none; padding: 12px 25px; border-radius: 5px; font-weight: bold; font-size: 13px; display: inline-block;">
                        Lihat / Muat Turun Resit PDF
                    </a>
                </div>
            </td>
        </tr>

        <!-- FOOTER EMEL -->
        <tr style="background-color: #f3f4f6; text-align: center; border-top: 1px solid #e5e7eb;">
            <td style="padding: 15px; font-size: 11px; color: #6b7280;">
                Urusetia WARGA CAKNA | B17, Taman Kirana, 21700 Kuala Berang, Terengganu<br>
                Hubungi Kami: +6019-615 7606 | Emel: wargacaknatrg@gmail.com
            </td>
        </tr>
    </table>
</body>
</html>