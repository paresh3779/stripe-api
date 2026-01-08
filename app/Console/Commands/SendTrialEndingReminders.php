<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\TrialEndingReminderMail;
use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendTrialEndingReminders extends Command
{
    protected $signature = 'subscriptions:send-trial-reminders';

    protected $description = 'Send reminder emails for subscriptions with trials ending in 3, 1 days';

    public function handle(): int
    {
        $this->info('Checking for trial subscriptions ending soon...');

        $reminderDays = [3, 1]; // Send reminders at 3 days and 1 day before trial ends

        foreach ($reminderDays as $days) {
            $this->sendRemindersForDay($days);
        }

        $this->info('Trial ending reminders completed.');
        return Command::SUCCESS;
    }

    private function sendRemindersForDay(int $days): void
    {
        $targetDate = now()->addDays($days)->startOfDay();
        $endOfTargetDate = now()->addDays($days)->endOfDay();

        $subscriptions = Subscription::query()
            ->whereNotNull('trial_end')
            ->whereBetween('trial_end', [$targetDate, $endOfTargetDate])
            ->where('status', 'trialing')
            ->where('cancel_at_period_end', false)
            ->with(['user', 'product'])
            ->get();

        $count = 0;
        foreach ($subscriptions as $subscription) {
            try {
                $user = $subscription->user;
                if ($user && $user->email) {
                    Mail::to($user->email)->queue(new TrialEndingReminderMail($subscription, $days));
                    $count++;

                    Log::info('Trial ending reminder sent', [
                        'subscription_id' => $subscription->id,
                        'user_id' => $user->id,
                        'days_remaining' => $days,
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Failed to send trial ending reminder', [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Sent {$count} reminder(s) for trials ending in {$days} day(s).");
    }
}
