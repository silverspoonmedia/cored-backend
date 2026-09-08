<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('archivd_uploads', function (Blueprint $table): void {
            // Resume upload reverted: fingerprint + its lookup index no longer used.
            $table->dropIndex(['user_id', 'content_hash', 'status']);
            $table->dropColumn('content_hash');
        });
    }

    public function down(): void
    {
        Schema::table('archivd_uploads', function (Blueprint $table): void {
            $table->string('content_hash', 64)->nullable()->after('size');
            $table->index(['user_id', 'content_hash', 'status']);
        });
    }
};
