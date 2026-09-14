<?php

use App\Domain\Navigation\Enums\MenuAudience;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Collapse the `is_authenticate` / `is_guest_only` boolean pair into a single
 * `audience` column.
 *
 * The two nullable booleans encoded one audience axis in two flags (with `null`
 * behaving as `false`), so `null` and `false` were two representations of the
 * same meaning and the illegal `true`/`true` combination had to be rejected by
 * a validator rule. `audience` carries the axis explicitly, and role
 * restrictions live inside it (`role`) instead of fighting with it.
 *
 * Backfill order matters: guest-only wins over the authenticate flag, then a
 * parentless-for-guests row (`is_authenticate = false`) becomes `all`, and
 * finally any row with role pivots becomes `role` so the effective visibility
 * of pre-existing role-restricted rows is preserved.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menus', function (Blueprint $table): void {
            $table->string('audience', 16)->default(MenuAudience::Auth->value);
        });

        DB::table('menus')
            ->where('is_guest_only', true)
            ->update(['audience' => MenuAudience::Guest->value]);

        DB::table('menus')
            ->where(function ($query): void {
                $query->whereNull('is_guest_only')->orWhere('is_guest_only', false);
            })
            ->where('is_authenticate', false)
            ->update(['audience' => MenuAudience::All->value]);

        $roleMenuIds = DB::table('menu_roles')->distinct()->pluck('menu_id')->all();

        if ($roleMenuIds !== []) {
            DB::table('menus')
                ->whereIn('id', $roleMenuIds)
                ->where('audience', '!=', MenuAudience::Guest->value)
                ->update(['audience' => MenuAudience::Role->value]);
        }

        Schema::table('menus', function (Blueprint $table): void {
            $table->dropColumn(['is_authenticate', 'is_guest_only']);
        });
    }

    public function down(): void
    {
        Schema::table('menus', function (Blueprint $table): void {
            $table->boolean('is_authenticate')->nullable();
            $table->boolean('is_guest_only')->nullable();
        });

        DB::table('menus')->update([
            'is_authenticate' => true,
            'is_guest_only' => false,
        ]);

        DB::table('menus')
            ->where('audience', MenuAudience::All->value)
            ->update(['is_authenticate' => false]);

        DB::table('menus')
            ->where('audience', MenuAudience::Guest->value)
            ->update(['is_authenticate' => false, 'is_guest_only' => true]);

        Schema::table('menus', function (Blueprint $table): void {
            $table->dropColumn('audience');
        });
    }
};
