<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-owned team workspaces: drop lifetime unique(owner_user_id).
 * Slot counting uses (owner_user_id, status).
 *
 * MySQL: the FK on owner_user_id is backed by that unique index, so drop FK
 * before dropUnique, then re-add the FK (non-unique index is enough).
 * Idempotent when create migration already omitted the unique.
 */
return new class extends Migration
{
    public function up(): void
    {
        $indexes = collect(Schema::getIndexes('file_hosting_workspaces'));

        $uniqueOwner = $indexes->first(
            fn (array $index): bool => ($index['unique'] ?? false)
                && ($index['columns'] ?? []) === ['owner_user_id'],
        );

        $hasOwnerStatus = $indexes->contains(
            fn (array $index): bool => ($index['name'] ?? '') === 'fh_workspaces_owner_status_idx'
                || ($index['columns'] ?? []) === ['owner_user_id', 'status'],
        );

        if ($uniqueOwner !== null) {
            Schema::table('file_hosting_workspaces', function (Blueprint $table): void {
                $table->dropForeign(['owner_user_id']);
            });

            Schema::table('file_hosting_workspaces', function (Blueprint $table) use ($uniqueOwner): void {
                $table->dropUnique($uniqueOwner['name'] ?? ['owner_user_id']);
            });

            Schema::table('file_hosting_workspaces', function (Blueprint $table): void {
                $table->foreign('owner_user_id')
                    ->references('id')
                    ->on('users')
                    ->cascadeOnDelete();
            });
        }

        if (! $hasOwnerStatus) {
            Schema::table('file_hosting_workspaces', function (Blueprint $table): void {
                $table->index(['owner_user_id', 'status'], 'fh_workspaces_owner_status_idx');
            });
        }
    }

    public function down(): void
    {
        $indexes = collect(Schema::getIndexes('file_hosting_workspaces'));

        $hasOwnerStatus = $indexes->contains(
            fn (array $index): bool => ($index['name'] ?? '') === 'fh_workspaces_owner_status_idx'
                || ($index['columns'] ?? []) === ['owner_user_id', 'status'],
        );

        $hasUniqueOwner = $indexes->contains(
            fn (array $index): bool => ($index['unique'] ?? false)
                && ($index['columns'] ?? []) === ['owner_user_id'],
        );

        if ($hasOwnerStatus) {
            Schema::table('file_hosting_workspaces', function (Blueprint $table): void {
                $table->dropIndex('fh_workspaces_owner_status_idx');
            });
        }

        if (! $hasUniqueOwner) {
            Schema::table('file_hosting_workspaces', function (Blueprint $table): void {
                $table->dropForeign(['owner_user_id']);
            });

            Schema::table('file_hosting_workspaces', function (Blueprint $table): void {
                $table->unique('owner_user_id');
                $table->foreign('owner_user_id')
                    ->references('id')
                    ->on('users')
                    ->cascadeOnDelete();
            });
        }
    }
};
