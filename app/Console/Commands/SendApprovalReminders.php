<?php

namespace App\Console\Commands;

use App\Models\ApprovalInstanceStep;
use App\Notifications\Approval\ApprovalReminderNotification;
use Illuminate\Console\Command;

class SendApprovalReminders extends Command
{
    protected $signature = 'approval:send-reminders';

    protected $description = 'Send SLA overdue reminders to approvers';

    public function handle(): int
    {
        $steps = ApprovalInstanceStep::where('status', 'IN_PROGRESS')
            ->whereNotNull('sla_target_date')
            ->where('sla_target_date', '<', now())
            ->whereNull('first_reminder_sent_at')
            ->with(['approvers', 'workflowStep'])
            ->get();

        foreach ($steps as $step) {
            foreach ($step->approvers as $slot) {
                if ($slot->status !== 'WAITING') {
                    continue;
                }

                if ($slot->assignedUser) {
                    $slot->assignedUser->notify(
                        new ApprovalReminderNotification($slot)
                    );
                }
            }

            $step->update(['first_reminder_sent_at' => now()]);
        }

        $this->info("Sent reminders for {$steps->count()} overdue steps.");

        return Command::SUCCESS;
    }
}
