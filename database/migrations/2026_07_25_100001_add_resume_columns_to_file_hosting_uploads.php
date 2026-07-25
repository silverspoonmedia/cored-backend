<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('file_hosting_uploads', function (Blueprint $table): void {
            // sha256 hex fingerprint of the file body, supplied by the client at init.
            // Lets a re-upload of the same file resume a still-pending multipart session.
            $table->string('content_hash', 64)->nullable()->after('size');
            // Chunk layout frozen at init so config drift never breaks resume math.
            $table->unsignedBigInteger('chunk_size')->nullable()->after('content_hash');
            $table->unsignedInteger('part_count')->nullable()->after('chunk_size');

            $table->index(['user_id', 'content_hash', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('file_hosting_uploads', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'content_hash', 'status']);
            $table->dropColumn(['content_hash', 'chunk_size', 'part_count']);
        });
    }
};
