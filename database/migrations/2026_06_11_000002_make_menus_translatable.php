<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            $table->json('name_json')->nullable();
            $table->json('description')->nullable();
        });

        foreach (DB::table('menus')->get(['id', 'name']) as $menu) {
            $plainName = $menu->name;

            DB::table('menus')->where('id', $menu->id)->update([
                'name_json' => json_encode([
                    'en' => $plainName,
                    'id' => $plainName,
                ], JSON_UNESCAPED_UNICODE),
            ]);
        }

        Schema::table('menus', function (Blueprint $table) {
            $table->dropColumn('name');
        });

        Schema::table('menus', function (Blueprint $table) {
            $table->renameColumn('name_json', 'name');
        });
    }

    public function down(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            $table->string('name_plain')->nullable();
        });

        foreach (DB::table('menus')->get(['id', 'name']) as $menu) {
            $translations = json_decode($menu->name, true);
            $plainName = is_array($translations)
                ? ($translations['en'] ?? reset($translations) ?: '')
                : (string) $menu->name;

            DB::table('menus')->where('id', $menu->id)->update([
                'name_plain' => $plainName,
            ]);
        }

        Schema::table('menus', function (Blueprint $table) {
            $table->dropColumn(['name', 'description']);
        });

        Schema::table('menus', function (Blueprint $table) {
            $table->renameColumn('name_plain', 'name');
        });
    }
};
