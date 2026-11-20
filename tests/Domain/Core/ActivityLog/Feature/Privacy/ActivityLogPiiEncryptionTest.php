<?php

namespace Tests\Domain\Core\ActivityLog\Feature\Privacy;

use App\Domain\Identity\Models\User;
use App\Support\Encryption\EncryptedEnvelope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class ActivityLogPiiEncryptionTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_login_stores_pii_as_envelopes_and_api_returns_plaintext(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $user = User::factory()->create([
            'email' => 'pii-login@example.com',
            'password' => Hash::make('password123'),
        ]);
        $user->assignRole('User');

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.44'])
            ->postJson('/api/v1/auth/login', [
                'email' => 'pii-login@example.com',
                'password' => 'password123',
            ])
            ->assertOk();

        $row = DB::table('activity_log')
            ->where('log_name', 'auth')
            ->where('event', 'login')
            ->firstOrFail();

        $raw = (string) $row->properties;
        $stored = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        $this->assertTrue(EncryptedEnvelope::isEnvelopeJson($stored['ip_address']));
        $this->assertTrue(EncryptedEnvelope::isEnvelopeJson($stored['user_agent']));
        $this->assertSame('password', $stored['login_method']);
        $this->assertStringNotContainsString('203.0.113.44', $raw);

        $cryptoRowsBefore = DB::table('activity_log')->where('log_name', 'crypto')->count();

        $this->withHeader('Authorization', 'Bearer '.auth('api')->login($admin))
            ->getJson('/api/v1/activity-logs?log_name=auth&event=login')
            ->assertOk()
            ->assertJsonPath('data.0.properties.ip_address', '203.0.113.44');

        $this->assertSame(
            $cryptoRowsBefore,
            DB::table('activity_log')->where('log_name', 'crypto')->count(),
            'Reading the audit trail must not append crypto audit rows.',
        );
    }

    public function test_failed_login_email_is_encrypted_at_rest_and_decrypted_for_admins(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        User::factory()->create(['email' => 'known@example.com']);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'known@example.com',
            'password' => 'wrong-password',
        ])->assertUnauthorized();

        $raw = (string) DB::table('activity_log')
            ->where('log_name', 'auth')
            ->where('event', 'login_failed')
            ->value('properties');

        $this->assertStringNotContainsString('known@example.com', $raw);

        $this->withHeader('Authorization', 'Bearer '.auth('api')->login($admin))
            ->getJson('/api/v1/activity-logs?log_name=auth&event=login_failed')
            ->assertOk()
            ->assertJsonPath('data.0.properties.email', 'known@example.com');
    }
}
