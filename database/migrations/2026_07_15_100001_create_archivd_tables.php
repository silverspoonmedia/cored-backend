<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Archivd product tables, squashed into one create.
 *
 * Everything the product later bolted on is part of the baseline: workspace
 * membership + invites, workspace scoping on folders/files/uploads, folder
 * visibility and the microstock "collection" columns, file stats + signed URL
 * cache, upload chunk layout and prune indexes, quotas, image variants and the
 * DMCA hash blocklist.
 *
 * Dropped churn: the upload resume fingerprint (`content_hash`) and its lookup
 * index — resume was reverted, and `chunk_size` / `part_count` are what the
 * multipart session actually reads. Workspace ownership is multi-slot from the
 * start, so no lifetime `unique(owner_user_id)` exists to drop later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('archivd_workspaces', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('status', 32)->default('active'); // active|archived
            $table->string('kind', 32)->default('team'); // team|microstock (slot-exempt)
            $table->timestamps();

            // Multi-owned: many workspaces per owner; slot counts use (owner, status).
            $table->index(['owner_user_id', 'status'], 'fh_workspaces_owner_status_idx');
            $table->index(['owner_user_id', 'kind', 'status'], 'fh_workspaces_owner_kind_status_idx');
        });

        Schema::create('archivd_workspace_members', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')
                ->constrained('archivd_workspaces')
                ->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 32); // owner|admin|editor|viewer
            $table->string('status', 32)->default('active'); // active|invited|removed
            $table->foreignUuid('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'user_id']);
            $table->index(['user_id', 'status']);
            $table->index(['workspace_id', 'status', 'role']);
        });

        Schema::create('archivd_workspace_invites', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')
                ->constrained('archivd_workspaces')
                ->cascadeOnDelete();
            $table->string('email');
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('role', 32); // admin|editor|viewer (not owner)
            $table->string('token', 64)->unique();
            $table->string('status', 32)->default('pending'); // pending|accepted|revoked|expired
            $table->foreignUuid('invited_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'status']);
            $table->index(['email', 'status']);
        });

        Schema::create('archivd_folders', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('workspace_id')
                ->nullable()
                ->constrained('archivd_workspaces')
                ->cascadeOnDelete();
            $table->uuid('parent_id')->nullable();
            $table->string('name');
            $table->unsignedInteger('order')->nullable();
            $table->string('visibility', 32)->default('private');
            // Microstock "collection" state; only folders inside a microstock-kind
            // workspace use these, regular Archivd folders keep them NULL.
            $table->string('collection_status', 32)->nullable();
            $table->text('collection_hint')->nullable();
            $table->string('collection_billing_mode', 16)->nullable();
            $table->timestamp('collection_locked_at')->nullable();
            $table->string('collection_csv_path')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['user_id', 'parent_id']);
            $table->index('parent_id', 'archivd_folders_parent_id_index');
            $table->index(['workspace_id', 'parent_id']);
            $table->index(['visibility']);
            $table->unique(['user_id', 'parent_id', 'order']);
            $table->index(['workspace_id', 'collection_status'], 'fh_folders_workspace_collection_status_idx');
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
            $table->foreignUuid('workspace_id')
                ->nullable()
                ->constrained('archivd_workspaces')
                ->cascadeOnDelete();
            $table->foreignUuid('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignUuid('folder_id')
                ->nullable()
                ->constrained('archivd_folders')
                ->nullOnDelete();
            $table->string('name');
            $table->string('disk');
            $table->string('path');
            $table->unsignedBigInteger('size')->default(0);
            $table->string('mime')->nullable();
            // sha256 hex; indexed for dedup + the DMCA hash blocklist lookup.
            $table->string('checksum')->nullable();
            $table->string('visibility', 32)->default('private');
            $table->unsignedBigInteger('download_count')->default(0);
            $table->unsignedBigInteger('view_count')->default(0);
            $table->timestamp('last_downloaded_at')->nullable();
            $table->timestamp('last_viewed_at')->nullable();
            // Cached presigned URL + its expiry; regenerated on miss.
            $table->text('signed_download_url')->nullable();
            $table->timestamp('signed_download_expires_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['user_id', 'folder_id']);
            $table->index(['workspace_id', 'folder_id']);
            $table->index('checksum');
            $table->index(['visibility']);
            $table->unique(['disk', 'path']);
        });

        Schema::create('archivd_uploads', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('workspace_id')
                ->nullable()
                ->constrained('archivd_workspaces')
                ->cascadeOnDelete();
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
            // Chunk layout frozen at init so config drift never breaks resume math.
            $table->unsignedBigInteger('chunk_size')->nullable();
            $table->unsignedInteger('part_count')->nullable();
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
            $table->index(['workspace_id', 'status']);
            // Prune scheduler filters pending sessions on either arm of an OR,
            // so each arm gets its own single-purpose index.
            $table->index(['status', 'expires_at']);
            $table->index(['status', 'updated_at']);
        });

        Schema::create('archivd_user_quotas', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('quota_bytes')->default(0);
            $table->string('source', 64)->nullable();
            $table->uuid('source_id')->nullable();
            $table->timestamps();

            $table->index(['source', 'source_id']);
        });

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

        Schema::create('archivd_hash_blocklist', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            // sha256 hex of a file that must never be publicly shareable (DMCA).
            $table->string('sha256', 64)->unique();
            $table->string('reason')->nullable();
            $table->uuid('created_by')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archivd_hash_blocklist');
        Schema::dropIfExists('archivd_file_variants');
        Schema::dropIfExists('archivd_user_quotas');
        Schema::dropIfExists('archivd_uploads');
        Schema::dropIfExists('archivd_files');
        Schema::dropIfExists('archivd_folders');
        Schema::dropIfExists('archivd_workspace_invites');
        Schema::dropIfExists('archivd_workspace_members');
        Schema::dropIfExists('archivd_workspaces');
    }
};
