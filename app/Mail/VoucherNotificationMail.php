<?php

namespace App\Mail;

use App\Models\PaymentVoucher;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VoucherNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public PaymentVoucher $voucher,
        public string $event,
        public ?string $acknowledgementToken = null,
    ) {}

    public function envelope(): Envelope
    {
        $subject = match ($this->event) {
            'issued' => "Baucar {$this->voucher->voucher_no} telah dikeluarkan",
            'payment_confirmed' => "Bayaran {$this->voucher->voucher_no} telah dibuat",
            'acknowledgement_completed' => "Akuan penerimaan {$this->voucher->voucher_no} selesai",
            default => "Makluman baucar {$this->voucher->voucher_no}",
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.voucher-notification');
    }
}
