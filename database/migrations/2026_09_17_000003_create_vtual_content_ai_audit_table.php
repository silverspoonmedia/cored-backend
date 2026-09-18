<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vtual_content_ai_audit', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('content_id')->constrained('vtual_contents')->cascadeOnDelete();
            $table->string('status', 32);
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('requested_at');
            $table->timestamp('processed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            // One pending/done audit row per content — latest wins.
            $table->index(['content_id', 'status'], 'vtual_ai_audit_content_status_idx');
            $table->index(['status', 'requested_at'], 'vtual_ai_audit_status_req_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vtual_content_ai_audit');
    }
};
