<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_domain_visibilities', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('product_key', 64)->unique();
            $table->string('visibility', 16)->default('public');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_domain_visibilities');
    }
};
