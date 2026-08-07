<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vtual_channels', function (Blueprint $table): void {
            $table->timestamp('feed_last_polled_at')->nullable()->after('last_synced_at');
            $table->timestamp('feed_last_entry_updated_at')->nullable()->after('feed_last_polled_at');
        });

        Schema::create('vtual_content_fetch_pool', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('platform_id')->constrained('vtual_platforms')->restrictOnDelete();
            $table->string('video_external_id');
            $table->foreignUuid('channel_id')->constrained('vtual_channels')->restrictOnDelete();
            $table->string('source', 32);
            $table->string('status', 32);
            $table->timestamp('requested_at');
            $table->timestamp('processed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['platform_id', 'status', 'requested_at'], 'vtual_ct_pool_plat_status_req_idx');
            $table->index(['platform_id', 'video_external_id', 'status'], 'vtual_ct_pool_plat_vid_status_idx');
            $table->index(['channel_id', 'status'], 'vtual_ct_pool_channel_status_idx');
        });

        Schema::create('vtual_websub_subscriptions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('channel_id')->unique()->constrained('vtual_channels')->cascadeOnDelete();
            $table->string('topic');
            $table->string('callback_url');
            $table->string('secret', 128);
            $table->string('status', 32);
            $table->timestamp('lease_expires_at')->nullable();
            $table->timestamp('subscribed_at')->nullable();
            $table->timestamp('last_notify_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['status', 'lease_expires_at'], 'vtual_websub_status_lease_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vtual_websub_subscriptions');
        Schema::dropIfExists('vtual_content_fetch_pool');

        Schema::table('vtual_channels', function (Blueprint $table): void {
            $table->dropColumn(['feed_last_polled_at', 'feed_last_entry_updated_at']);
        });
    }
};
