<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Payment;
use App\Models\StripeWebhookEvent;
use App\Constants\PaymentStatus;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use Stripe\Checkout\Session;

class ReconcileStripePayments extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'stripe:reconcile 
                            {--days=7 : Number of days to look back}
                            {--fix : Actually fix discrepancies (default is dry-run)}';

    /**
     * The console command description.
     */
    protected $description = 'Reconcile local payment records with Stripe to find and fix discrepancies';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        Stripe::setApiKey(config('stripe.secret'));

        $days = (int) $this->option('days');
        $dryRun = !$this->option('fix');

        $this->info("Reconciling payments from the last {$days} days...");
        
        if ($dryRun) {
            $this->warn('Running in DRY-RUN mode. Use --fix to actually make changes.');
        }

        $this->newLine();

        // 1. Find payments with pending/processing status that might have completed
        $this->reconcilePendingPayments($days, $dryRun);

        // 2. Find failed webhook events that need retry
        $this->reconcileFailedWebhookEvents($dryRun);

        // 3. Find orphaned checkout sessions (paid but no local record)
        $this->reconcileOrphanedSessions($days, $dryRun);

        $this->newLine();
        $this->info('Reconciliation complete.');

        return Command::SUCCESS;
    }

    /**
     * Reconcile payments stuck in pending/processing status
     */
    private function reconcilePendingPayments(int $days, bool $dryRun): void
    {
        $this->info('Checking pending/processing payments...');

        $pendingPayments = Payment::whereIn('status', [
                PaymentStatus::PENDING,
                PaymentStatus::PROCESSING,
                PaymentStatus::REQUIRES_ACTION,
            ])
            ->where('created_at', '>=', now()->subDays($days))
            ->whereNotNull('stripe_payment_intent_id')
            ->get();

        $this->info("Found {$pendingPayments->count()} pending payments to check.");

        $fixed = 0;
        $discrepancies = [];

        foreach ($pendingPayments as $payment) {
            try {
                $paymentIntent = PaymentIntent::retrieve($payment->stripe_payment_intent_id);

                $stripeStatus = $this->mapStripeStatus($paymentIntent->status);

                if ($stripeStatus !== $payment->status) {
                    $discrepancies[] = [
                        'payment_id' => $payment->id,
                        'stripe_pi_id' => $payment->stripe_payment_intent_id,
                        'local_status' => $payment->status,
                        'stripe_status' => $stripeStatus,
                    ];

                    if (!$dryRun) {
                        $payment->update([
                            'status' => $stripeStatus,
                            'updated_at' => now(),
                        ]);
                        $fixed++;
                    }
                }
            } catch (\Exception $e) {
                $this->error("Error checking payment {$payment->id}: {$e->getMessage()}");
            }
        }

        if (count($discrepancies) > 0) {
            $this->warn("Found " . count($discrepancies) . " status discrepancies:");
            $this->table(
                ['Payment ID', 'Stripe PI ID', 'Local Status', 'Stripe Status'],
                $discrepancies
            );

            if (!$dryRun) {
                $this->info("Fixed {$fixed} payment records.");
            }
        } else {
            $this->info("No discrepancies found.");
        }
    }

    /**
     * Retry failed webhook events
     */
    private function reconcileFailedWebhookEvents(bool $dryRun): void
    {
        $this->newLine();
        $this->info('Checking failed webhook events...');

        $failedEvents = StripeWebhookEvent::retryable(3)
            ->where('created_at', '>=', now()->subDays(3))
            ->get();

        $this->info("Found {$failedEvents->count()} failed webhook events.");

        if ($failedEvents->count() > 0) {
            $this->table(
                ['Event ID', 'Type', 'Error', 'Retry Count', 'Created At'],
                $failedEvents->map(fn($e) => [
                    $e->stripe_event_id,
                    $e->event_type,
                    \Str::limit($e->error_message, 50),
                    $e->retry_count,
                    $e->created_at->format('Y-m-d H:i'),
                ])
            );

            if (!$dryRun) {
                $this->warn('Manual retry of failed events is recommended via Stripe Dashboard.');
            }
        }
    }

    /**
     * Find checkout sessions that were paid but have no local payment record
     */
    private function reconcileOrphanedSessions(int $days, bool $dryRun): void
    {
        $this->newLine();
        $this->info('Checking for orphaned checkout sessions...');

        try {
            $sessions = Session::all([
                'created' => [
                    'gte' => now()->subDays($days)->timestamp,
                ],
                'status' => 'complete',
                'limit' => 100,
            ]);

            $orphaned = [];

            foreach ($sessions->data as $session) {
                if ($session->payment_status === 'paid' && $session->payment_intent) {
                    // Check if we have a local payment record
                    $localPayment = Payment::where('stripe_payment_intent_id', $session->payment_intent)->first();

                    if (!$localPayment) {
                        $orphaned[] = [
                            'session_id' => $session->id,
                            'payment_intent' => $session->payment_intent,
                            'amount' => $session->amount_total / 100,
                            'currency' => strtoupper($session->currency),
                            'customer' => $session->customer ?? 'N/A',
                            'user_id' => $session->metadata->user_id ?? 'N/A',
                        ];
                    }
                }
            }

            if (count($orphaned) > 0) {
                $this->error("Found " . count($orphaned) . " orphaned sessions (paid but no local record):");
                $this->table(
                    ['Session ID', 'Payment Intent', 'Amount', 'Currency', 'Customer', 'User ID'],
                    $orphaned
                );

                $this->warn('These payments were successful but may not have been recorded locally.');
                $this->warn('Review and create payment records manually if needed.');
            } else {
                $this->info("No orphaned sessions found.");
            }

        } catch (\Exception $e) {
            $this->error("Error fetching sessions: {$e->getMessage()}");
        }
    }

    /**
     * Map Stripe PaymentIntent status to local PaymentStatus
     */
    private function mapStripeStatus(string $stripeStatus): string
    {
        return match ($stripeStatus) {
            'succeeded' => PaymentStatus::SUCCEEDED,
            'processing' => PaymentStatus::PROCESSING,
            'requires_action' => PaymentStatus::REQUIRES_ACTION,
            'requires_payment_method' => PaymentStatus::REQUIRES_PAYMENT_METHOD,
            'canceled' => PaymentStatus::CANCELLED,
            'requires_capture' => PaymentStatus::PENDING,
            default => PaymentStatus::PENDING,
        };
    }
}
