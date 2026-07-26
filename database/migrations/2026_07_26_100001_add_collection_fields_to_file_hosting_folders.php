<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Microstock "collection" state on File Hosting folders. Only folders inside a
 * WorkspaceKind::Microstock workspace use these columns; regular File Hosting
 * folders keep them NULL. Lifecycle: draft -> locked -> processing -> review ->
 * exported (failed on error). A locked collection no longer accepts uploads.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('file_hosting_folders', function (Blueprint $table): void {
            $table->string('collection_status', 32)->nullable()->after('visibility');
            $table->text('collection_hint')->nullable()->after('collection_status');
            $table->timestamp('collection_locked_at')->nullable()->after('collection_hint');
            $table->string('collection_csv_path')->nullable()->after('collection_locked_at');
            $table->index(['workspace_id', 'collection_status'], 'fh_folders_workspace_collection_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('file_hosting_folders', function (Blueprint $table): void {
            $table->dropIndex('fh_folders_workspace_collection_status_idx');
            $table->dropColumn([
                'collection_status',
                'collection_hint',
                'collection_locked_at',
                'collection_csv_path',
            ]);
        });
    }
};
