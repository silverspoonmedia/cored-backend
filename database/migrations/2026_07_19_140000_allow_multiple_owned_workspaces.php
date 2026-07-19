<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-owned team workspaces: drop lifetime unique(owner_user_id).
 * Slot counting uses (owner_user_id, status).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('file_hosting_workspaces', function (Blueprint $table): void {
            $table->dropUnique(['owner_user_id']);
            // (status, owner_user_id) already exists from create migration; ensure owner-first for counts.
            $table->index(['owner_user_id', 'status'], 'fh_workspaces_owner_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('file_hosting_workspaces', function (Blueprint $table): void {
            $table->dropIndex('fh_workspaces_owner_status_idx');
            $table->unique('owner_user_id');
        });
    }
};
