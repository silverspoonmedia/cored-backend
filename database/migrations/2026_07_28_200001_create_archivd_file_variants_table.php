<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('archivd_file_variants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('file_id')
                ->constrained('archivd_files')
                ->cascadeOnDelete();
            $table->string('kind', 32);
            $table->string('disk');
            $table->string('path');
            $table->unsignedBigInteger('size')->default(0);
            $table->unsignedSmallInteger('max_px');
            $table->string('mime')->default('image/jpeg');
            $table->string('status');
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique(['file_id', 'kind']);
            $table->index(['file_id', 'status']);
            $table->unique(['disk', 'path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archivd_file_variants');
    }
};
