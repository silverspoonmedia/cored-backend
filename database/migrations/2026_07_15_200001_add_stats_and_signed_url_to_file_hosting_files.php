<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('file_hosting_files', function (Blueprint $table): void {
            $table->unsignedBigInteger('download_count')->default(0)->after('checksum');
            $table->unsignedBigInteger('view_count')->default(0)->after('download_count');
            $table->timestamp('last_downloaded_at')->nullable()->after('view_count');
            $table->timestamp('last_viewed_at')->nullable()->after('last_downloaded_at');
            $table->text('signed_download_url')->nullable()->after('last_viewed_at');
            $table->timestamp('signed_download_expires_at')->nullable()->after('signed_download_url');
        });
    }

    public function down(): void
    {
        Schema::table('file_hosting_files', function (Blueprint $table): void {
            $table->dropColumn([
                'download_count',
                'view_count',
                'last_downloaded_at',
                'last_viewed_at',
                'signed_download_url',
                'signed_download_expires_at',
            ]);
        });
    }
};
