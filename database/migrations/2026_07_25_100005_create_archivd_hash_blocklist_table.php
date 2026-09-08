<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('archivd_hash_blocklist', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            // sha256 hex of a file that must never be publicly shareable (DMCA).
            $table->string('sha256', 64)->unique();
            $table->string('reason')->nullable();
            $table->uuid('created_by')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archivd_hash_blocklist');
    }
};
