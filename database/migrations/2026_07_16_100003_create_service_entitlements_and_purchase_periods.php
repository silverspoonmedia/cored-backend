<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_entitlements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('service_id')->constrained('services')->cascadeOnDelete();
            $table->string('status', 32);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('grace_ends_at')->nullable();
            $table->timestamp('grace_notified_at')->nullable();
            $table->foreignUuid('source_purchase_id')->nullable()->constrained('service_purchases')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'service_id']);
            $table->index(['status', 'ends_at']);
            $table->index(['status', 'grace_ends_at']);
        });

        Schema::table('service_purchases', function (Blueprint $table): void {
            $table->timestamp('period_start')->nullable()->after('status');
            $table->timestamp('period_end')->nullable()->after('period_start');
            $table->foreignUuid('fx_rate_snapshot_id')
                ->nullable()
                ->after('fx_fetched_at')
                ->constrained('exchange_rate_snapshots')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('service_purchases', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('fx_rate_snapshot_id');
            $table->dropColumn(['period_start', 'period_end']);
        });

        Schema::dropIfExists('service_entitlements');
    }
};
