<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Product removal of the Approval matrix engine.
 * Non-reversible: down() is intentionally empty (dev-stage clean cut).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('approval_instance_step_approvers');
        Schema::dropIfExists('approval_delegation_logs');
        Schema::dropIfExists('approval_delegations');
        Schema::dropIfExists('approval_logs');
        Schema::dropIfExists('approval_instance_steps');
        Schema::dropIfExists('approval_instances');
        Schema::dropIfExists('approval_workflow_step_approvers');
        Schema::dropIfExists('approval_workflow_steps');
        Schema::dropIfExists('approval_workflows');
    }

    public function down(): void
    {
        // Non-reversible product removal — recreate via historical migrations if needed.
    }
};
