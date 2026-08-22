<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('system_settings')
            ->where('key', 'file-hosting.chunk_size_bytes')
            ->update(['value' => json_encode(8388608)]);

        DB::table('system_settings')
            ->where('key', 'file-hosting.multipart_threshold_bytes')
            ->update(['value' => json_encode(8388608)]);
    }

    public function down(): void
    {
        DB::table('system_settings')
            ->where('key', 'file-hosting.chunk_size_bytes')
            ->update(['value' => json_encode(26214400)]);

        DB::table('system_settings')
            ->where('key', 'file-hosting.multipart_threshold_bytes')
            ->update(['value' => json_encode(26214400)]);
    }
};
