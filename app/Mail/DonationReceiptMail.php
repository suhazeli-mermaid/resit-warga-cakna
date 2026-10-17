<?php

namespace App\Mail;

use App\Models\DonationReceipt;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Barryvdh\DomPDF\Facade\Pdf;

class DonationReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public $receipt;

    public function __construct(DonationReceipt $receipt)
    {
        $this->receipt = $receipt;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Resit Sumbangan WARGA CAKNA [{$this->receipt->receipt_no}]",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.receipt',
            with: [
                'receipt' => $this->receipt,
            ],
        );
    }

    /**
     * Melampirkan fail PDF Resit secara automatik dalam emel.
     */
    public function attachments(): array
    {
        $pdf = Pdf::loadView('pdf.donation-receipt', ['receipt' => $this->receipt]);

        return [
            Attachment::fromData(fn () => $pdf->output(), "Resit-Sumbangan-{$this->receipt->receipt_no}.pdf")
                ->withMime('application/pdf'),
        ];
    }
}