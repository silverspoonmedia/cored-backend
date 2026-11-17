<?php

namespace App\Domain\ActivityLog\Tests\Unit\Support;

use App\Domain\ActivityLog\Support\SensitiveActivityProperties;
use App\Domain\Identity\Models\User;
use App\Support\Encryption\EncryptedEnvelope;
use App\Support\Encryption\EnvelopeEncryptionService;
use App\Support\Oci\Kms\Contracts\KmsDekClientInterface;
use App\Support\Oci\Kms\OciKmsDekResult;
use App\Support\Oci\Kms\OciKmsFailureCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class SensitiveActivityPropertiesTest extends TestCase
{
    use RefreshDatabase;

    public function test_activity_helper_encrypts_pii_properties_and_keeps_other_keys_plaintext(): void
    {
        $user = User::factory()->create();

        activity()
            ->useLog('auth')
            ->event('login')
            ->causedBy($user)
            ->withProperties([
                'ip_address' => '203.0.113.7',
                'user_agent' => 'Mozilla/5.0 (privacy-test)',
                'email' => 'person@example.com',
                'login_method' => 'password',
            ])
            ->log('login');

        $row = DB::table('activity_log')->where('log_name', 'auth')->firstOrFail();
        $raw = (string) $row->properties;
        $stored = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        $this->assertTrue(EncryptedEnvelope::isEnvelopeJson($stored['ip_address']));
        $this->assertTrue(EncryptedEnvelope::isEnvelopeJson($stored['user_agent']));
        $this->assertTrue(EncryptedEnvelope::isEnvelopeJson($stored['email']));

        // Non-PII keys stay queryable plaintext.
        $this->assertSame('password', $stored['login_method']);

        $this->assertStringNotContainsString('203.0.113.7', $raw);
        $this->assertStringNotContainsString('person@example.com', $raw);
        $this->assertStringNotContainsString('privacy-test', $raw);

        $this->assertSame('203.0.113.7', app(EnvelopeEncryptionService::class)->decrypt(
            $stored['ip_address'],
            SensitiveActivityProperties::context(
                'ip_address',
                (string) $row->id,
                SensitiveActivityProperties::PURPOSE_READ,
            ),
        ));
    }

    public function test_encrypt_in_place_is_idempotent(): void
    {
        $activity = new Activity([
            'log_name' => 'auth',
            'description' => 'login',
            'event' => 'login',
        ]);

        $activity->properties = collect([
            'ip_address' => '198.51.100.9',
            'login_method' => 'password',
        ]);

        SensitiveActivityProperties::encryptInPlace($activity);
        $first = $activity->properties->all();

        SensitiveActivityProperties::encryptInPlace($activity);

        $this->assertSame($first, $activity->properties->all());
        $this->assertTrue(EncryptedEnvelope::isEnvelopeJson($first['ip_address']));
    }

    public function test_disabled_config_keeps_properties_plaintext(): void
    {
        config(['activitylog.sensitive_properties.enabled' => false]);

        activity()
            ->useLog('auth')
            ->event('login')
            ->withProperties(['ip_address' => '203.0.113.9'])
            ->log('login');

        $raw = (string) DB::table('activity_log')->where('log_name', 'auth')->value('properties');

        $this->assertStringContainsString('203.0.113.9', $raw);
    }

    public function test_model_activity_without_pii_properties_is_untouched(): void
    {
        $user = User::factory()->create();

        $user->update(['name' => 'Renamed User']);

        $activity = Activity::query()->where('log_name', 'user')->firstOrFail();

        $this->assertSame([], $activity->properties->toArray());
        $this->assertNotEmpty($activity->attribute_changes->toArray());
    }

    public function test_kms_failure_drops_pii_instead_of_breaking_the_audit_row(): void
    {
        $this->app->instance(KmsDekClientInterface::class, new class implements KmsDekClientInterface
        {
            public function generateDek(): OciKmsDekResult
            {
                return OciKmsDekResult::failure(OciKmsFailureCategory::Network, 'KMS unreachable');
            }

            public function decryptDek(string $wrappedDek): OciKmsDekResult
            {
                return OciKmsDekResult::failure(OciKmsFailureCategory::Network, 'KMS unreachable');
            }
        });

        activity()
            ->useLog('auth')
            ->event('login')
            ->withProperties([
                'ip_address' => '203.0.113.99',
                'login_method' => 'password',
            ])
            ->log('login');

        $row = DB::table('activity_log')->where('log_name', 'auth')->firstOrFail();
        $raw = (string) $row->properties;
        $stored = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        $this->assertArrayNotHasKey('ip_address', $stored);
        $this->assertSame('password', $stored['login_method']);
        $this->assertStringNotContainsString('203.0.113.99', $raw);
    }
}
