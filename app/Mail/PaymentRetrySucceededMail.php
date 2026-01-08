<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentRetrySucceededMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Invoice $invoice
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Payment Successful - Your subscription is active again',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invoice.retry-succeeded',
            with: [
                'invoice' => $this->invoice,
                'user' => $this->invoice->user,
                'subscription' => $this->invoice->subscription,
                'amount' => number_format($this->invoice->amount_paid / 100, 2),
                'currency' => strtoupper($this->invoice->currency),
                'invoiceNumber' => $this->invoice->number ?? 'INV-' . strtoupper(substr($this->invoice->id, 0, 8)),
                'paidAt' => $this->invoice->paid_at?->format('F j, Y'),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
