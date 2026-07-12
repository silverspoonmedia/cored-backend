<?php

namespace Tests\Feature\Console;

use App\Support\Encryption\EncryptedEnvelope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EncryptionMigrateP0CommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_migrates_sensitive_cache_rows_without_id_column(): void
    {
        DB::table('cache')->insert([
            'key' => '2fa_token:test-user',
            'value' => serialize(['user_id' => 'abc', 'remember' => false]),
            'expiration' => now()->addHour()->timestamp,
        ]);

        $this->artisan('encryption:migrate-p0')
            ->assertSuccessful();

        $stored = (string) DB::table('cache')->where('key', '2fa_token:test-user')->value('value');

        $this->assertTrue(EncryptedEnvelope::isEnvelopeJson($stored));
    }

    public function test_it_is_idempotent_when_cache_rows_are_already_envelopes(): void
    {
        DB::table('cache')->insert([
            'key' => '2fa_token:already-done',
            'value' => json_encode([
                'v' => 1,
                'alg' => 'AES-256-GCM',
                'kms_key_id' => 'local',
                'dek_ciphertext' => 'x',
                'iv' => 'x',
                'tag' => 'x',
                'ciphertext' => 'x',
            ]),
            'expiration' => now()->addHour()->timestamp,
        ]);

        $before = DB::table('cache')->where('key', '2fa_token:already-done')->value('value');

        $this->artisan('encryption:migrate-p0')
            ->assertSuccessful();

        $this->assertSame($before, DB::table('cache')->where('key', '2fa_token:already-done')->value('value'));
    }
}
