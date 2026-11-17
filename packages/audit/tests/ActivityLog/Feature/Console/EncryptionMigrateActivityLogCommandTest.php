<?php

namespace App\Domain\ActivityLog\Tests\Feature\Console;

use App\Support\Encryption\EncryptedEnvelope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EncryptionMigrateActivityLogCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_encrypts_pii_properties_and_is_idempotent(): void
    {
        $this->insertLegacyRow('203.0.113.10', 'Agent/1.0');

        $this->artisan('encryption:migrate-activity-log')
            ->expectsOutputToContain('Migrated 1 row(s) / 2 field(s).')
            ->assertSuccessful();

        $stored = $this->storedProperties();

        $this->assertTrue(EncryptedEnvelope::isEnvelopeJson($stored['ip_address']));
        $this->assertTrue(EncryptedEnvelope::isEnvelopeJson($stored['user_agent']));
        $this->assertSame('password', $stored['login_method']);

        $this->artisan('encryption:migrate-activity-log')
            ->expectsOutputToContain('Migrated 0 row(s) / 0 field(s).')
            ->assertSuccessful();

        $this->assertSame($stored, $this->storedProperties());
    }

    public function test_dry_run_reports_without_writing(): void
    {
        $this->insertLegacyRow('203.0.113.11', 'Agent 2.0');

        $this->artisan('encryption:migrate-activity-log', ['--dry-run' => true])
            ->expectsOutputToContain('Would migrate 1 row(s) / 2 field(s).')
            ->assertSuccessful();

        $raw = (string) DB::table('activity_log')->where('log_name', 'auth')->value('properties');

        $this->assertStringContainsString('203.0.113.11', $raw);
        $this->assertStringContainsString('Agent 2.0', $raw);
    }

    private function insertLegacyRow(string $ipAddress, string $userAgent): void
    {
        DB::table('activity_log')->insert([
            'log_name' => 'auth',
            'description' => 'login',
            'event' => 'login',
            'properties' => json_encode([
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'login_method' => 'password',
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function storedProperties(): array
    {
        $raw = (string) DB::table('activity_log')->where('log_name', 'auth')->value('properties');

        return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    }
}
