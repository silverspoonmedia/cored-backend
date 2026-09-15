<?php

namespace Tests\Unit\Domain\ActivityLog\Support;

use App\Domain\ActivityLog\Support\ActivityLogRealtime;
use App\Domain\ActivityLog\Support\CryptoActivityRecorder;
use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class CryptoActivityRecorderTest extends TestCase
{
    use RefreshDatabase;

    public function test_record_field_decrypted_writes_crypto_log_without_secrets(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'api');

        (new CryptoActivityRecorder(new ActivityLogRealtime))->recordFieldDecrypted(
            resourceType: 'users',
            resourceId: (string) $user->id,
            field: 'two_factor_secret',
            purpose: 'test',
            kmsKeyId: 'ocid1.key.test',
            cacheHit: false,
            envelopeVersion: 1,
            legacyCiphertext: false,
        );

        $activity = Activity::query()->where('log_name', 'crypto')->first();

        $this->assertNotNull($activity);
        $this->assertSame('field_decrypted', $activity->event);
        $this->assertSame('users', $activity->properties->get('resource_type'));
        $this->assertSame('two_factor_secret', $activity->properties->get('field'));
        $this->assertFalse($activity->properties->get('cache_hit'));
        $this->assertSame(1, $activity->properties->get('envelope_version'));
        $this->assertFalse($activity->properties->get('legacy_ciphertext'));
        $this->assertArrayNotHasKey('plaintext', $activity->properties->toArray());
    }

    public function test_extra_properties_allowlist_blocks_secret_keys(): void
    {
        (new CryptoActivityRecorder(new ActivityLogRealtime))->recordFieldDecrypted(
            resourceType: 'users',
            resourceId: 'user-1',
            field: 'two_factor_secret',
            extraProperties: [
                'plaintext' => 'leak',
                'purpose' => 'allowed-override',
                'unknown_key' => 'ignored',
            ],
        );

        $properties = Activity::query()->where('log_name', 'crypto')->firstOrFail()->properties->toArray();

        $this->assertArrayNotHasKey('plaintext', $properties);
        $this->assertArrayNotHasKey('unknown_key', $properties);
        $this->assertSame('allowed-override', $properties['purpose']);
    }
}
