<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('file_hosting_files', function (Blueprint $table): void {
            // sha256 lookup for dedup + DMCA hash blocklist. `checksum` column
            // already exists; this adds the index for fast exact-match queries.
            $table->index('checksum');
        });
    }

    public function down(): void
    {
        Schema::table('file_hosting_files', function (Blueprint $table): void {
            $table->dropIndex(['checksum']);
        });
    }
};
