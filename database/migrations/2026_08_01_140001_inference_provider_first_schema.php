<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

        $this->migrateProviderRatesFromPlans();

        Schema::table('ai_inference_providers', function (Blueprint $table): void {
            if (! Schema::hasColumn('ai_inference_providers', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('name');
            }
            $table->dropConstrainedForeignId('rate_plan_id');
        });

        Schema::table('ai_model_rate_snapshots', function (Blueprint $table): void {
            $table->foreignUuid('inference_provider_id')
                ->nullable()
                ->after('model_slug')
                ->constrained('ai_inference_providers')
                ->nullOnDelete();
        });

        $this->backfillSnapshotProviders();

        Schema::table('ai_model_rate_snapshots', function (Blueprint $table): void {
            $table->dropUnique('ai_model_rate_snapshots_dedup_unique');
            $table->dropConstrainedForeignId('rate_plan_id');
            $table->unique(
                ['inference_provider_id', 'model_slug', 'input_per_million_usd', 'output_per_million_usd'],
                'ai_model_rate_snapshots_provider_dedup_unique',
            );
        });

        Schema::dropIfExists('ai_inference_rate_plan_entries');
        Schema::dropIfExists('ai_inference_rate_plans');
    }

    public function down(): void
    {
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

        Schema::table('ai_inference_providers', function (Blueprint $table): void {
            $table->foreignUuid('rate_plan_id')
                ->nullable()
                ->after('name')
                ->constrained('ai_inference_rate_plans')
                ->restrictOnDelete();
        });

        Schema::table('ai_model_rate_snapshots', function (Blueprint $table): void {
            $table->dropUnique('ai_model_rate_snapshots_provider_dedup_unique');
            $table->dropConstrainedForeignId('inference_provider_id');
            $table->foreignUuid('rate_plan_id')
                ->nullable()
                ->after('model_slug')
                ->constrained('ai_inference_rate_plans')
                ->restrictOnDelete();
            $table->unique(
                ['model_slug', 'rate_plan_id', 'input_per_million_usd', 'output_per_million_usd'],
                'ai_model_rate_snapshots_dedup_unique',
            );
        });

        Schema::dropIfExists('ai_provider_model_rates');
    }

    private function migrateProviderRatesFromPlans(): void
    {
        if (! Schema::hasTable('ai_inference_rate_plan_entries')) {
            return;
        }

        $providers = DB::table('ai_inference_providers')->get(['id', 'rate_plan_id']);

        foreach ($providers as $provider) {
            if ($provider->rate_plan_id === null) {
                continue;
            }

            $entries = DB::table('ai_inference_rate_plan_entries')
                ->where('rate_plan_id', $provider->rate_plan_id)
                ->get();

            foreach ($entries as $entry) {
                DB::table('ai_provider_model_rates')->updateOrInsert(
                    [
                        'inference_provider_id' => $provider->id,
                        'model_card_id' => $entry->model_card_id,
                    ],
                    [
                        'id' => (string) \Illuminate\Support\Str::uuid(),
                        'input_per_million_usd' => $entry->input_per_million_usd,
                        'output_per_million_usd' => $entry->output_per_million_usd,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            }
        }
    }

    private function backfillSnapshotProviders(): void
    {
        $defaultProviderId = DB::table('ai_inference_providers')
            ->where('slug', config('inference.default_provider_slug', 'openwebui-9router'))
            ->value('id');

        if ($defaultProviderId === null) {
            return;
        }

        DB::table('ai_model_rate_snapshots')
            ->whereNull('inference_provider_id')
            ->update(['inference_provider_id' => $defaultProviderId]);
    }
};
