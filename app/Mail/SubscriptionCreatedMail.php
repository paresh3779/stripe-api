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

class SubscriptionCreatedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Subscription $subscription
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to ' . ($this->subscription->product->name ?? 'Your Subscription'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.subscription.created',
            with: [
                'subscription' => $this->subscription,
                'user' => $this->subscription->user,
                'product' => $this->subscription->product,
                'price' => $this->subscription->price,
                'amount' => number_format($this->subscription->amount / 100, 2),
                'currency' => strtoupper($this->subscription->currency),
                'interval' => $this->subscription->billing_interval,
                'nextBillingDate' => $this->subscription->current_period_end?->format('F j, Y'),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
