<?php

namespace App\Mail;

use App\Models\PaymentVoucher;
use App\Models\VoucherAcknowledgement;
use App\Services\VoucherAcknowledgementPdfService;
use App\Services\VoucherAcknowledgementUrlService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
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
        public ?VoucherAcknowledgement $acknowledgement = null,
        public ?string $audience = null,
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
        $acknowledgementUrl = $this->event === 'payment_confirmed' && $this->acknowledgementToken
            ? app(VoucherAcknowledgementUrlService::class)->forToken($this->acknowledgementToken)
            : null;

        return new Content(
            view: 'emails.voucher-notification',
            with: ['acknowledgementUrl' => $acknowledgementUrl],
        );
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        if ($this->event !== 'acknowledgement_completed'
            || ! $this->acknowledgement
            || $this->acknowledgement->used_at === null) {
            return [];
        }

        $acknowledgement = $this->acknowledgement;
        $documents = app(VoucherAcknowledgementPdfService::class);

        return [
            Attachment::fromData(
                fn () => app(VoucherAcknowledgementPdfService::class)->render($acknowledgement),
                $documents->filename($acknowledgement),
            )->withMime('application/pdf'),
        ];
    }
}
