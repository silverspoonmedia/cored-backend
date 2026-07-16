<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rate_snapshots', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->char('base_currency', 3);
            $table->char('quote_currency', 3);
            $table->decimal('rate', 18, 8);
            $table->string('provider', 64);
            $table->date('as_of_date')->nullable();
            $table->timestamp('fetched_at');
            $table->json('source_payload')->nullable();
            $table->timestamps();

            $table->foreign('base_currency')->references('code')->on('currencies')->cascadeOnUpdate();
            $table->foreign('quote_currency')->references('code')->on('currencies')->cascadeOnUpdate();
            $table->index(['base_currency', 'quote_currency', 'provider', 'fetched_at'], 'fx_snapshots_pair_provider_fetched_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rate_snapshots');
    }
};
