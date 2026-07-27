<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('microstock_asset_metadata', function (Blueprint $table): void {
            $table->string('category')->nullable()->after('keywords');
            $table->text('description')->nullable()->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('microstock_asset_metadata', function (Blueprint $table): void {
            $table->dropColumn(['category', 'description']);
        });
    }
};
