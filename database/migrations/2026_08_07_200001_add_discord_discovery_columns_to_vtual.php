<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vtual_channels', function (Blueprint $table): void {
            $table->string('visibility', 32)->default('public')->after('status');
            $table->string('discovery_source', 32)->nullable()->after('visibility');

            $table->index(['creator_id', 'visibility'], 'vtual_channels_creator_visibility_idx');
        });

        Schema::table('vtual_creator_claims', function (Blueprint $table): void {
            $table->foreignUuid('verified_channel_id')
                ->nullable()
                ->after('channel_id')
                ->constrained('vtual_channels')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vtual_creator_claims', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('verified_channel_id');
        });

        Schema::table('vtual_channels', function (Blueprint $table): void {
            $table->dropIndex('vtual_channels_creator_visibility_idx');
            $table->dropColumn(['visibility', 'discovery_source']);
        });
    }
};
