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
            $table->index('payment_channel_code');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['payment_channel_code']);
            $table->dropColumn('payment_channel_code');
        });
    }
};
