<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('archivd_uploads', function (Blueprint $table): void {
            // Prune scheduler (`archivd:prune-stale-uploads`) filters pending
            // sessions on `expires_at < X OR updated_at < Y` hourly. Two separate
            // single-purpose indexes let the optimizer satisfy either arm of the OR.
            $table->index(['status', 'expires_at']);
            $table->index(['status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::table('archivd_uploads', function (Blueprint $table): void {
            $table->dropIndex(['status', 'expires_at']);
            $table->dropIndex(['status', 'updated_at']);
        });
    }
};
