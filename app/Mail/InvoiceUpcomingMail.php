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

class InvoiceUpcomingMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Subscription $subscription,
        public readonly int $upcomingAmount,
        public readonly string $billingDate
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Upcoming Invoice Reminder - ' . config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invoice.upcoming',
            with: [
                'subscription' => $this->subscription,
                'user' => $this->subscription->user,
                'product' => $this->subscription->product,
                'amount' => number_format($this->upcomingAmount / 100, 2),
                'currency' => strtoupper($this->subscription->currency),
                'billingDate' => $this->billingDate,
                'interval' => $this->subscription->billing_interval,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
