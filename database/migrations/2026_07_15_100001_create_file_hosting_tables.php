<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_hosting_folders', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('parent_id')
                ->nullable()
                ->constrained('file_hosting_folders')
                ->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('order')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['user_id', 'parent_id']);
            $table->unique(['user_id', 'parent_id', 'order']);
        });

        Schema::create('file_hosting_files', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('folder_id')
                ->nullable()
                ->constrained('file_hosting_folders')
                ->nullOnDelete();
            $table->string('name');
            $table->string('disk');
            $table->string('path');
            $table->unsignedBigInteger('size')->default(0);
            $table->string('mime')->nullable();
            $table->string('checksum')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['user_id', 'folder_id']);
            $table->unique(['disk', 'path']);
        });

        Schema::create('file_hosting_uploads', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('folder_id')
                ->nullable()
                ->constrained('file_hosting_folders')
                ->nullOnDelete();
            $table->string('disk');
            $table->string('path');
            $table->text('s3_upload_id')->nullable();
            $table->string('strategy');
            $table->string('original_name');
            $table->string('mime');
            $table->unsignedBigInteger('size');
            $table->string('status');
            $table->json('parts')->nullable();
            $table->foreignUuid('promoted_file_id')
                ->nullable()
                ->constrained('file_hosting_files')
                ->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_hosting_uploads');
        Schema::dropIfExists('file_hosting_files');
        Schema::dropIfExists('file_hosting_folders');
    }
};
