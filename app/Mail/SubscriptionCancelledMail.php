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

class SubscriptionCancelledMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Subscription $subscription
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Subscription Has Been Cancelled',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.subscription.cancelled',
            with: [
                'subscription' => $this->subscription,
                'user' => $this->subscription->user,
                'product' => $this->subscription->product,
                'cancelAtPeriodEnd' => $this->subscription->cancel_at_period_end,
                'accessUntil' => $this->subscription->current_period_end?->format('F j, Y'),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
