<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vtual_channels', function (Blueprint $table): void {
            $table->string('profile_fingerprint', 128)->nullable()->after('profile_input');
            $table->index(['creator_id', 'platform_id', 'profile_fingerprint'], 'vtual_ch_creator_plat_fp_idx');
        });
    }

    public function down(): void
    {
        Schema::table('vtual_channels', function (Blueprint $table): void {
            $table->dropIndex('vtual_ch_creator_plat_fp_idx');
            $table->dropColumn('profile_fingerprint');
        });
    }
};
