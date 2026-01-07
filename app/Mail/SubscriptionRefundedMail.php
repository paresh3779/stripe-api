<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionRefundedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Subscription $subscription,
        public readonly array $refundDetails
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Subscription Refund Has Been Processed',
        );
    }

    public function content(): Content
    {
        $refundAmount = isset($this->refundDetails['amount']) 
            ? number_format($this->refundDetails['amount'] / 100, 2) 
            : '0.00';

        return new Content(
            view: 'emails.subscription.refunded',
            with: [
                'subscription' => $this->subscription,
                'user' => $this->subscription->user,
                'product' => $this->subscription->product,
                'refundAmount' => $refundAmount,
                'currency' => strtoupper($this->refundDetails['currency'] ?? $this->subscription->currency),
                'refundId' => $this->refundDetails['refund_id'] ?? null,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
