<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Service purchases, squashed: quantity, the plan-change columns (list/credit/
 * charge/surplus + previous entitlement) and the entitlement period all belong
 * to the create. Runs after `exchange_rate_snapshots` so the FX snapshot FK is
 * declarable here rather than in an alter.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_purchases', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('service_id')->constrained('services')->restrictOnDelete();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->char('display_currency', 3);
            $table->decimal('display_amount', 15, 2);
            $table->decimal('list_amount', 15, 2)->nullable();
            $table->decimal('credit_amount', 15, 2)->default(0);
            $table->decimal('charge_amount', 15, 2)->nullable();
            $table->unsignedInteger('surplus_days')->default(0);
            $table->uuid('previous_entitlement_id')->nullable();
            $table->char('settlement_currency', 3)->default('IDR');
            $table->decimal('settlement_amount', 15, 2);
            $table->decimal('fx_rate', 18, 8)->nullable();
            $table->string('fx_provider', 64)->nullable();
            $table->timestamp('fx_fetched_at')->nullable();
            $table->foreignUuid('fx_rate_snapshot_id')->nullable()->constrained('exchange_rate_snapshots')->nullOnDelete();
            $table->string('status', 32)->default('pending');
            $table->string('change_type', 32)->nullable();
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index(['user_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_purchases');
    }
};
