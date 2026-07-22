<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vtual_platform_credentials', function (Blueprint $table): void {
            $table->text('runtime_payload')->nullable()->after('secret_hint');
            $table->timestamp('verified_at')->nullable()->after('last_error_message');
        });
    }

    public function down(): void
    {
        Schema::table('vtual_platform_credentials', function (Blueprint $table): void {
            $table->dropColumn(['runtime_payload', 'verified_at']);
        });
    }
};
