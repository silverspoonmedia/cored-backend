<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('archivd_folders', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('parent_id')->nullable();
            $table->string('name');
            $table->unsignedInteger('order')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['user_id', 'parent_id']);
            $table->index('parent_id', 'archivd_folders_parent_id_index');
            $table->unique(['user_id', 'parent_id', 'order']);
        });

        Schema::table('archivd_folders', function (Blueprint $table): void {
            $table->foreign('parent_id', 'archivd_folders_parent_id_foreign')
                ->references('id')
                ->on('archivd_folders')
                ->cascadeOnDelete();
        });

        Schema::create('archivd_files', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('folder_id')
                ->nullable()
                ->constrained('archivd_folders')
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

        Schema::create('archivd_uploads', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('folder_id')
                ->nullable()
                ->constrained('archivd_folders')
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
                ->constrained('archivd_files')
                ->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archivd_uploads');
        Schema::dropIfExists('archivd_files');
        Schema::dropIfExists('archivd_folders');
    }
};
