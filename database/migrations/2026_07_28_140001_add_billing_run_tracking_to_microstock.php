<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('microstock_asset_metadata', function (Blueprint $table): void {
            $table->foreignUuid('billing_user_id')
                ->nullable()
                ->after('user_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->uuid('analysis_run_id')->nullable()->after('billing_user_id');
            $table->index('analysis_run_id');
        });

        Schema::table('microstock_ai_usage_events', function (Blueprint $table): void {
            $table->uuid('analysis_run_id')->nullable()->after('folder_id');
            $table->unsignedSmallInteger('attempt_index')->nullable()->after('stage');
            $table->string('provider_request_id')->nullable()->after('resolved_model');
            $table->timestamp('voided_at')->nullable()->after('billed_at');

            $table->index('analysis_run_id');
            $table->index('voided_at');
        });
    }

    public function down(): void
    {
        Schema::table('microstock_ai_usage_events', function (Blueprint $table): void {
            $table->dropIndex(['analysis_run_id']);
            $table->dropIndex(['voided_at']);
            $table->dropColumn(['analysis_run_id', 'attempt_index', 'provider_request_id', 'voided_at']);
        });

        Schema::table('microstock_asset_metadata', function (Blueprint $table): void {
            $table->dropForeign(['billing_user_id']);
            $table->dropIndex(['analysis_run_id']);
            $table->dropColumn(['billing_user_id', 'analysis_run_id']);
        });
    }
};
