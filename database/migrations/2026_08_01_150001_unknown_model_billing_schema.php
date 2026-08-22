<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_model_rate_snapshots', function (Blueprint $table): void {
            $table->string('rate_source', 32)->default('known')->after('output_per_million_usd');
        });

        Schema::table('ai_model_cards', function (Blueprint $table): void {
            $table->boolean('needs_rate_classification')->default(false)->after('is_active');
        });

        Schema::table('microstock_ai_usage_events', function (Blueprint $table): void {
            $table->timestamp('consolidated_at')->nullable()->after('billed_at');
            $table->bigInteger('consolidation_adjustment_micro_usd')->nullable()->after('consolidated_at');
        });

        $this->normalizeBalanceColumn();
    }

    public function down(): void
    {
        Schema::table('ai_model_rate_snapshots', function (Blueprint $table): void {
            $table->dropColumn('rate_source');
        });

        Schema::table('ai_model_cards', function (Blueprint $table): void {
            $table->dropColumn('needs_rate_classification');
        });

        Schema::table('microstock_ai_usage_events', function (Blueprint $table): void {
            $table->dropColumn(['consolidated_at', 'consolidation_adjustment_micro_usd']);
        });

        $this->normalizeBalanceColumn();
    }

    private function normalizeBalanceColumn(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (! Schema::hasColumn('microstock_ai_balances', 'balance_micro_usd')) {
            return;
        }

        if ($driver === 'pgsql') {
            Schema::table('microstock_ai_balances', function (Blueprint $table): void {
                $table->bigInteger('balance_micro_usd')->default(0)->change();
            });

            return;
        }

        Schema::table('microstock_ai_balances', function (Blueprint $table): void {
            $table->unsignedBigInteger('balance_micro_usd')->default(0)->change();
        });
    }
};
