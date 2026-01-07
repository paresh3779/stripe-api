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

class SubscriptionExpirationReminderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Subscription $subscription,
        public readonly int $daysRemaining
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->daysRemaining === 1 
            ? 'Your Subscription Expires Tomorrow'
            : "Your Subscription Expires in {$this->daysRemaining} Days";

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.subscription.expiration-reminder',
            with: [
                'subscription' => $this->subscription,
                'user' => $this->subscription->user,
                'product' => $this->subscription->product,
                'daysRemaining' => $this->daysRemaining,
                'expirationDate' => $this->subscription->current_period_end?->format('F j, Y'),
                'renewalAmount' => number_format($this->subscription->amount / 100, 2),
                'currency' => strtoupper($this->subscription->currency),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
