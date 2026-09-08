<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vtual_creator_management_invites', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('management_id')->constrained('vtual_managements')->cascadeOnDelete();
            $table->string('email');
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            // existing = link the invitee's profile; new = create one on accept.
            $table->string('mode', 16); // existing|new
            $table->foreignUuid('creator_id')->nullable()->constrained('vtual_creators')->cascadeOnDelete();
            $table->string('new_display_name')->nullable();
            $table->string('token', 64)->unique();
            $table->string('status');
            $table->foreignUuid('invited_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index(['management_id', 'email', 'status'], 'vcmi_management_email_status_index');
            $table->index(['creator_id', 'status'], 'vcmi_creator_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vtual_creator_management_invites');
    }
};
