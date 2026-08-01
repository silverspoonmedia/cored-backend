<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_model_cards', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('slug', 128)->unique();
            $table->string('display_name', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('ai_inference_rate_plans', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('slug', 64)->unique();
            $table->string('name', 128);
            $table->timestamps();
        });

        Schema::create('ai_inference_rate_plan_entries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('rate_plan_id')->constrained('ai_inference_rate_plans')->cascadeOnDelete();
            $table->foreignUuid('model_card_id')->constrained('ai_model_cards')->cascadeOnDelete();
            $table->decimal('input_per_million_usd', 10, 4);
            $table->decimal('output_per_million_usd', 10, 4);
            $table->timestamps();

            $table->unique(['rate_plan_id', 'model_card_id'], 'ai_rate_plan_entries_plan_card_unique');
        });

        Schema::create('ai_inference_providers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('slug', 64)->unique();
            $table->string('name', 128);
            $table->foreignUuid('rate_plan_id')->constrained('ai_inference_rate_plans')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('ai_model_rate_snapshots', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('model_card_id')->constrained('ai_model_cards')->restrictOnDelete();
            $table->string('model_slug', 128);
            $table->foreignUuid('rate_plan_id')->constrained('ai_inference_rate_plans')->restrictOnDelete();
            $table->decimal('input_per_million_usd', 10, 4);
            $table->decimal('output_per_million_usd', 10, 4);
            $table->timestamp('captured_at');
            $table->timestamps();

            $table->unique(
                ['model_slug', 'rate_plan_id', 'input_per_million_usd', 'output_per_million_usd'],
                'ai_model_rate_snapshots_dedup_unique',
            );
            $table->index(['model_slug', 'captured_at'], 'ai_model_rate_snapshots_slug_captured_idx');
        });

        Schema::table('microstock_ai_usage_events', function (Blueprint $table): void {
            $table->foreignUuid('ai_model_rate_snapshot_id')
                ->nullable()
                ->after('resolved_model')
                ->constrained('ai_model_rate_snapshots')
                ->nullOnDelete();
            $table->foreignUuid('inference_provider_id')
                ->nullable()
                ->after('ai_model_rate_snapshot_id')
                ->constrained('ai_inference_providers')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('microstock_ai_usage_events', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('inference_provider_id');
            $table->dropConstrainedForeignId('ai_model_rate_snapshot_id');
        });

        Schema::dropIfExists('ai_model_rate_snapshots');
        Schema::dropIfExists('ai_inference_providers');
        Schema::dropIfExists('ai_inference_rate_plan_entries');
        Schema::dropIfExists('ai_inference_rate_plans');
        Schema::dropIfExists('ai_model_cards');
    }
};
