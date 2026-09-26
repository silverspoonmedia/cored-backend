<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * YouTube `contentDetails.regionRestriction` persisted per content row.
 *
 * Semantics: expose, do not hide. The content stays listed for every visitor;
 * the SPA renders a notice when the viewer's region is blocked (or not in the
 * allowed list). Hiding globally would make content vanish with no visible
 * cause. Twitch has no equivalent field — those rows stay null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vtual_contents', function (Blueprint $table): void {
            $table->json('region_allowed')->nullable()->after('content_type');
            $table->json('region_blocked')->nullable()->after('region_allowed');
        });
    }

    public function down(): void
    {
        Schema::table('vtual_contents', function (Blueprint $table): void {
            $table->dropColumn(['region_allowed', 'region_blocked']);
        });
    }
};
