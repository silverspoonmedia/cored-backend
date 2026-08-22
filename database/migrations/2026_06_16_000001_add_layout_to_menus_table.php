<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('menus', 'layout')) {
            Schema::table('menus', function (Blueprint $table) {
                $table->string('layout', 32)->default('sidebar')->after('parent_id');
            });
        }

        $indexNames = collect(Schema::getIndexes('menus'))->pluck('name');

        if (! $indexNames->contains('menus_parent_id_index')) {
            Schema::table('menus', function (Blueprint $table) {
                $table->index('parent_id', 'menus_parent_id_index');
            });
        }

        if ($indexNames->contains('menus_parent_id_type_order_unique')) {
            Schema::table('menus', function (Blueprint $table) {
                $table->dropUnique(['parent_id', 'type', 'order']);
            });
        }

        if (! $indexNames->contains('menus_layout_parent_id_type_order_unique')) {
            Schema::table('menus', function (Blueprint $table) {
                $table->unique(['layout', 'parent_id', 'type', 'order']);
            });
        }
    }

    public function down(): void
    {
        $indexNames = collect(Schema::getIndexes('menus'))->pluck('name');

        if ($indexNames->contains('menus_layout_parent_id_type_order_unique')) {
            Schema::table('menus', function (Blueprint $table) {
                $table->dropUnique(['layout', 'parent_id', 'type', 'order']);
            });
        }

        if (! $indexNames->contains('menus_parent_id_type_order_unique')) {
            Schema::table('menus', function (Blueprint $table) {
                $table->unique(['parent_id', 'type', 'order']);
            });
        }

        if ($indexNames->contains('menus_parent_id_index')) {
            Schema::table('menus', function (Blueprint $table) {
                $table->dropIndex('menus_parent_id_index');
            });
        }

        if (Schema::hasColumn('menus', 'layout')) {
            Schema::table('menus', function (Blueprint $table) {
                $table->dropColumn('layout');
            });
        }
    }
};
