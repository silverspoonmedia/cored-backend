<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Service catalog, squashed: `plan_kind` and `storage_bytes` are part of the
 * baseline instead of a later alter.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code', 128)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('billing_interval', 32)->default('monthly');
            $table->string('fulfillment_target', 64)->nullable();
            $table->string('entitlement', 64)->nullable();
            $table->string('plan_kind', 32)->nullable();
            $table->unsignedBigInteger('storage_bytes')->default(0);
            $table->string('status', 32)->default('draft');
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('fulfillment_target');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
