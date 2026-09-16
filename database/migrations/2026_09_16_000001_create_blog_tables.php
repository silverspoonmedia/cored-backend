<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_media', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('disk');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->timestamps();

            $table->index('uploaded_by');
        });

        Schema::create('blog_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('blog_tags', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('blog_posts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->longText('content')->nullable();
            $table->string('status');
            $table->timestamp('published_at')->nullable();
            $table->foreignUuid('featured_media_id')->nullable()->constrained('blog_media')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['status', 'published_at']);
        });

        Schema::create('blog_category_post', function (Blueprint $table) {
            $table->foreignUuid('category_id')->constrained('blog_categories')->cascadeOnDelete();
            $table->foreignUuid('post_id')->constrained('blog_posts')->cascadeOnDelete();

            $table->primary(['category_id', 'post_id']);
        });

        Schema::create('blog_post_tag', function (Blueprint $table) {
            $table->foreignUuid('post_id')->constrained('blog_posts')->cascadeOnDelete();
            $table->foreignUuid('tag_id')->constrained('blog_tags')->cascadeOnDelete();

            $table->primary(['post_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_post_tag');
        Schema::dropIfExists('blog_category_post');
        Schema::dropIfExists('blog_posts');
        Schema::dropIfExists('blog_tags');
        Schema::dropIfExists('blog_categories');
        Schema::dropIfExists('blog_media');
    }
};
