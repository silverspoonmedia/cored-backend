<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vtual_creator_claims', function (Blueprint $table): void {
            $table->string('closed_reason', 32)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('vtual_creator_claims', function (Blueprint $table): void {
            $table->dropColumn('closed_reason');
        });
    }
};
