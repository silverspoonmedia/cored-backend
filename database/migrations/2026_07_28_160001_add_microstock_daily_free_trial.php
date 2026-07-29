<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('microstock_daily_free_usage', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('usage_date');
            $table->unsignedInteger('analyses_used')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'usage_date']);
        });

        Schema::table('microstock_asset_metadata', function (Blueprint $table): void {
            $table->string('billing_source', 32)->nullable()->after('analysis_run_id');
        });

        Schema::table('microstock_ai_usage_events', function (Blueprint $table): void {
            $table->string('billing_source', 32)->nullable()->after('analysis_run_id');
        });
    }

    public function down(): void
    {
        Schema::table('microstock_ai_usage_events', function (Blueprint $table): void {
            $table->dropColumn('billing_source');
        });

        Schema::table('microstock_asset_metadata', function (Blueprint $table): void {
            $table->dropColumn('billing_source');
        });

        Schema::dropIfExists('microstock_daily_free_usage');
    }
};
