<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->text('gateway_request_payload')->nullable()->change();
            $table->text('gateway_response_payload')->nullable()->change();
        });

        Schema::table('password_reset_tokens', function (Blueprint $table) {
            $table->string('token_blind_index', 64)->nullable()->after('token');
            $table->index('token_blind_index');
        });
    }

    public function down(): void
    {
        Schema::table('password_reset_tokens', function (Blueprint $table) {
            $table->dropIndex(['token_blind_index']);
            $table->dropColumn('token_blind_index');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->json('gateway_request_payload')->nullable()->change();
            $table->json('gateway_response_payload')->nullable()->change();
        });
    }
};
