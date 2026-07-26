<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('microstock_asset_metadata', function (Blueprint $table): void {
            $table->text('error')->nullable()->after('ai_raw');
        });
    }

    public function down(): void
    {
        Schema::table('microstock_asset_metadata', function (Blueprint $table): void {
            $table->dropColumn('error');
        });
    }
};
