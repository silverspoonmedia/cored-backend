<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->string('domain_kind', 32)->default('core')->after('guard_name');
            $table->index('domain_kind');
            $table->dropUnique(['level']);
            $table->unique(['domain_kind', 'level']);
        });

        Schema::table('permissions', function (Blueprint $table): void {
            $table->string('domain_kind', 32)->default('core')->after('guard_name');
            $table->index('domain_kind');
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->dropUnique(['domain_kind', 'level']);
            $table->dropIndex(['domain_kind']);
            $table->dropColumn('domain_kind');
            $table->unique('level');
        });

        Schema::table('permissions', function (Blueprint $table): void {
            $table->dropIndex(['domain_kind']);
            $table->dropColumn('domain_kind');
        });
    }
};
