<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vtual_platforms', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('key', 32)->unique(); // youtube|twitch|kick
            $table->string('name');
            $table->boolean('enabled')->default(true);
            $table->boolean('supports_live')->default(true);
            $table->boolean('supports_vod')->default(false);
            $table->timestamps();
        });

        Schema::create('vtual_creators', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('slug')->unique();
            $table->string('display_name');
            $table->text('bio')->nullable();
            $table->string('avatar_url')->nullable();
            $table->string('status', 32)->default('active'); // active|archived
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });

        Schema::create('vtual_creator_members', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('creator_id')->constrained('vtual_creators')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 32); // owner|editor|viewer
            $table->timestamps();

            $table->unique(['creator_id', 'user_id']);
            $table->index(['user_id', 'role']);
        });

        Schema::create('vtual_managements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('logo_url')->nullable();
            $table->string('status', 32)->default('active'); // active|archived
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });

        Schema::create('vtual_management_members', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('management_id')->constrained('vtual_managements')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 32); // owner|admin|staff
            $table->timestamps();

            $table->unique(['management_id', 'user_id']);
            $table->index(['user_id', 'role']);
        });

        Schema::create('vtual_management_creators', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('management_id')->constrained('vtual_managements')->cascadeOnDelete();
            $table->foreignUuid('creator_id')->constrained('vtual_creators')->cascadeOnDelete();
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->timestamps();

            $table->unique(['management_id', 'creator_id']);
            $table->index(['creator_id', 'left_at']);
        });

        Schema::create('vtual_channels', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('creator_id')->constrained('vtual_creators')->cascadeOnDelete();
            $table->foreignUuid('platform_id')->constrained('vtual_platforms')->restrictOnDelete();
            $table->string('external_id');
            $table->string('handle')->nullable();
            $table->string('display_name')->nullable();
            $table->string('url')->nullable();
            $table->string('status', 32)->default('pending'); // pending|active|disconnected|archived
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['platform_id', 'external_id']);
            $table->index(['creator_id', 'status']);
        });

        Schema::create('vtual_contents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('creator_id')->constrained('vtual_creators')->cascadeOnDelete();
            $table->foreignUuid('channel_id')->constrained('vtual_channels')->cascadeOnDelete();
            $table->foreignUuid('platform_id')->constrained('vtual_platforms')->restrictOnDelete();
            $table->string('external_id');
            $table->string('type', 32); // live|archive|vod
            $table->string('status', 32)->default('unknown'); // scheduled|live|ended|archived|uploaded|unknown
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->string('url')->nullable();
            $table->unsignedInteger('concurrent_viewers')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['platform_id', 'external_id']);
            $table->index(['creator_id', 'status']);
            $table->index(['channel_id', 'status']);
            $table->index(['type', 'status']);
            $table->index('started_at');
        });

        Schema::create('vtual_external_links', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuidMorphs('linkable'); // creators / managements
            $table->string('label');
            $table->string('url');
            $table->string('kind', 32)->default('other'); // social|donation|website|other
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['linkable_type', 'linkable_id', 'sort_order'], 'vtual_ext_links_linkable_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vtual_external_links');
        Schema::dropIfExists('vtual_contents');
        Schema::dropIfExists('vtual_channels');
        Schema::dropIfExists('vtual_management_creators');
        Schema::dropIfExists('vtual_management_members');
        Schema::dropIfExists('vtual_managements');
        Schema::dropIfExists('vtual_creator_members');
        Schema::dropIfExists('vtual_creators');
        Schema::dropIfExists('vtual_platforms');
    }
};
