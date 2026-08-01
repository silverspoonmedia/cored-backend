<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->foreignUuid('payment_channel_route_snapshot_id')
                ->nullable()
                ->after('payment_channel_route_id')
                ->constrained('payment_channel_route_snapshots')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropForeign(['payment_channel_route_snapshot_id']);
            $table->dropColumn('payment_channel_route_snapshot_id');
        });
    }
};
