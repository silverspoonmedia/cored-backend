<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('microstock_asset_metadata', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('file_id')
                ->constrained('file_hosting_files')
                ->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->json('keywords')->nullable();
            $table->string('status', 32)->default('pending'); // pending|generated|finalized
            $table->json('ai_raw')->nullable();
            $table->timestamps();

            $table->unique('file_id');
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('microstock_asset_metadata');
    }
};
