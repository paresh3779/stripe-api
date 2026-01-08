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

class TrialEndingReminderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Subscription $subscription,
        public readonly int $daysRemaining
    ) {}

    public function envelope(): Envelope
    {
        $urgency = $this->daysRemaining <= 1 ? '⚠️ ' : '';
        return new Envelope(
            subject: "{$urgency}Your free trial ends in {$this->daysRemaining} day" . ($this->daysRemaining > 1 ? 's' : ''),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.subscription.trial-ending',
            with: [
                'subscription' => $this->subscription,
                'user' => $this->subscription->user,
                'product' => $this->subscription->product,
                'daysRemaining' => $this->daysRemaining,
                'trialEndDate' => $this->subscription->trial_end?->format('F j, Y'),
                'amount' => number_format($this->subscription->amount / 100, 2),
                'currency' => strtoupper($this->subscription->currency),
                'interval' => $this->subscription->billing_interval,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
