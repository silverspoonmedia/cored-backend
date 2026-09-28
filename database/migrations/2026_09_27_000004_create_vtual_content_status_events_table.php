<?php

use App\Domain\Vtual\Support\VtualContentStatusRecorder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Status timeline for a content row (decision: docs/domains/vtual/reference-metrics.md).
 *
 * The product question this table answers was "what is a live session?" — and
 * the answer is already in the schema: the platform's stream session id **is**
 * the content `external_id`, so one content row is one session, and a restream
 * is a different session because the platform issues a different id. No session
 * table is needed; what was missing is the *status timeline* of that row.
 *
 * Append-only, one row per observed transition (not per poll), so a stream that
 * stays `live` for six hours costs two rows — `scheduled`/`live` and `ended`.
 * Written by {@see VtualContentStatusRecorder} from
 * the hydrate and end-of-stream paths, and pruned on the metric retention window.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vtual_content_status_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('content_id')->constrained('vtual_contents')->cascadeOnDelete();
            $table->foreignUuid('channel_id')->constrained('vtual_channels')->cascadeOnDelete();
            $table->foreignUuid('platform_id')->constrained('vtual_platforms')->cascadeOnDelete();
            $table->foreignUuid('creator_id')->nullable()->constrained('vtual_creators')->nullOnDelete();

            $table->string('status', 32);
            // Null on the first observed status — the row had no prior state.
            $table->string('previous_status', 32)->nullable();

            // When the platform state changed (the write time), not a bucket.
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->nullable();

            $table->index(['content_id', 'occurred_at'], 'vtual_content_status_events_content_idx');
            $table->index(['creator_id', 'occurred_at'], 'vtual_content_status_events_creator_idx');
            $table->index('occurred_at', 'vtual_content_status_events_occurred_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vtual_content_status_events');
    }
};
