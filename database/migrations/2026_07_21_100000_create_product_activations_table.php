<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_activations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('product_key', 64);
            $table->string('activation_type', 16);
            $table->string('state', 16);
            $table->json('provisioning_payload')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->string('source', 64)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'product_key']);
            $table->index(['product_key', 'state']);
            $table->index(['user_id', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_activations');
    }
};
