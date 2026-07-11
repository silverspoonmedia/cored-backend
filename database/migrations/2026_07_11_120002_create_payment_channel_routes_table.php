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
