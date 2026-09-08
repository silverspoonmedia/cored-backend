<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('archivd_folders', function (Blueprint $table): void {
            $table->string('collection_billing_mode', 16)->nullable()->after('collection_hint');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('archivd_folders', function (Blueprint $table): void {
            $table->dropColumn('collection_billing_mode');
        });
    }
};
