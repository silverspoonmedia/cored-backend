<?php

namespace Tests\Unit\Domain\ActivityLog\Support;

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

        (new CryptoActivityRecorder)->recordFieldDecrypted(
            resourceType: 'users',
            resourceId: (string) $user->id,
            field: 'two_factor_secret',
            purpose: 'test',
            kmsKeyId: 'ocid1.key.test',
        );

        $activity = Activity::query()->where('log_name', 'crypto')->first();

        $this->assertNotNull($activity);
        $this->assertSame('field_decrypted', $activity->event);
        $this->assertSame('users', $activity->properties->get('resource_type'));
        $this->assertSame('two_factor_secret', $activity->properties->get('field'));
        $this->assertArrayNotHasKey('plaintext', $activity->properties->toArray());
    }
}
