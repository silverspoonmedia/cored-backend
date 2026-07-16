<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_purchases', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('service_id')->constrained('services')->restrictOnDelete();
            $table->char('display_currency', 3);
            $table->decimal('display_amount', 15, 2);
            $table->char('settlement_currency', 3)->default('IDR');
            $table->decimal('settlement_amount', 15, 2);
            $table->decimal('fx_rate', 18, 8)->nullable();
            $table->string('fx_provider', 64)->nullable();
            $table->timestamp('fx_fetched_at')->nullable();
            $table->string('status', 32)->default('pending');
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
