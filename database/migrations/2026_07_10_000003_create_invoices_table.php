<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('invoiceable_id');
            $table->string('invoiceable_type');
            $table->foreignUuid('payment_gateway_id')->constrained('payment_gateways')->restrictOnDelete();
            $table->string('invoice_number')->unique();
            $table->string('external_transaction_id')->nullable();
            $table->string('payment_method');
            $table->decimal('subtotal', 15, 2);
            $table->decimal('payment_channel_fee', 15, 2)->default(0);
            $table->decimal('customer_gross_total', 15, 2);
            $table->string('currency', 3)->default('IDR');
            $table->string('status');
            $table->json('gateway_request_payload')->nullable();
            $table->json('gateway_response_payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('withdrawable_at')->nullable();
            $table->string('disbursement_status', 32)->default('none');
            $table->timestamp('disbursement_processing_at')->nullable();
            $table->timestamp('disbursed_at')->nullable();
            $table->string('disbursement_reference')->nullable();
            $table->timestamp('funds_last_reconciled_at')->nullable();
            $table->timestamps();

            $table->index(['invoiceable_id', 'invoiceable_type']);
            $table->index('status');
            $table->index('disbursement_status', 'invoices_disbursement_status_idx');
            $table->index(['withdrawable_at', 'disbursement_status'], 'invoices_withdrawable_disbursement_idx');
            $table->unique(['payment_gateway_id', 'external_transaction_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
