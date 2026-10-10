<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inference pricing, squashed to the provider-first shape: a provider owns a
 * rate per model card (`ai_provider_model_rates`), and a billing snapshot
 * captures the provider + model + amounts that were in force. The intermediate
 * rate-plan tables never survive a fresh install, so they are gone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_model_cards', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('slug', 128)->unique();
            $table->string('display_name', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('needs_rate_classification')->default(false);
            $table->timestamps();
        });

        Schema::create('ai_inference_providers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('slug', 64)->unique();
            $table->string('name', 128);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('ai_provider_model_rates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('inference_provider_id')
                ->constrained('ai_inference_providers')
                ->cascadeOnDelete();
            $table->foreignUuid('model_card_id')
                ->constrained('ai_model_cards')
                ->cascadeOnDelete();
            $table->decimal('input_per_million_usd', 10, 4);
            $table->decimal('output_per_million_usd', 10, 4);
            $table->timestamps();

            $table->unique(
                ['inference_provider_id', 'model_card_id'],
                'ai_provider_model_rates_provider_card_unique',
            );
        });

        Schema::create('ai_model_rate_snapshots', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('model_card_id')->constrained('ai_model_cards')->restrictOnDelete();
            $table->string('model_slug', 128);
            $table->foreignUuid('inference_provider_id')
                ->nullable()
                ->constrained('ai_inference_providers')
                ->nullOnDelete();
            $table->decimal('input_per_million_usd', 10, 4);
            $table->decimal('output_per_million_usd', 10, 4);
            $table->string('rate_source', 32)->default('known');
            $table->timestamp('captured_at');
            $table->timestamps();

            $table->unique(
                ['inference_provider_id', 'model_slug', 'input_per_million_usd', 'output_per_million_usd'],
                'ai_model_rate_snapshots_provider_dedup_unique',
            );
            $table->index(['model_slug', 'captured_at'], 'ai_model_rate_snapshots_slug_captured_idx');
        });

        // Billing-side links. The columns themselves ship with the microstock
        // tables; the FKs land here because these are the tables they point at.
        Schema::table('microstock_ai_usage_events', function (Blueprint $table): void {
            $table->foreign('ai_model_rate_snapshot_id')
                ->references('id')
                ->on('ai_model_rate_snapshots')
                ->nullOnDelete();
            $table->foreign('inference_provider_id')
                ->references('id')
                ->on('ai_inference_providers')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('microstock_ai_usage_events', function (Blueprint $table): void {
            $table->dropForeign(['inference_provider_id']);
            $table->dropForeign(['ai_model_rate_snapshot_id']);
        });

        Schema::dropIfExists('ai_model_rate_snapshots');
        Schema::dropIfExists('ai_provider_model_rates');
        Schema::dropIfExists('ai_inference_providers');
        Schema::dropIfExists('ai_model_cards');
    }
};
