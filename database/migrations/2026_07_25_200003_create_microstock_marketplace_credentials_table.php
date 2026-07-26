<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('microstock_marketplace_credentials', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('marketplace');
            $table->string('label');
            $table->text('secret_payload'); // envelope-encrypted JSON (host/credentials)
            $table->timestamps();

            $table->unique(['user_id', 'marketplace', 'label'], 'ms_cred_user_market_label_uq');
            $table->index(['user_id', 'marketplace'], 'ms_cred_user_market_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('microstock_marketplace_credentials');
    }
};
