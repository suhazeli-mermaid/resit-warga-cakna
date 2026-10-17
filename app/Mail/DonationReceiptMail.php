<?php

namespace App\Mail;

use App\Models\DonationReceipt;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DonationReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Instance resit sumbangan.
     *
     * @var \App\Models\DonationReceipt
     */
    public $receipt;

    /**
     * Cipta instance mesej baharu.
     *
     * @param  \App\Models\DonationReceipt  $receipt
     * @return void
     */
    public function __construct(DonationReceipt $receipt)
    {
        $this->receipt = $receipt;
    }

    /**
     * Dapatkan sampul surat mesej (Envelope).
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Resit Sumbangan WARGA CAKNA [{$this->receipt->receipt_no}]",
        );
    }

    /**
     * Dapatkan definisi kandungan mesej (Content).
     */
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
     * Dapatkan lampiran untuk mesej (Attachments).
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }

    /**
     * Sokongan kaedah build() legacy untuk versi Laravel terdahulu.
     */
    public function build()
    {
        return $this->subject("Resit Sumbangan WARGA CAKNA [{$this->receipt->receipt_no}]")
                    ->view('emails.receipt');
    }
}