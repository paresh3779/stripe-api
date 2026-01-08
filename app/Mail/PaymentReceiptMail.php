<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentReceiptMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Payment $payment
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Payment Receipt - ' . config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.payment.receipt',
            with: [
                'payment' => $this->payment,
                'user' => $this->payment->user,
                'product' => $this->payment->product,
                'amount' => number_format($this->payment->amount / 100, 2),
                'currency' => strtoupper($this->payment->currency),
                'paidAt' => $this->payment->paid_at?->format('F j, Y \a\t g:i A'),
                'transactionId' => $this->payment->stripe_payment_intent_id ?? $this->payment->id,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
