<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('archivd_workspaces', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('status', 32)->default('active'); // active|archived
            $table->timestamps();

            // Multi-owned: many workspaces per owner; slot counts use (owner, status).
            $table->index(['owner_user_id', 'status'], 'fh_workspaces_owner_status_idx');
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

        Schema::table('archivd_folders', function (Blueprint $table): void {
            $table->foreignUuid('workspace_id')
                ->nullable()
                ->after('user_id')
                ->constrained('archivd_workspaces')
                ->cascadeOnDelete();
            $table->index(['workspace_id', 'parent_id']);
        });

        Schema::table('archivd_files', function (Blueprint $table): void {
            $table->foreignUuid('workspace_id')
                ->nullable()
                ->after('user_id')
                ->constrained('archivd_workspaces')
                ->cascadeOnDelete();
            $table->foreignUuid('created_by')
                ->nullable()
                ->after('workspace_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->index(['workspace_id', 'folder_id']);
        });

        Schema::table('archivd_uploads', function (Blueprint $table): void {
            $table->foreignUuid('workspace_id')
                ->nullable()
                ->after('user_id')
                ->constrained('archivd_workspaces')
                ->cascadeOnDelete();
            $table->index(['workspace_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('archivd_uploads', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('workspace_id');
        });

        Schema::table('archivd_files', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('workspace_id');
        });

        Schema::table('archivd_folders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('workspace_id');
        });

        Schema::dropIfExists('archivd_workspace_invites');
        Schema::dropIfExists('archivd_workspace_members');
        Schema::dropIfExists('archivd_workspaces');
    }
};
