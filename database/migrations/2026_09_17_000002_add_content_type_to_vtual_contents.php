<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vtual_contents', function (Blueprint $table): void {
            $table->string('content_type', 16)->nullable()->after('content_category_detail');
            $table->index('content_type');
        });
    }

    public function down(): void
    {
        Schema::table('vtual_contents', function (Blueprint $table): void {
            $table->dropIndex(['content_type']);
            $table->dropColumn('content_type');
        });
    }
};
