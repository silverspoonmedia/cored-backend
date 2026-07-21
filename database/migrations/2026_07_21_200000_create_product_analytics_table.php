<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_analytics', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('product_key', 64);
            $table->string('tier', 16)->default('n/a');
            $table->string('metric_key', 64);
            $table->unsignedBigInteger('metric_value')->default(0);
            $table->timestamps();

            $table->unique(['product_key', 'user_id', 'metric_key']);
            $table->index(['product_key', 'metric_key']);
            $table->index(['product_key', 'tier', 'metric_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_analytics');
    }
};
