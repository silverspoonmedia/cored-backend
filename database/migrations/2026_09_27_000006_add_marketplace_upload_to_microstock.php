<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marketplace upload (decision: docs/domains/microstock/reference-marketplace-upload.md).
 *
 * Two additions, both driven by the same decision record:
 *
 * 1. `microstock_marketplace_credentials` gains the columns every other
 *    credential store in this app already has (`vtual_platform_credentials`):
 *    a status, the last error, and the last-use/verification timestamps. Without
 *    them the credentials page can only show that a row exists — not whether the
 *    host accepted it, which is the one thing an owner needs before uploading.
 * 2. `microstock_marketplace_uploads` records each **attempt**. The upload is a
 *    copy-out (nothing local is moved or deleted), so this row is the audit
 *    trail and the status the SPA renders.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('microstock_marketplace_credentials', function (Blueprint $table): void {
            // pending (never used) | verified (last transfer succeeded) | failed
            $table->string('status', 16)->default('pending')->after('label');
            $table->text('last_error')->nullable()->after('status');
            $table->timestamp('last_verified_at')->nullable()->after('last_error');
            $table->timestamp('last_used_at')->nullable()->after('last_verified_at');

            $table->index(['user_id', 'status'], 'ms_cred_user_status_idx');
        });

        Schema::create('microstock_marketplace_uploads', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('folder_id')->constrained('archivd_folders')->cascadeOnDelete();
            $table->foreignUuid('credential_id')->constrained('microstock_marketplace_credentials')->cascadeOnDelete();

            $table->string('marketplace');
            // queued | uploading | uploaded | failed
            $table->string('status', 16)->default('queued');
            $table->string('remote_path')->nullable();
            $table->unsignedInteger('file_count')->default(0);
            $table->unsignedBigInteger('byte_count')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['folder_id', 'created_at'], 'ms_upload_folder_created_idx');
            $table->index(['user_id', 'status'], 'ms_upload_user_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('microstock_marketplace_uploads');

        Schema::table('microstock_marketplace_credentials', function (Blueprint $table): void {
            $table->dropIndex('ms_cred_user_status_idx');
            $table->dropColumn(['status', 'last_error', 'last_verified_at', 'last_used_at']);
        });
    }
};
