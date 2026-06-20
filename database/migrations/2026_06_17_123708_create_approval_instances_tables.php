<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_instances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workflow_id')->constrained('approval_workflows')->restrictOnDelete();
            $table->integer('workflow_version');
            $table->string('document_type');
            $table->uuid('document_id');
            $table->enum('status', ['PENDING', 'APPROVED', 'REJECTED', 'CANCELLED'])->default('PENDING');
            $table->foreignUuid('submitted_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['document_type', 'document_id']);
            $table->index('status');
            $table->index(['document_type', 'document_id']);
        });

        Schema::create('approval_instance_steps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('instance_id')->constrained('approval_instances')->cascadeOnDelete();
            $table->foreignUuid('workflow_step_id')->constrained('approval_workflow_steps')->restrictOnDelete();
            $table->integer('step_order');
            $table->string('step_name');
            $table->enum('status', ['WAITING', 'IN_PROGRESS', 'APPROVED', 'REJECTED', 'SKIPPED'])->default('WAITING');
            $table->json('condition_evaluated')->nullable()->comment('Snapshot of evaluated condition for audit');
            $table->timestamp('sla_target_date')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('first_reminder_sent_at')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['instance_id', 'step_order']);
            $table->index('status');
            $table->index(['status', 'sla_target_date']);
        });

        Schema::create('approval_instance_step_approvers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('instance_step_id');
            $table->uuid('workflow_step_approver_id');
            $table->string('required_role');
            $table->string('slot_label');
            $table->enum('status', ['WAITING', 'APPROVED', 'REJECTED', 'CANCELLED', 'REASSIGNED'])->default('WAITING');
            $table->uuid('assigned_user_id')->nullable();
            $table->uuid('actor_id')->nullable();
            $table->uuid('delegated_by')->nullable();
            $table->uuid('delegation_id')->nullable();
            $table->uuid('reassigned_to')->nullable();
            $table->text('reassignment_reason')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('instance_step_id', 'fk_approver_inst_step')
                ->references('id')->on('approval_instance_steps')->cascadeOnDelete();
            $table->foreign('workflow_step_approver_id', 'fk_approver_wkfl_step')
                ->references('id')->on('approval_workflow_step_approvers')->restrictOnDelete();
            $table->foreign('assigned_user_id', 'fk_approver_assigned_user')
                ->references('id')->on('users')->nullOnDelete();
            $table->foreign('actor_id', 'fk_approver_actor')
                ->references('id')->on('users')->nullOnDelete();
            $table->foreign('delegated_by', 'fk_approver_delegated_by')
                ->references('id')->on('users')->nullOnDelete();
            $table->foreign('reassigned_to', 'fk_approver_reassigned_to')
                ->references('id')->on('users')->nullOnDelete();

            $table->index(['assigned_user_id', 'status']);
            $table->index('status');
            $table->index('instance_step_id');
            $table->index('delegation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_instance_step_approvers');
        Schema::dropIfExists('approval_instance_steps');
        Schema::dropIfExists('approval_instances');
    }
};
