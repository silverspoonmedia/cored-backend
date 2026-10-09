<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Service entitlements, squashed: `quantity` is part of the create, and the
 * period columns live on `service_purchases` (where the checkout writes them)
 * instead of being bolted on afterwards.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_entitlements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('service_id')->constrained('services')->cascadeOnDelete();
            $table->string('status', 32);
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('grace_ends_at')->nullable();
            $table->timestamp('grace_notified_at')->nullable();
            $table->foreignUuid('source_purchase_id')->nullable()->constrained('service_purchases')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'service_id']);
            $table->index(['status', 'ends_at']);
            $table->index(['status', 'grace_ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_entitlements');
    }
};
