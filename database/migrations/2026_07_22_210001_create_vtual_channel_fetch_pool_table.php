<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vtual_channel_fetch_pool', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('platform_id')->constrained('vtual_platforms')->restrictOnDelete();
            $table->string('external_id');
            $table->foreignUuid('channel_id')->nullable()->constrained('vtual_channels')->nullOnDelete();
            $table->string('reason', 32); // refresh|scheduled_resync|manual
            $table->string('status', 32); // pending|processing|done|failed
            $table->timestamp('requested_at');
            $table->timestamp('processed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['platform_id', 'status', 'requested_at'], 'vtual_ch_pool_plat_status_req_idx');
            $table->index(['platform_id', 'external_id', 'status'], 'vtual_ch_pool_plat_ext_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vtual_channel_fetch_pool');
    }
};
