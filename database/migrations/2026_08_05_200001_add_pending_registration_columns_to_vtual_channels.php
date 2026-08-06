<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vtual_channels', function (Blueprint $table): void {
            $table->string('profile_input', 512)->nullable()->after('external_id');
            $table->text('resolve_error')->nullable()->after('profile_input');
        });
    }

    public function down(): void
    {
        Schema::table('vtual_channels', function (Blueprint $table): void {
            $table->dropColumn(['profile_input', 'resolve_error']);
        });
    }
};
