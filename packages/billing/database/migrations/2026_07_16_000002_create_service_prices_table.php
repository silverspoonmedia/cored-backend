<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_prices', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('service_id')->constrained('services')->cascadeOnDelete();
            $table->char('currency', 3);
            $table->decimal('amount', 15, 2);
            $table->timestamps();

            $table->unique(['service_id', 'currency']);
            $table->index('currency');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_prices');
    }
};
