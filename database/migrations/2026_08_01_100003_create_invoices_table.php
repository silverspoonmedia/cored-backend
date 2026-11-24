<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invoices, squashed.
 *
 * The create carries the whole final row: `payment_gateway_id` nullable from the
 * start, the channel link trio (`payment_channel_code`,
 * `payment_channel_route_id`, `payment_channel_route_snapshot_id`) and the
 * gateway payloads as `text` because they hold envelope-encrypted strings, not
 * JSON. It sits after the payment channel tables so every FK is declarable in
 * one place.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('invoiceable_id');
            $table->string('invoiceable_type');
            $table->foreignUuid('payment_gateway_id')->nullable()->constrained('payment_gateways')->restrictOnDelete();
            $table->string('invoice_number')->unique();
            $table->string('external_transaction_id')->nullable();
            $table->string('payment_method');
            $table->string('payment_channel_code', 64)->nullable();
            $table->foreignUuid('payment_channel_route_id')->nullable()->constrained('payment_channel_routes')->nullOnDelete();
            $table->foreignUuid('payment_channel_route_snapshot_id')->nullable()->constrained('payment_channel_route_snapshots')->restrictOnDelete();
            $table->decimal('subtotal', 15, 2);
            $table->decimal('payment_channel_fee', 15, 2)->default(0);
            $table->decimal('customer_gross_total', 15, 2);
            $table->string('currency', 3)->default('IDR');
            $table->string('status');
            // Envelope-encrypted ciphertext, never JSON - see envelope-encryption.mdc.
            $table->text('gateway_request_payload')->nullable();
            $table->text('gateway_response_payload')->nullable();
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
            $table->index('payment_channel_code');
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
