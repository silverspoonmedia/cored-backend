<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_purchases', function (Blueprint $table): void {
            $table->string('change_type', 32)->nullable()->after('status');
            $table->decimal('list_amount', 15, 2)->nullable()->after('display_amount');
            $table->decimal('credit_amount', 15, 2)->default(0)->after('list_amount');
            $table->decimal('charge_amount', 15, 2)->nullable()->after('credit_amount');
            $table->unsignedInteger('surplus_days')->default(0)->after('charge_amount');
            $table->uuid('previous_entitlement_id')->nullable()->after('surplus_days');
        });
    }

    public function down(): void
    {
        Schema::table('service_purchases', function (Blueprint $table): void {
            $table->dropColumn([
                'change_type',
                'list_amount',
                'credit_amount',
                'charge_amount',
                'surplus_days',
                'previous_entitlement_id',
            ]);
        });
    }
};
