<?php

namespace Tests\Domain\Microstock\Feature;

use App\Domain\FileHosting\Enums\WorkspaceKind;
use App\Domain\FileHosting\Models\Folder;
use App\Domain\FileHosting\Models\HostedFile;
use App\Domain\FileHosting\Models\Workspace;
use App\Domain\Identity\Models\User;
use App\Domain\Microstock\Models\AiUsageEvent;
use Database\Seeders\FileHostingRoleSeeder;
use Database\Seeders\MicrostockRoleSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class BillingAdminApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->seed(RoleSeeder::class);
        $this->seed(FileHostingRoleSeeder::class);
        $this->seed(MicrostockRoleSeeder::class);
    }

    public function test_overview_forbidden_for_regular_user(): void
    {
        $user = User::factory()->create();
        $user->assignRole('User');

        $this->actingAsJwt($user)
            ->getJson('/api/v1/admin/microstock/billing/overview')
            ->assertForbidden();
    }

    public function test_overview_returns_aggregates_for_moderator(): void
    {
        $moderator = User::factory()->create();
        $moderator->assignRole('Moderator');

        $owner = User::factory()->create();
        $folder = $this->collection($owner);
        $file = HostedFile::query()->create([
            'user_id' => $owner->id,
            'workspace_id' => $folder->workspace_id,
            'folder_id' => $folder->id,
            'name' => 'shot.jpg',
            'mime' => 'image/jpeg',
            'size' => 1024,
            'disk' => 'local',
            'path' => strtolower(Str::random(40)),
            'visibility' => 'private',
        ]);

        AiUsageEvent::query()->create([
            'user_id' => $owner->id,
            'file_id' => $file->id,
            'folder_id' => $folder->id,
            'stage' => 'vision',
            'alias_model' => 'gpt-5.6',
            'resolved_model' => 'gpt-5.6',
            'prompt_tokens' => 100,
            'completion_tokens' => 50,
            'total_tokens' => 150,
            'provider_cost_micro_usd' => 1_000_000,
            'billable_micro_usd' => 1_350_000,
            'billing_source' => 'prepaid',
            'billed_at' => now(),
            'created_at' => now(),
        ]);

        $this->actingAsJwt($moderator)
            ->getJson('/api/v1/admin/microstock/billing/overview')
            ->assertOk()
            ->assertJsonPath('data.totals.event_count', 1)
            ->assertJsonPath('data.totals.unique_users', 1)
            ->assertJsonPath('data.totals.provider_cost_micro_usd', 1_000_000)
            ->assertJsonPath('data.totals.billable_micro_usd', 1_350_000)
            ->assertJsonPath('data.by_billing_source.prepaid.event_count', 1);
    }

    public function test_admin_usage_events_includes_user(): void
    {
        $admin = User::factory()->create(['email' => 'admin@example.com']);
        $admin->assignRole('Admin');

        $owner = User::factory()->create(['email' => 'owner@example.com']);
        $folder = $this->collection($owner);
        $file = HostedFile::query()->create([
            'user_id' => $owner->id,
            'workspace_id' => $folder->workspace_id,
            'folder_id' => $folder->id,
            'name' => 'a.jpg',
            'mime' => 'image/jpeg',
            'size' => 1024,
            'disk' => 'local',
            'path' => strtolower(Str::random(40)),
            'visibility' => 'private',
        ]);

        AiUsageEvent::query()->create([
            'user_id' => $owner->id,
            'file_id' => $file->id,
            'folder_id' => $folder->id,
            'stage' => 'thinking',
            'alias_model' => 'gpt-5.6',
            'resolved_model' => 'gpt-5.6',
            'prompt_tokens' => 1,
            'completion_tokens' => 1,
            'total_tokens' => 2,
            'provider_cost_micro_usd' => 100,
            'billable_micro_usd' => 135,
            'billing_source' => 'daily_free',
            'billed_at' => now(),
            'created_at' => now(),
        ]);

        $this->actingAsJwt($admin)
            ->getJson('/api/v1/admin/microstock/billing/usage-events')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.user.email', 'owner@example.com')
            ->assertJsonPath('data.items.0.billing_source', 'daily_free');

        $item = $this->actingAsJwt($admin)
            ->getJson('/api/v1/admin/microstock/billing/usage-events')
            ->json('data.items.0');
        $this->assertArrayHasKey('provider_cost_usd', $item);
    }

    private function collection(User $user): Folder
    {
        $ws = Workspace::query()->create([
            'owner_user_id' => $user->id,
            'name' => 'Microstock',
            'kind' => WorkspaceKind::Microstock,
            'status' => 'active',
        ]);

        return Folder::query()->create([
            'user_id' => $user->id,
            'workspace_id' => $ws->id,
            'name' => 'Set',
            'visibility' => 'private',
        ]);
    }

    private function actingAsJwt(User $user): static
    {
        $token = auth('api')->login($user);

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }
}
