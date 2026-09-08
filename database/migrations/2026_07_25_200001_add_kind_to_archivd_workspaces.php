<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Workspace kind: `team` (user-created, slot-counted) vs system workspaces
 * (e.g. `microstock`, slot-exempt). Default `team` keeps existing rows valid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('archivd_workspaces', function (Blueprint $table): void {
            $table->string('kind', 32)->default('team')->after('status');
            $table->index(['owner_user_id', 'kind', 'status'], 'fh_workspaces_owner_kind_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('archivd_workspaces', function (Blueprint $table): void {
            $table->dropIndex('fh_workspaces_owner_kind_status_idx');
            $table->dropColumn('kind');
        });
    }
};
