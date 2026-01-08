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

class DisputeNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Payment $payment,
        public readonly string $disputeReason,
        public readonly int $disputeAmount
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Payment Dispute Notification - ' . config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.payment.dispute',
            with: [
                'payment' => $this->payment,
                'user' => $this->payment->user,
                'product' => $this->payment->product,
                'disputeReason' => $this->formatDisputeReason($this->disputeReason),
                'disputeAmount' => number_format($this->disputeAmount / 100, 2),
                'currency' => strtoupper($this->payment->currency),
                'transactionId' => $this->payment->stripe_payment_intent_id ?? $this->payment->id,
            ],
        );
    }

    private function formatDisputeReason(string $reason): string
    {
        $reasons = [
            'duplicate' => 'Duplicate charge',
            'fraudulent' => 'Fraudulent transaction',
            'subscription_canceled' => 'Subscription was canceled',
            'product_unacceptable' => 'Product unacceptable',
            'product_not_received' => 'Product not received',
            'unrecognized' => 'Unrecognized charge',
            'credit_not_processed' => 'Credit not processed',
            'general' => 'General dispute',
        ];

        return $reasons[$reason] ?? ucfirst(str_replace('_', ' ', $reason));
    }

    public function attachments(): array
    {
        return [];
    }
}
