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

        // Billing-side links. The columns themselves ship with whichever product
        // owns the AI ledger; the FKs land here because these are the catalog's
        // tables. The product publishes its tables and columns in
        // `ai.ledger_fk_targets`, so this migration names no product.
        //
        // Guarded on the host owning that table: the ledger is a product-domain
        // table (it leaves with the product's uninstall), so a standalone package
        // test — or a consumer without that product — creates the pricing catalog
        // and skips the link, instead of failing on a table nothing will use.
        foreach ((array) config('ai.ledger_fk_targets', []) as $target) {
            $table = $target['table'] ?? null;

            if (! is_string($table) || $table === '' || ! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($target): void {
                foreach ((array) ($target['columns'] ?? []) as $column) {
                    $name = $column['column'] ?? null;
                    $on = $column['on'] ?? null;

                    if (! is_string($name) || $name === '' || ! is_string($on) || $on === '') {
                        continue;
                    }

                    $foreign = $blueprint->foreign($name)
                        ->references($column['references'] ?? 'id')
                        ->on($on);

                    ($column['on_delete'] ?? 'null') === 'cascade'
                        ? $foreign->cascadeOnDelete()
                        : $foreign->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        foreach ((array) config('ai.ledger_fk_targets', []) as $target) {
            $table = $target['table'] ?? null;

            if (! is_string($table) || $table === '' || ! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($target): void {
                foreach ((array) ($target['columns'] ?? []) as $column) {
                    $name = $column['column'] ?? null;

                    if (is_string($name) && $name !== '') {
                        $blueprint->dropForeign([$name]);
                    }
                }
            });
        }

        Schema::dropIfExists('ai_model_rate_snapshots');
        Schema::dropIfExists('ai_provider_model_rates');
        Schema::dropIfExists('ai_inference_providers');
        Schema::dropIfExists('ai_model_cards');
    }
};
