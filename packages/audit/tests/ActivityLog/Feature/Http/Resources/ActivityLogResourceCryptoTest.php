<?php

namespace App\Domain\ActivityLog\Tests\Feature\Http\Resources;

use App\Domain\ActivityLog\Http\Resources\ActivityLogResource;
use App\Domain\ActivityLog\Support\ActivityLogRealtime;
use App\Domain\ActivityLog\Support\CryptoActivityRecorder;
use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ActivityLogResourceCryptoTest extends TestCase
{
    use RefreshDatabase;

    public function test_crypto_properties_mask_kms_key_id_in_api_but_not_in_database(): void
    {
        $user = User::factory()->create();
        $fullOcid = 'ocid1.key.ap-singapore-1.abcdefghijklmnopqrstuvwxyz';

        $this->actingAs($user, 'api');

        (new CryptoActivityRecorder(new ActivityLogRealtime))->recordFieldDecrypted(
            resourceType: 'users',
            resourceId: (string) $user->id,
            field: 'two_factor_secret',
            purpose: 'test',
            kmsKeyId: $fullOcid,
            cacheHit: false,
            envelopeVersion: 1,
            legacyCiphertext: false,
        );

        $activity = Activity::query()->where('log_name', 'crypto')->firstOrFail();

        $this->assertSame($fullOcid, $activity->properties->get('kms_key_id'));

        $payload = (new ActivityLogResource($activity))->toArray(Request::create('/'));

        $this->assertSame('ocid1.key....stuvwxyz', $payload['properties']['kms_key_id']);
    }
}
