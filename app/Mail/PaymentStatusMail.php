<?php

namespace App\Mail;

use App\Models\DonationReceipt;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public const MANUAL_RECEIVED = 'manual_received';

    public const PAYMENT_COMPLETED = 'payment_completed';

    public const PAYMENT_REJECTED = 'payment_rejected';

    public function __construct(
        public DonationReceipt $receipt,
        public string $event,
        public string $audience,
    ) {}

    public function envelope(): Envelope
    {
        $subject = match ($this->event) {
            self::MANUAL_RECEIVED => $this->audience === 'admin'
                ? "Bayaran manual menunggu pengesahan [{$this->receipt->receipt_no}]"
                : "Sumbangan diterima untuk pengesahan [{$this->receipt->receipt_no}]",
            self::PAYMENT_COMPLETED => "Rekod bayaran berjaya [{$this->receipt->receipt_no}]",
            self::PAYMENT_REJECTED => "Bayaran ditolak [{$this->receipt->receipt_no}]",
            default => "Status bayaran [{$this->receipt->receipt_no}]",
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.payment-status',
            with: [
                'receipt' => $this->receipt,
                'event' => $this->event,
                'audience' => $this->audience,
            ],
        );
    }
}
