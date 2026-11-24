<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_channel_routes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('payment_channel_id')->constrained('payment_channels')->cascadeOnDelete();
            $table->foreignUuid('payment_gateway_id')->constrained('payment_gateways')->restrictOnDelete();
            $table->string('provider_payment_type', 64);
            $table->string('provider_payment_channel', 64)->nullable();
            $table->unsignedInteger('priority')->default(0);
            $table->string('route_role', 16)->default('primary');
            $table->unsignedTinyInteger('settlement_days')->default(2);
            $table->unsignedInteger('fee_flat')->nullable();
            $table->decimal('fee_percent', 5, 3)->nullable();
            $table->boolean('fee_vat_inclusive')->default(false);
            $table->string('policy_tag', 64)->nullable();
            $table->boolean('is_policy_managed')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['payment_channel_id', 'payment_gateway_id'], 'pcr_channel_gateway_unique');
            $table->index(['payment_channel_id', 'is_active', 'priority'], 'pcr_channel_active_priority_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_channel_routes');
    }
};
