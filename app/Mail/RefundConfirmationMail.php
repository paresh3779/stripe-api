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

class RefundConfirmationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Payment $payment,
        public readonly int $refundAmount,
        public readonly bool $isFullRefund = true
    ) {}

    public function envelope(): Envelope
    {
        $type = $this->isFullRefund ? 'Full' : 'Partial';
        return new Envelope(
            subject: "{$type} Refund Confirmation - " . config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.payment.refund',
            with: [
                'payment' => $this->payment,
                'user' => $this->payment->user,
                'product' => $this->payment->product,
                'originalAmount' => number_format($this->payment->amount / 100, 2),
                'refundAmount' => number_format($this->refundAmount / 100, 2),
                'currency' => strtoupper($this->payment->currency),
                'isFullRefund' => $this->isFullRefund,
                'transactionId' => $this->payment->stripe_payment_intent_id ?? $this->payment->id,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
