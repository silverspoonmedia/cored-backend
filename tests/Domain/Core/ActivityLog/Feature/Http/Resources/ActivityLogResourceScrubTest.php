<?php

namespace Tests\Domain\Core\ActivityLog\Feature\Http\Resources;

use App\Domain\ActivityLog\Http\Resources\ActivityLogResource;
use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ActivityLogResourceScrubTest extends TestCase
{
    use RefreshDatabase;

    public function test_properties_and_attribute_changes_redact_sensitive_keys(): void
    {
        $user = User::factory()->create();

        $activity = Activity::query()->create([
            'log_name' => 'user',
            'description' => 'updated',
            'event' => 'updated',
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'causer_type' => User::class,
            'causer_id' => $user->id,
            'properties' => [
                'token' => 'live-invite-token',
                'nested' => ['password' => 'hunter2', 'safe' => 'ok'],
            ],
        ]);

        $activity->forceFill([
            'attribute_changes' => [
                'email' => 'new@example.com',
                'remember_token' => 'secret-remember',
            ],
        ])->save();

        $payload = (new ActivityLogResource($activity))->toArray(Request::create('/'));

        $this->assertSame('[redacted]', $payload['properties']['token']);
        $this->assertSame('[redacted]', $payload['properties']['nested']['password']);
        $this->assertSame('ok', $payload['properties']['nested']['safe']);
        $this->assertSame('[redacted]', $payload['attribute_changes']['remember_token']);
        $this->assertSame('new@example.com', $payload['attribute_changes']['email']);
    }
}
