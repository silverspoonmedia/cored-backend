<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Uploader tags persisted per content row (`snippet.tags` on YouTube, the
 * category/game list on Twitch).
 *
 * Why store them: the classifier reads `tags` as a real signal — the uploader's
 * own genre labels often name the song when the title is a bare track name — but
 * they only ever existed as a transient mapper input. `vtual:classify-contents`
 * works from stored rows, so it could not see them, and `--refresh` after a
 * classifier change could not reproduce the verdict the hydrate path reached.
 *
 * Internal signal only: never expose this through `ContentResource` or
 * `PublicContentResource`. It is evidence for classification, not catalogue data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vtual_contents', function (Blueprint $table): void {
            $table->json('tags')->nullable()->after('region_blocked');
        });
    }

    public function down(): void
    {
        Schema::table('vtual_contents', function (Blueprint $table): void {
            $table->dropColumn('tags');
        });
    }
};
