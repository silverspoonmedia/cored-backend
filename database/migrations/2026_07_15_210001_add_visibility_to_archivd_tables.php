<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('archivd_folders', function (Blueprint $table): void {
            $table->string('visibility', 32)->default('private')->after('order');
            $table->index(['visibility']);
        });

        Schema::table('archivd_files', function (Blueprint $table): void {
            $table->string('visibility', 32)->default('private')->after('checksum');
            $table->index(['visibility']);
        });
    }

    public function down(): void
    {
        Schema::table('archivd_folders', function (Blueprint $table): void {
            $table->dropIndex(['visibility']);
            $table->dropColumn('visibility');
        });

        Schema::table('archivd_files', function (Blueprint $table): void {
            $table->dropIndex(['visibility']);
            $table->dropColumn('visibility');
        });
    }
};
