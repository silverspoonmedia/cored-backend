<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vtual_contents', function (Blueprint $table): void {
            $table->string('content_category', 32)->nullable()->after('type');
            $table->string('content_category_detail', 255)->nullable()->after('content_category');
            $table->index('content_category');
        });
    }

    public function down(): void
    {
        Schema::table('vtual_contents', function (Blueprint $table): void {
            $table->dropIndex(['content_category']);
            $table->dropColumn(['content_category', 'content_category_detail']);
        });
    }
};
