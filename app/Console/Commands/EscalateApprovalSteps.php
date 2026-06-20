<?php

namespace App\Console\Commands;

use App\Models\ApprovalInstanceStep;
use App\Models\User;
use App\Notifications\Approval\ApprovalEscalationNotification;
use Illuminate\Console\Command;

class EscalateApprovalSteps extends Command
{
    protected $signature = 'approval:escalate';
    protected $description = 'Escalate overdue approval steps to the escalation role';

    public function handle(): int
    {
        $now = now();

        $steps = ApprovalInstanceStep::where('status', 'IN_PROGRESS')
            ->whereNotNull('activated_at')
            ->whereNull('escalated_at')
            ->whereHas('workflowStep', function ($q) {
                $q->whereNotNull('escalate_after_days');
            })
            ->with(['workflowStep', 'approvers', 'instance'])
            ->get()
            ->filter(function ($step) use ($now) {
                $days = $step->workflowStep->escalate_after_days;
                return $step->activated_at->diffInDays($now) >= $days;
            });

        $escalatedCount = 0;

        foreach ($steps as $step) {
            $escalateTo = $step->workflowStep->escalate_to_role;

            if (! $escalateTo) {
                continue;
            }

            $escalationUsers = User::role($escalateTo)->get();

            foreach ($step->approvers()->where('status', 'WAITING')->get() as $slot) {
                foreach ($escalationUsers as $user) {
                    $user->notify(new ApprovalEscalationNotification($slot));
                }
            }

            $step->update(['escalated_at' => now()]);

            \App\Models\ApprovalLog::create([
                'instance_id' => $step->instance_id,
                'instance_step_id' => $step->id,
                'action' => 'ESCALATED',
                'actor_id' => null,
                'notes' => "Escalated to role: {$escalateTo}",
                'metadata' => [
                    'escalate_to_role' => $escalateTo,
                    'escalate_after_days' => $step->workflowStep->escalate_after_days,
                ],
            ]);

            $escalatedCount++;
        }

        $this->info("Escalated {$escalatedCount} steps.");

        return Command::SUCCESS;
    }
}