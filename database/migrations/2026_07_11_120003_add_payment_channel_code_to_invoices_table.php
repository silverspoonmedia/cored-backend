<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('payment_channel_code', 64)->nullable()->after('payment_method');
            $table->foreignUuid('payment_channel_route_id')
                ->nullable()
                ->after('payment_channel_code')
                ->constrained('payment_channel_routes')
                ->nullOnDelete();
            $table->index('payment_channel_code');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['payment_channel_route_id']);
            $table->dropIndex(['payment_channel_code']);
            $table->dropColumn(['payment_channel_code', 'payment_channel_route_id']);
        });
    }
};
