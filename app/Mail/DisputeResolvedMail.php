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

class DisputeResolvedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Payment $payment,
        public readonly string $disputeStatus,
        public readonly int $disputeAmount
    ) {}

    public function envelope(): Envelope
    {
        $outcome = $this->disputeStatus === 'won' ? 'Resolved in Your Favor' : 'Dispute Update';
        return new Envelope(
            subject: "Payment Dispute {$outcome} - " . config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.payment.dispute-resolved',
            with: [
                'payment' => $this->payment,
                'user' => $this->payment->user,
                'disputeStatus' => $this->disputeStatus,
                'disputeAmount' => number_format($this->disputeAmount / 100, 2),
                'currency' => strtoupper($this->payment->currency),
                'isWon' => $this->disputeStatus === 'won',
                'transactionId' => $this->payment->stripe_payment_intent_id ?? $this->payment->id,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
