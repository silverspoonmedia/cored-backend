<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            // UUID v7 PK (project convention — see docs/platform/identifiers.md). The
            // owning record is referenced polymorphically so ONE attachments
            // table can serve any model (orders today, anything tomorrow).
            $table->uuid('id')->primary();

            // uuidMorphs => `attachable_type` (string) + `attachable_id` (uuid),
            // matching the project's UUID PK/FK rule, plus a composite index.
            $table->uuidMorphs('attachable');

            // The user who owns/uploaded the file. Nullable + nullOnDelete so a
            // deleted user does not cascade-remove their files (the owning
            // record decides the file's lifecycle, not the uploader).
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();

            // File metadata; the binary itself lives on the storage `disk` and
            // is addressed by `path` (the disk-relative key used to build URLs
            // and delete the object). Storing the disk per row means a file can
            // be resolved/removed even if the owning model's default disk changes.
            $table->string('disk');
            $table->string('path');
            $table->string('name');
            $table->unsignedBigInteger('size');
            $table->string('mime');

            // Signed-URL cache (S3-compatible disks only). A temporary/signed URL
            // is expensive to mint and itself expires, so it is cached here with
            // its expiry; it is regenerated when the current time nears
            // `cached_url_expires_at`. Public-disk files leave both null and use
            // a plain, non-expiring URL.
            $table->text('cached_url')->nullable();
            $table->timestamp('cached_url_expires_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
