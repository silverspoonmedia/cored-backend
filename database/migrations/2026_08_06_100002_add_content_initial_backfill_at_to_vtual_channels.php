<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vtual_channels', function (Blueprint $table): void {
            $table->timestamp('content_initial_backfill_at')->nullable()->after('feed_last_entry_updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('vtual_channels', function (Blueprint $table): void {
            $table->dropColumn('content_initial_backfill_at');
        });
    }
};
