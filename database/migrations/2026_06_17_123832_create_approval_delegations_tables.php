<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_delegations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('delegator_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('delegate_id')->constrained('users')->cascadeOnDelete();
            $table->enum('scope', ['ALL', 'DOCUMENT_TYPE', 'INSTANCE']);
            $table->string('document_type')->nullable();
            $table->foreignUuid('instance_id')->nullable()->constrained('approval_instances')->cascadeOnDelete();
            $table->timestamp('valid_from');
            $table->timestamp('valid_until');
            $table->text('reason');
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignUuid('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['delegate_id', 'is_active', 'valid_from', 'valid_until'], 'idx_deleg_active');
            $table->index(['is_active', 'valid_until'], 'idx_deleg_expiry');
        });

        Schema::create('approval_delegation_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('delegation_id')->constrained('approval_delegations')->cascadeOnDelete();
            $table->enum('action', ['CREATED', 'REVOKED', 'EXPIRED']);
            $table->foreignUuid('performed_by')->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['delegation_id', 'created_at'], 'idx_deleg_log_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_delegation_logs');
        Schema::dropIfExists('approval_delegations');
    }
};
