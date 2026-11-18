<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_channel_route_snapshots', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('payment_channel_code', 64);
            $table->foreignUuid('payment_gateway_id')->constrained('payment_gateways')->restrictOnDelete();
            $table->string('provider_payment_type', 64);
            $table->string('provider_payment_channel', 64)->nullable();
            $table->string('route_role', 16);
            $table->unsignedTinyInteger('settlement_days');
            $table->unsignedInteger('fee_flat')->nullable();
            $table->decimal('fee_percent', 5, 3)->nullable();
            $table->boolean('fee_vat_inclusive')->default(false);
            $table->string('policy_tag', 64)->nullable();
            $table->timestamp('captured_at');
            $table->timestamps();

            $table->index(['payment_channel_code', 'captured_at'], 'pcrs_channel_captured_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_channel_route_snapshots');
    }
};
