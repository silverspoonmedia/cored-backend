<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vtual_channels', function (Blueprint $table): void {
            $table->text('description')->nullable()->after('display_name');
            $table->string('avatar_url')->nullable()->after('description');
            $table->string('banner_url')->nullable()->after('avatar_url');
            $table->timestamp('platform_created_at')->nullable()->after('url');
        });
    }

    public function down(): void
    {
        Schema::table('vtual_channels', function (Blueprint $table): void {
            $table->dropColumn([
                'description',
                'avatar_url',
                'banner_url',
                'platform_created_at',
            ]);
        });
    }
};
