<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Delegated per-creator capability grants (decision:
 * docs/domains/vtual/reference-capability-grants.md).
 *
 * Management access is role-based and org-wide: an active
 * `vtual_management_creators` assignment plus an org membership is what makes a
 * creator visible, and `Owner`/`Admin` may mutate **every** creator the org
 * holds. This table is the additive exception the decision record settled on:
 * one row = one org member allowed to view/edit **one** named creator, on top
 * of whatever their role already grants. It never narrows a role — the
 * replace-mode toggle was explicitly declined, so a role check that already
 * allows an action is never consulted against this table.
 *
 * A grant is only live while the creator is actively assigned to the same
 * management (`left_at` null); `CreatorAccessService` joins the assignment on
 * read, so unassigning a creator makes every grant for it inert without a
 * cleanup job.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vtual_management_creator_grants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('management_id')->constrained('vtual_managements')->cascadeOnDelete();
            $table->foreignUuid('member_id')->constrained('vtual_management_members')->cascadeOnDelete();
            $table->foreignUuid('creator_id')->constrained('vtual_creators')->cascadeOnDelete();

            // VtualGrantCapability: view|edit
            $table->string('capability', 16);
            $table->foreignUuid('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // One row per (member, creator); a re-grant with a higher capability upserts.
            $table->unique(['member_id', 'creator_id'], 'vtual_mgmt_creator_grants_member_creator_unique');
            $table->index(['management_id', 'creator_id'], 'vtual_mgmt_creator_grants_management_creator_idx');
            $table->index(['creator_id', 'capability'], 'vtual_mgmt_creator_grants_creator_capability_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vtual_management_creator_grants');
    }
};
