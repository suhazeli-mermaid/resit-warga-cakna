<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Status Bayaran WARGA CAKNA</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f4f6f8; margin: 0; padding: 20px; color: #1f2937;">
    <table align="center" width="600" style="background-color: #ffffff; border: 1px solid #e5e7eb; border-collapse: collapse;">
        <tr style="background-color: #046a38; color: #ffffff; text-align: center;">
            <td style="padding: 22px;">
                <h2 style="margin: 0; font-size: 19px;">WARGA CAKNA</h2>
            </td>
        </tr>
        <tr>
            <td style="padding: 28px; font-size: 14px; line-height: 1.6;">
                @if($audience === 'admin')
                    <p>Salam Admin,</p>
                @else
                    <p>Salam Sejahtera <strong>{{ $receipt->donor_name }}</strong>,</p>
                @endif

                @if($event === \App\Mail\PaymentStatusMail::MANUAL_RECEIVED)
                    @if($audience === 'admin')
                        <p>Transaksi manual baharu telah diterima dan memerlukan pengesahan Admin.</p>
                    @else
                        <p>Sumbangan anda telah direkodkan dan kini berstatus <strong>TUNGGU PENGESAHAN</strong>.</p>
                    @endif
                @elseif($event === \App\Mail\PaymentStatusMail::PAYMENT_REJECTED)
                    <p>Transaksi ini telah <strong>DITOLAK</strong>.</p>
                    <p><strong>Sebab:</strong> {{ $receipt->rejection_reason }}</p>
                @else
                    <p>Transaksi ini telah disahkan <strong>BERJAYA</strong>.</p>
                @endif

                <table width="100%" style="background-color: #f9fafb; border: 1px solid #e5e7eb; border-collapse: collapse; margin: 18px 0;">
                    <tr><td style="padding: 8px 12px; font-weight: bold;">No. Resit</td><td style="padding: 8px 12px;">{{ $receipt->receipt_no }}</td></tr>
                    <tr><td style="padding: 8px 12px; font-weight: bold;">Penderma</td><td style="padding: 8px 12px;">{{ $receipt->donor_name }}</td></tr>
                    <tr><td style="padding: 8px 12px; font-weight: bold;">Tarikh Sumbangan</td><td style="padding: 8px 12px;">{{ $receipt->donation_date_label }}</td></tr>
                    <tr><td style="padding: 8px 12px; font-weight: bold;">Jumlah</td><td style="padding: 8px 12px;">RM {{ number_format($receipt->amount, 2) }}</td></tr>
                    <tr><td style="padding: 8px 12px; font-weight: bold;">Kaedah</td><td style="padding: 8px 12px;">{{ strtoupper(str_replace('_', ' ', $receipt->payment_method)) }}</td></tr>
                    <tr><td style="padding: 8px 12px; font-weight: bold;">Status</td><td style="padding: 8px 12px;"><strong>{{ $receipt->status_label }}</strong></td></tr>
                    @if($receipt->payment_reference)
                        <tr><td style="padding: 8px 12px; font-weight: bold;">Rujukan</td><td style="padding: 8px 12px;">{{ $receipt->payment_reference }}</td></tr>
                    @endif
                </table>

                @if($audience === 'admin')
                    <p><a href="{{ route('receipts.show', $receipt->id) }}">Lihat rekod transaksi</a></p>
                @endif
            </td>
        </tr>
    </table>
</body>
</html>
