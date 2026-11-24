<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_addon_renewals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 32)->default('pending');
            $table->string('display_currency', 3);
            $table->decimal('display_amount', 15, 2);
            $table->string('settlement_currency', 3);
            $table->decimal('settlement_amount', 15, 2);
            $table->decimal('fx_rate', 18, 8)->nullable();
            $table->string('fx_provider')->nullable();
            $table->timestamp('fx_fetched_at')->nullable();
            $table->uuid('fx_rate_snapshot_id')->nullable();
            $table->timestamp('billing_ends_at_before')->nullable();
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('service_addon_renewal_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('service_addon_renewal_id')
                ->constrained('service_addon_renewals')
                ->cascadeOnDelete();
            $table->foreignUuid('service_id')->constrained('services')->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_amount', 15, 2);
            $table->decimal('line_amount', 15, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_addon_renewal_lines');
        Schema::dropIfExists('service_addon_renewals');
    }
};
