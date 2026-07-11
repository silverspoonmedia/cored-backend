<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['payment_gateway_id']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->uuid('payment_gateway_id')->nullable()->change();
            $table->foreign('payment_gateway_id')
                ->references('id')
                ->on('payment_gateways')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['payment_gateway_id']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->uuid('payment_gateway_id')->nullable(false)->change();
            $table->foreign('payment_gateway_id')
                ->references('id')
                ->on('payment_gateways')
                ->restrictOnDelete();
        });
    }
};
