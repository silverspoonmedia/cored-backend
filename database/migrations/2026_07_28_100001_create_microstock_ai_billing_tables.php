<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
            $table->foreignUuid('file_id')->nullable()->constrained('file_hosting_files')->nullOnDelete();
            $table->foreignUuid('folder_id')->nullable()->constrained('file_hosting_folders')->nullOnDelete();
            $table->string('stage', 32);
            $table->string('alias_model');
            $table->string('resolved_model');
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->unsignedInteger('total_tokens')->default(0);
            $table->unsignedBigInteger('provider_cost_micro_usd')->default(0);
            $table->unsignedBigInteger('billable_micro_usd')->default(0);
            $table->timestamp('billed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('asset_metadata_id');
            $table->index(['user_id', 'created_at']);
            $table->index('billed_at');
        });

        Schema::table('microstock_asset_metadata', function (Blueprint $table): void {
            $table->json('ai_billing')->nullable()->after('ai_raw');
        });
    }

    public function down(): void
    {
        Schema::table('microstock_asset_metadata', function (Blueprint $table): void {
            $table->dropColumn('ai_billing');
        });

        Schema::dropIfExists('microstock_ai_usage_events');
        Schema::dropIfExists('microstock_ai_credit_transactions');
        Schema::dropIfExists('microstock_ai_balances');
    }
};
