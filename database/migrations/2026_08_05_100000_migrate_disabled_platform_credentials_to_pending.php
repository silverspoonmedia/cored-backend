<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('vtual_platform_credentials')
            ->where('status', 'disabled')
            ->update([
                'status' => 'pending',
                'verified_at' => null,
                'runtime_payload' => null,
            ]);
    }

    public function down(): void
    {
        // Irreversible: prior disabled rows cannot be distinguished from native pending rows.
    }
};
