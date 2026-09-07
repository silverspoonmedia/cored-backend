<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vtual_eventsub_subscriptions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('channel_id')->constrained('vtual_channels')->cascadeOnDelete();
            $table->string('external_id', 64);
            $table->string('event_type', 64);
            $table->string('twitch_subscription_id', 64)->nullable();
            $table->string('secret', 128);
            $table->string('status', 32);
            $table->timestamp('lease_expires_at')->nullable();
            $table->timestamp('subscribed_at')->nullable();
            $table->timestamp('last_notify_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            // One subscription row per channel per event type.
            $table->unique(['channel_id', 'event_type'], 'vtual_eventsub_channel_type_unique');
            $table->unique('twitch_subscription_id', 'vtual_eventsub_twitch_id_unique');
            $table->index(['status', 'lease_expires_at'], 'vtual_eventsub_status_lease_idx');
            $table->index(['external_id', 'event_type', 'status'], 'vtual_eventsub_ext_type_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vtual_eventsub_subscriptions');
    }
};
