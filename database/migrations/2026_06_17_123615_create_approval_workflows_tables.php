<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_workflows', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('document_type')->comment('MR, PR, PO, BOQ_REVISION, GR, ORDER, etc.');
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->text('locked_reason')->nullable();
            $table->foreignUuid('superseded_by')->nullable()->constrained('approval_workflows')->nullOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['document_type', 'is_active'], 'idx_workflow_document_active');
            $table->index('is_active');
        });

        Schema::create('approval_workflow_steps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workflow_id')->constrained('approval_workflows')->cascadeOnDelete();
            $table->integer('step_order');
            $table->string('step_name');
            $table->json('condition')->nullable()->comment('Dynamic activation rules');
            $table->enum('parallel_mode', ['ANY_REJECT', 'UNANIMOUS', 'MAJORITY_VOTE'])->default('UNANIMOUS');
            $table->enum('reject_action', ['STOP', 'BACK_TO_STEP'])->default('STOP');
            $table->integer('reject_target_step_order')->nullable();
            $table->boolean('reject_reset_downstream')->default(true);
            $table->string('reject_set_doc_status')->nullable();
            $table->string('approve_set_doc_status')->nullable();
            $table->integer('reminder_after_days')->default(2);
            $table->integer('escalate_after_days')->nullable();
            $table->string('escalate_to_role')->nullable();
            $table->boolean('allow_reassignment')->default(false);
            $table->timestamps();

            $table->unique(['workflow_id', 'step_order']);
            $table->index('workflow_id');
        });

        Schema::create('approval_workflow_step_approvers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workflow_step_id')->constrained('approval_workflow_steps')->cascadeOnDelete();
            $table->string('required_role')->comment('Must match roles.name from Spatie');
            $table->string('slot_label')->comment('E.g., "Finance Manager", "Technical Reviewer"');
            $table->timestamps();

            $table->index('workflow_step_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_workflow_step_approvers');
        Schema::dropIfExists('approval_workflow_steps');
        Schema::dropIfExists('approval_workflows');
    }
};
