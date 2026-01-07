<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Invoice;
use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentFailedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Invoice $invoice,
        public readonly ?Subscription $subscription = null
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Payment Failed - Action Required',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invoice.payment-failed',
            with: [
                'invoice' => $this->invoice,
                'user' => $this->invoice->user,
                'subscription' => $this->subscription,
                'amount' => number_format($this->invoice->amount_due / 100, 2),
                'currency' => strtoupper($this->invoice->currency),
                'hostedInvoiceUrl' => $this->invoice->hosted_invoice_url,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
