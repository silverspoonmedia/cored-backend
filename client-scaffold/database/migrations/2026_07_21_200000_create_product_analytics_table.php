<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Product analytics, squashed to the per-tier aggregate shape: one row per
 * (product_key, tier, metric_key) bucket. The per-user version never survives a
 * fresh install, so it is not part of the baseline.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_analytics', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('product_key', 64);
            $table->string('tier', 16)->default('n/a');
            $table->string('metric_key', 64);
            $table->unsignedBigInteger('metric_value')->default(0);
            $table->timestamps();

            $table->unique(['product_key', 'tier', 'metric_key']);
            $table->index(['product_key', 'metric_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_analytics');
    }
};
