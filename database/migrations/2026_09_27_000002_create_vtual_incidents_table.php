<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * First-class vTual integration incident.
 *
 * Three stores, three jobs — this table is the *triage* one:
 *   - `last_error` columns (channels, WebSub rows, credentials) keep per-row
 *     state; they answer "is this row healthy", not "what is broken right now".
 *   - `error_logs` captures unexpected exceptions across the whole platform.
 *   - `vtual_incidents` groups a *known integration failure* by fingerprint so a
 *     repeated sync error is one row with an occurrence count and an owner,
 *     instead of N log lines nobody acknowledges.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vtual_incidents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            // sha1 of kind|subject|normalized title|normalized detail — one row
            // per distinct failure, not per occurrence. Severity is deliberately
            // absent: an escalation is the same incident getting worse.
            $table->string('fingerprint', 64)->unique();
            $table->string('kind', 32);
            $table->string('severity', 16);
            $table->string('status', 32)->default('open');

            // Subject the incident is about. All nullable and all nullOnDelete:
            // deleting the channel/credential must not erase the incident
            // history that explains why the deletion happened.
            $table->foreignUuid('platform_id')->nullable()->constrained('vtual_platforms')->nullOnDelete();
            $table->foreignUuid('channel_id')->nullable()->constrained('vtual_channels')->nullOnDelete();
            $table->foreignUuid('credential_id')->nullable()->constrained('vtual_platform_credentials')->nullOnDelete();

            $table->string('title');
            $table->text('detail')->nullable();
            // Redacted before write — never a raw token or secret.
            $table->json('context')->nullable();

            $table->unsignedInteger('occurrences')->default(1);
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');

            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignUuid('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignUuid('resolved_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // Triage list: open first, newest activity first.
            $table->index(['status', 'last_seen_at'], 'vtual_incidents_status_seen_idx');
            $table->index(['severity', 'last_seen_at'], 'vtual_incidents_severity_seen_idx');
            $table->index(['kind', 'last_seen_at'], 'vtual_incidents_kind_seen_idx');
            $table->index('channel_id', 'vtual_incidents_channel_idx');
            $table->index('credential_id', 'vtual_incidents_credential_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vtual_incidents');
    }
};
