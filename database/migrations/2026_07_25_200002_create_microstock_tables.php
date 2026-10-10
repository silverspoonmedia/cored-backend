<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Microstock (Archivd subproduct) tables, squashed into one create: asset
 * metadata, marketplace credentials + upload attempts, the AI billing ledger
 * (balances, credit transactions, usage events) and the daily free-trial
 * counter.
 *
 * Every column the product added later is part of the baseline - the AI
 * analysis stages, the collection run tracking, the daily-free billing source
 * and the marketplace credential status columns. The FKs into the AI pricing
 * tables are declared in the inference migration, because those tables are
 * created after this one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('microstock_asset_metadata', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('file_id')
                ->constrained('archivd_files')
                ->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            // Who pays for the analysis; null falls back to the owner.
            $table->foreignUuid('billing_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->uuid('analysis_run_id')->nullable();
            $table->string('billing_source', 32)->nullable();
            $table->string('title')->nullable();
            $table->json('keywords')->nullable();
            $table->string('category')->nullable();
            $table->text('description')->nullable();
            $table->string('status', 32)->default('pending'); // pending|generated|finalized
            $table->json('ai_raw')->nullable();
            // Decoupled analysis stages: vision pass, thinking pass, merged result.
            $table->json('ai_vision')->nullable();
            $table->json('ai_thinking')->nullable();
            $table->json('ai_result')->nullable();
            $table->json('ai_billing')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique('file_id');
            $table->index(['user_id', 'status']);
            $table->index('analysis_run_id');
        });

        Schema::create('microstock_marketplace_credentials', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('marketplace');
            $table->string('label');
            // pending (never used) | verified (last transfer succeeded) | failed
            $table->string('status', 16)->default('pending');
            $table->text('last_error')->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->text('secret_payload'); // envelope-encrypted JSON (host/credentials)
            $table->timestamps();

            $table->unique(['user_id', 'marketplace', 'label'], 'ms_cred_user_market_label_uq');
            $table->index(['user_id', 'marketplace'], 'ms_cred_user_market_idx');
            $table->index(['user_id', 'status'], 'ms_cred_user_status_idx');
        });

        Schema::create('microstock_marketplace_uploads', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('folder_id')->constrained('archivd_folders')->cascadeOnDelete();
            $table->foreignUuid('credential_id')->constrained('microstock_marketplace_credentials')->cascadeOnDelete();

            $table->string('marketplace');
            // queued | uploading | uploaded | failed
            $table->string('status', 16)->default('queued');
            $table->string('remote_path')->nullable();
            $table->unsignedInteger('file_count')->default(0);
            $table->unsignedBigInteger('byte_count')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['folder_id', 'created_at'], 'ms_upload_folder_created_idx');
            $table->index(['user_id', 'status'], 'ms_upload_user_status_idx');
        });

        Schema::create('microstock_ai_balances', function (Blueprint $table): void {
            $table->foreignUuid('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('balance_micro_usd')->default(0);
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('microstock_ai_credit_transactions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->bigInteger('amount_micro_usd');
            $table->string('type', 32);
            $table->string('reference_type')->nullable();
            $table->uuid('reference_id')->nullable();
            $table->unsignedBigInteger('balance_after');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index(['reference_type', 'reference_id'], 'ms_ai_credit_tx_ref_idx');
        });

        Schema::create('microstock_ai_usage_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('asset_metadata_id')
                ->nullable()
                ->constrained('microstock_asset_metadata')
                ->nullOnDelete();
            $table->foreignUuid('file_id')->nullable()->constrained('archivd_files')->nullOnDelete();
            $table->foreignUuid('folder_id')->nullable()->constrained('archivd_folders')->nullOnDelete();
            $table->uuid('analysis_run_id')->nullable();
            $table->string('billing_source', 32)->nullable();
            $table->string('stage', 32);
            $table->unsignedSmallInteger('attempt_index')->nullable();
            $table->string('alias_model');
            $table->string('resolved_model');
            // Provider attribution + rate snapshot; FKs are added by the
            // inference migration once those tables exist.
            $table->uuid('ai_model_rate_snapshot_id')->nullable();
            $table->uuid('inference_provider_id')->nullable();
            $table->string('provider_request_id')->nullable();
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->unsignedInteger('total_tokens')->default(0);
            $table->unsignedBigInteger('provider_cost_micro_usd')->default(0);
            $table->unsignedBigInteger('billable_micro_usd')->default(0);
            $table->timestamp('billed_at')->nullable();
            $table->timestamp('consolidated_at')->nullable();
            $table->bigInteger('consolidation_adjustment_micro_usd')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('asset_metadata_id');
            $table->index(['user_id', 'created_at']);
            $table->index('billed_at');
            $table->index('analysis_run_id');
            $table->index('voided_at');
        });

        Schema::create('microstock_daily_free_usage', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('usage_date');
            $table->unsignedInteger('analyses_used')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'usage_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('microstock_daily_free_usage');
        Schema::dropIfExists('microstock_ai_usage_events');
        Schema::dropIfExists('microstock_ai_credit_transactions');
        Schema::dropIfExists('microstock_ai_balances');
        Schema::dropIfExists('microstock_marketplace_uploads');
        Schema::dropIfExists('microstock_marketplace_credentials');
        Schema::dropIfExists('microstock_asset_metadata');
    }
};
