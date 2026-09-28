<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historical metric snapshots (decision: docs/domains/vtual/reference-metrics.md).
 *
 * `vtual_channels.subscriber_count` and `vtual_contents.concurrent_viewers` are
 * point-in-time columns — every refresh overwrites them, so they answer "what is
 * it now" and can never answer "what was it". These two append-only tables keep
 * one row per sampling bucket per subject, written by the snapshot writers that
 * already run (channel fetch-pool flush, channel activation, content hydrate).
 *
 * Deletion semantics differ from `vtual_incidents` on purpose: an incident
 * explains *why* a channel was deleted, so its FKs are nullOnDelete; a metric
 * row is a number about that channel and is meaningless without it, so the FKs
 * cascade. `creator_id` is denormalized for the "which creator grew" read and
 * nulls out if the creator row goes away.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vtual_channel_metrics', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('channel_id')->constrained('vtual_channels')->cascadeOnDelete();
            $table->foreignUuid('platform_id')->constrained('vtual_platforms')->cascadeOnDelete();
            $table->foreignUuid('creator_id')->nullable()->constrained('vtual_creators')->nullOnDelete();

            $table->unsignedBigInteger('subscriber_count')->nullable();

            // Start of the sampling bucket, not the wall clock of the write: the
            // unique key is what makes a burst of pushes collapse to one row.
            $table->timestamp('bucket_start');
            $table->timestamp('captured_at');

            // Append-only: no updated_at, and `created_at` is the only framework
            // column this table needs.
            $table->timestamp('created_at')->nullable();

            $table->unique(['channel_id', 'bucket_start'], 'vtual_channel_metrics_bucket_unique');
            $table->index('bucket_start', 'vtual_channel_metrics_bucket_idx');
            $table->index('creator_id', 'vtual_channel_metrics_creator_idx');
        });

        Schema::create('vtual_content_metrics', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('content_id')->constrained('vtual_contents')->cascadeOnDelete();
            $table->foreignUuid('channel_id')->constrained('vtual_channels')->cascadeOnDelete();
            $table->foreignUuid('platform_id')->constrained('vtual_platforms')->cascadeOnDelete();
            $table->foreignUuid('creator_id')->nullable()->constrained('vtual_creators')->nullOnDelete();

            // Concurrent viewers at capture time. Nullable: a platform can report
            // a live row without a viewer count, and "live with unknown CCV" is
            // itself a data point.
            $table->unsignedBigInteger('concurrent_viewers')->nullable();

            $table->timestamp('bucket_start');
            $table->timestamp('captured_at');
            $table->timestamp('created_at')->nullable();

            $table->unique(['content_id', 'bucket_start'], 'vtual_content_metrics_bucket_unique');
            $table->index('bucket_start', 'vtual_content_metrics_bucket_idx');
            $table->index('creator_id', 'vtual_content_metrics_creator_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vtual_content_metrics');
        Schema::dropIfExists('vtual_channel_metrics');
    }
};
