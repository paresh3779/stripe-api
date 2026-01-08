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

class InvoiceCreatedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Invoice $invoice
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New Invoice - ' . ($this->invoice->number ?? 'INV-' . strtoupper(substr($this->invoice->id, 0, 8))),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invoice.created',
            with: [
                'invoice' => $this->invoice,
                'user' => $this->invoice->user,
                'subscription' => $this->invoice->subscription,
                'amount' => number_format($this->invoice->total / 100, 2),
                'currency' => strtoupper($this->invoice->currency),
                'invoiceNumber' => $this->invoice->number ?? 'INV-' . strtoupper(substr($this->invoice->id, 0, 8)),
                'dueDate' => $this->invoice->due_date?->format('F j, Y') ?? 'Upon Receipt',
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
