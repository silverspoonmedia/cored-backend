<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('instance_id')->constrained('approval_instances')->cascadeOnDelete();
            $table->foreignUuid('instance_step_id')->nullable()->constrained('approval_instance_steps')->cascadeOnDelete();
            $table->enum('action', [
                'SUBMITTED', 'APPROVED', 'REJECTED', 'DELEGATED', 
                'CANCELLED', 'PERMANENT_TRANSFER', 'REASSIGNED', 'ESCALATED'
            ]);
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('target_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['instance_id', 'created_at']);
            $table->index(['actor_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_logs');
    }
};
