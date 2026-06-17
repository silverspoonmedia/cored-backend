<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approval_instance_step_approvers', function (Blueprint $table) {
            $table->foreign('delegation_id', 'fk_approver_delegation')
                ->references('id')
                ->on('approval_delegations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('approval_instance_step_approvers', function (Blueprint $table) {
            $table->dropForeign('fk_approver_delegation');
        });
    }
};
