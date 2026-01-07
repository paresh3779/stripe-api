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

class InvoicePaidMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Invoice $invoice
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Payment Receipt - Invoice #' . ($this->invoice->number ?? $this->invoice->id),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invoice.paid',
            with: [
                'invoice' => $this->invoice,
                'user' => $this->invoice->user,
                'subscription' => $this->invoice->subscription,
                'amount' => number_format($this->invoice->total / 100, 2),
                'currency' => strtoupper($this->invoice->currency),
                'invoiceNumber' => $this->invoice->number,
                'paidAt' => $this->invoice->paid_at?->format('F j, Y'),
                'invoicePdfUrl' => $this->invoice->invoice_pdf,
                'hostedInvoiceUrl' => $this->invoice->hosted_invoice_url,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
