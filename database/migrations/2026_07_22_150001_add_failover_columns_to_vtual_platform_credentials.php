<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vtual_platform_credentials', function (Blueprint $table): void {
            $table->unsignedInteger('consecutive_auth_failures')->default(0)->after('last_error_message');
            $table->timestamp('failover_skip_until')->nullable()->after('consecutive_auth_failures');
        });
    }

    public function down(): void
    {
        Schema::table('vtual_platform_credentials', function (Blueprint $table): void {
            $table->dropColumn(['consecutive_auth_failures', 'failover_skip_until']);
        });
    }
};
