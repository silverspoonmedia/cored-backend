<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->string('plan_kind', 32)->nullable()->after('entitlement');
            $table->unsignedBigInteger('storage_bytes')->default(0)->after('plan_kind');
        });

        Schema::table('service_entitlements', function (Blueprint $table): void {
            $table->unsignedInteger('quantity')->default(1)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('service_entitlements', function (Blueprint $table): void {
            $table->dropColumn('quantity');
        });

        Schema::table('services', function (Blueprint $table): void {
            $table->dropColumn(['plan_kind', 'storage_bytes']);
        });
    }
};
