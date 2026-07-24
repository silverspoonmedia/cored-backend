<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vtual_creator_claims', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('creator_id')->constrained('vtual_creators')->cascadeOnDelete();
            $table->foreignUuid('channel_id')->constrained('vtual_channels')->cascadeOnDelete();
            $table->string('token', 64);
            $table->string('status', 32); // pending|verified|cancelled|expired
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->string('verified_via', 32)->nullable(); // bio|accept_anyway
            $table->foreignUuid('verified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'status'], 'vtual_creator_claims_user_status_idx');
            $table->index(['creator_id', 'status'], 'vtual_creator_claims_creator_status_idx');
            $table->index('token', 'vtual_creator_claims_token_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vtual_creator_claims');
    }
};
