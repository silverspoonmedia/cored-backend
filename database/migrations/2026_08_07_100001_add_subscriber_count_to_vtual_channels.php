<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vtual_channels', function (Blueprint $table): void {
            $table->unsignedBigInteger('subscriber_count')->nullable()->after('platform_created_at');
        });
    }

    public function down(): void
    {
        Schema::table('vtual_channels', function (Blueprint $table): void {
            $table->dropColumn('subscriber_count');
        });
    }
};
