<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vtual_creator_claim_blocks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('creator_id')->constrained('vtual_creators')->cascadeOnDelete();
            $table->timestamp('blocked_until');
            $table->timestamps();

            $table->unique(['user_id', 'creator_id'], 'vtual_creator_claim_blocks_user_creator_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vtual_creator_claim_blocks');
    }
};
