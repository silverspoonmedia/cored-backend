<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_detail', function (Blueprint $table) {
            // UUID v7 PK + UUID FK to the master (project convention). The FK
            // cascadeOnDelete removes detail rows when the master is hard-deleted;
            // the service still wraps writes in a transaction so a failed line
            // never leaves a half-written master behind (no orphaned data).
            $table->uuid('id')->primary();
            $table->foreignUuid('order_master_id')->constrained('order_master')->cascadeOnDelete();
            $table->string('product_name');
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 15, 2);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_detail');
    }
};
