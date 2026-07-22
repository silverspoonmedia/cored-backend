<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vtual_content_appearances', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('content_id')->constrained('vtual_contents')->cascadeOnDelete();
            $table->foreignUuid('channel_id')->constrained('vtual_channels')->cascadeOnDelete();
            // Denormalized from channel for creator feed queries (primary or appearance).
            $table->foreignUuid('creator_id')->constrained('vtual_creators')->cascadeOnDelete();
            $table->string('role', 32); // primary|collaborator|network
            $table->timestamps();

            $table->unique(['content_id', 'channel_id'], 'vtual_content_appear_content_channel_uq');
            $table->index(['channel_id', 'role'], 'vtual_content_appear_channel_role_idx');
            $table->index(['creator_id', 'role'], 'vtual_content_appear_creator_role_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vtual_content_appearances');
    }
};
