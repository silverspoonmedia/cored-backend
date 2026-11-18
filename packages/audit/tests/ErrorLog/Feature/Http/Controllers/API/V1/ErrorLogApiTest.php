<?php

namespace App\Domain\ErrorLog\Tests\Feature\Http\Controllers\API\V1;

use App\Domain\ErrorLog\Models\ErrorLog;
use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class ErrorLogApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
    }

    public function test_admin_can_list_error_logs(): void
    {
        $admin = $this->userWithRole('Admin');
        $this->seedErrorGroup('Boom A');
        $this->seedErrorGroup('Boom B');

        $this->actingAsJwt($admin)
            ->getJson('/api/v1/error-logs')
            ->assertOk()
            ->assertJsonPath('message', __('api/error-log.list_success'))
            ->assertJsonStructure([
                'data' => [[
                    'id',
                    'fingerprint',
                    'type',
                    'level',
                    'message',
                    'occurrence_count',
                    'status',
                    'last_seen_at',
                ]],
            ])
            ->assertJsonCount(2, 'data');
    }

    public function test_moderator_without_permission_cannot_view_error_logs(): void
    {
        $moderator = $this->userWithRole('Moderator');
        $this->seedErrorGroup('secret');

        $this->actingAsJwt($moderator)
            ->getJson('/api/v1/error-logs')
            ->assertForbidden();
    }

    public function test_guest_cannot_view_error_logs(): void
    {
        $this->getJson('/api/v1/error-logs')->assertUnauthorized();
    }

    public function test_admin_can_show_error_log(): void
    {
        $admin = $this->userWithRole('Admin');
        $row = $this->seedErrorGroup('Detail me');

        $this->actingAsJwt($admin)
            ->getJson('/api/v1/error-logs/'.$row->id)
            ->assertOk()
            ->assertJsonPath('message', __('api/error-log.show_success'))
            ->assertJsonPath('data.id', $row->id)
            ->assertJsonPath('data.message', 'Detail me');
    }

    public function test_admin_can_update_status(): void
    {
        $admin = $this->userWithRole('Admin');
        $row = $this->seedErrorGroup('resolve me');

        $this->actingAsJwt($admin)
            ->patchJson('/api/v1/error-logs/'.$row->id.'/status', [
                'status' => ErrorLog::STATUS_RESOLVED,
            ])
            ->assertOk()
            ->assertJsonPath('message', __('api/error-log.status_updated'))
            ->assertJsonPath('data.status', ErrorLog::STATUS_RESOLVED)
            ->assertJsonPath('data.resolved_by.id', $admin->id);

        $this->assertDatabaseHas('error_logs', [
            'id' => $row->id,
            'status' => ErrorLog::STATUS_RESOLVED,
            'resolved_by' => $admin->id,
        ]);
    }

    public function test_index_filters_by_status_and_q(): void
    {
        $admin = $this->userWithRole('Admin');
        $open = $this->seedErrorGroup('unique-needle-xyz');
        $resolved = $this->seedErrorGroup('other message');
        $resolved->update(['status' => ErrorLog::STATUS_RESOLVED]);

        $this->actingAsJwt($admin)
            ->getJson('/api/v1/error-logs?status=open&q=unique-needle')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $open->id);
    }

    private function seedErrorGroup(string $message): ErrorLog
    {
        $now = now();

        return ErrorLog::query()->create([
            'fingerprint' => hash('sha1', Str::uuid()->toString()),
            'type' => ErrorLog::TYPE_EXCEPTION,
            'level' => 'error',
            'exception_class' => \RuntimeException::class,
            'message' => $message,
            'file' => '/app/test.php',
            'line' => 1,
            'trace' => 'trace',
            'context' => ['url' => 'http://example.test'],
            'occurrence_count' => 1,
            'first_seen_at' => $now,
            'last_seen_at' => $now,
            'status' => ErrorLog::STATUS_OPEN,
        ]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function actingAsJwt(User $user): static
    {
        $token = auth('api')->login($user);

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }
}
