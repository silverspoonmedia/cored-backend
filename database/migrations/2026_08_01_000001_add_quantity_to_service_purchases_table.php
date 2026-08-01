<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_purchases', function (Blueprint $table): void {
            $table->unsignedSmallInteger('quantity')->default(1)->after('service_id');
        });
    }

    public function down(): void
    {
        Schema::table('service_purchases', function (Blueprint $table): void {
            $table->dropColumn('quantity');
        });
    }
};
