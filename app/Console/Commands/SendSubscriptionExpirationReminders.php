<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\SubscriptionExpirationReminderMail;
use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendSubscriptionExpirationReminders extends Command
{
    protected $signature = 'subscriptions:send-expiration-reminders';

    protected $description = 'Send reminder emails for subscriptions expiring (cancelled) in 7, 3, 1 days';

    public function handle(): int
    {
        $this->info('Checking for subscriptions expiring soon...');

        $reminderDays = [7, 3, 1]; // Send reminders at 7, 3, and 1 day before expiration

        foreach ($reminderDays as $days) {
            $this->sendRemindersForDay($days);
        }

        $this->info('Subscription expiration reminders completed.');
        return Command::SUCCESS;
    }

    private function sendRemindersForDay(int $days): void
    {
        $targetDate = now()->addDays($days)->startOfDay();
        $endOfTargetDate = now()->addDays($days)->endOfDay();

        // Find subscriptions that are set to cancel at period end
        $subscriptions = Subscription::query()
            ->where('cancel_at_period_end', true)
            ->whereBetween('current_period_end', [$targetDate, $endOfTargetDate])
            ->whereIn('status', ['active', 'trialing'])
            ->with(['user', 'product'])
            ->get();

        $count = 0;
        foreach ($subscriptions as $subscription) {
            try {
                $user = $subscription->user;
                if ($user && $user->email) {
                    Mail::to($user->email)->queue(new SubscriptionExpirationReminderMail($subscription, $days));
                    $count++;

                    Log::info('Subscription expiration reminder sent', [
                        'subscription_id' => $subscription->id,
                        'user_id' => $user->id,
                        'days_remaining' => $days,
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Failed to send subscription expiration reminder', [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Sent {$count} reminder(s) for subscriptions expiring in {$days} day(s).");
    }
}
