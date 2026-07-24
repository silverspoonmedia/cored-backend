<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vtual_platform_credentials', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('platform_id')
                ->constrained('vtual_platforms')
                ->cascadeOnDelete();
            $table->string('label');
            $table->string('type', 32); // api_key|oauth_app|bearer
            $table->text('secret_payload'); // envelope-encrypted JSON
            $table->string('secret_fingerprint', 64);
            $table->string('secret_hint', 16)->nullable();
            $table->unsignedInteger('priority')->default(100);
            $table->string('status', 32)->default('active'); // active|disabled|revoked
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->string('last_error_message')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['platform_id', 'label'], 'vtual_plat_cred_platform_label_uq');
            $table->index(['platform_id', 'status', 'priority'], 'vtual_plat_cred_platform_status_prio_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vtual_platform_credentials');
    }
};
