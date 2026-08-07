<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vtual_creator_claim_verify_pool', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('claim_id')->constrained('vtual_creator_claims')->cascadeOnDelete();
            $table->string('status', 32);
            $table->timestamp('requested_at');
            $table->timestamp('processed_at')->nullable();
            $table->string('error', 2000)->nullable();
            $table->timestamps();

            $table->index(['status', 'requested_at'], 'vtual_claim_verify_pool_status_requested_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vtual_creator_claim_verify_pool');
    }
};
