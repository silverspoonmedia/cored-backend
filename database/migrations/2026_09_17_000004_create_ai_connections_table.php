<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_connections', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('slug', 64)->unique();
            $table->string('label', 128);
            // openai_compatible — room to grow for native provider shapes.
            $table->string('driver', 32)->default('openai_compatible');
            $table->string('endpoint', 255);
            // Envelope-encrypted JSON ({api_key}). Never plaintext.
            $table->text('secret_payload');
            $table->string('secret_fingerprint', 64);
            $table->string('secret_hint', 16)->nullable();
            // Model used by the manual Test probe (chat-completions ping).
            $table->string('test_model', 128);
            // Transport overrides; null falls back to the consumer's own defaults.
            $table->unsignedInteger('timeout_seconds')->nullable();
            $table->unsignedInteger('connect_timeout_seconds')->nullable();
            $table->unsignedInteger('max_retries')->nullable();
            $table->json('extra_headers')->nullable();
            $table->boolean('is_default')->default(false);
            $table->string('status', 32)->default('pending'); // pending|active|disabled
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->string('last_error_message')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'is_default'], 'ai_connections_status_default_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_connections');
    }
};
