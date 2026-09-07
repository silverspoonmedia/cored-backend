<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vtual_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('management_id')->nullable()->constrained('vtual_managements')->nullOnDelete();
            $table->foreignUuid('creator_id')->nullable()->constrained('vtual_creators')->nullOnDelete();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->string('url')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->string('timezone')->nullable();
            $table->string('status', 32);
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'starts_at'], 'vtual_events_status_starts_idx');
            $table->index('management_id', 'vtual_events_management_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vtual_events');
    }
};
