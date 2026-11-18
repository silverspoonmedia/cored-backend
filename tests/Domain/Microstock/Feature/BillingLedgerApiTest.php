<?php

namespace Tests\Domain\Microstock\Feature;

use App\Domain\Archivd\Models\Folder;
use App\Domain\Archivd\Models\HostedFile;
use App\Domain\Identity\Models\User;
use App\Domain\Microstock\Enums\AiCreditTransactionType;
use App\Domain\Microstock\Models\AiUsageEvent;
use App\Domain\Microstock\Services\AiCreditService;
use App\Domain\Microstock\Services\MicrostockService;
use App\Domain\Microstock\Support\MicrostockCatalog;
use App\Domain\Service\Enums\EntitlementStatus;
use App\Domain\Service\Models\Service;
use App\Domain\Service\Models\ServiceEntitlement;
use Database\Seeders\ArchivdRoleSeeder;
use Database\Seeders\MicrostockRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\GrantsMicrostockAiCredits;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class BillingLedgerApiTest extends TestCase
{
    use GrantsMicrostockAiCredits;
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private User $owner;

    private User $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->seed(ArchivdRoleSeeder::class);
        $this->seed(MicrostockRoleSeeder::class);

        $this->owner = $this->userWithRole('User', 'owner@example.com');
        $this->other = $this->userWithRole('User', 'other@example.com');
        $this->grantMicrostockFree($this->owner);
        $this->grantMicrostockFree($this->other);
    }

    public function test_ledger_requires_auth(): void
    {
        $this->getJson('/api/v1/microstock/billing/ledger')
            ->assertUnauthorized();
    }

    public function test_ledger_returns_paginated_transactions_for_authenticated_user(): void
    {
        app(AiCreditService::class)->addCredit(
            (string) $this->owner->id,
            2_000_000,
            AiCreditTransactionType::Adjustment,
            'test',
            null,
        );

        $response = $this->actingAsJwt($this->owner)
            ->getJson('/api/v1/microstock/billing/ledger?per_page=10');

        $response->assertOk()
            ->assertJsonPath('data.pagination.per_page', 10)
            ->assertJsonPath('data.items.0.type', AiCreditTransactionType::Adjustment->value);

        $amounts = collect($response->json('data.items'))->pluck('amount_micro_usd')->all();
        $this->assertContains(2_000_000, $amounts);
    }

    public function test_ledger_isolates_users(): void
    {
        app(AiCreditService::class)->addCredit(
            (string) $this->owner->id,
            1_000_000,
            AiCreditTransactionType::Adjustment,
            'owner',
            null,
        );

        app(AiCreditService::class)->addCredit(
            (string) $this->other->id,
            3_000_000,
            AiCreditTransactionType::Adjustment,
            'other',
            null,
        );

        $ownerAmounts = collect($this->actingAsJwt($this->owner)
            ->getJson('/api/v1/microstock/billing/ledger')
            ->json('data.items'))
            ->pluck('amount_micro_usd')
            ->all();
        $this->assertContains(1_000_000, $ownerAmounts);
        $this->assertNotContains(3_000_000, $ownerAmounts);

        $otherAmounts = collect($this->actingAsJwt($this->other)
            ->getJson('/api/v1/microstock/billing/ledger')
            ->json('data.items'))
            ->pluck('amount_micro_usd')
            ->all();
        $this->assertContains(3_000_000, $otherAmounts);
        $this->assertNotContains(1_000_000, $otherAmounts);
    }

    public function test_usage_events_returns_rows_for_authenticated_user(): void
    {
        $folder = $this->collection($this->owner);
        $file = HostedFile::query()->create([
            'user_id' => $this->owner->id,
            'workspace_id' => $folder->workspace_id,
            'folder_id' => $folder->id,
            'name' => 'asset.jpg',
            'mime' => 'image/jpeg',
            'size' => 1024,
            'disk' => 'local',
            'path' => strtolower(Str::random(40)),
            'visibility' => 'private',
        ]);

        AiUsageEvent::query()->create([
            'user_id' => $this->owner->id,
            'file_id' => $file->id,
            'folder_id' => $folder->id,
            'stage' => 'vision',
            'alias_model' => 'gpt-5.6',
            'resolved_model' => 'gpt-5.6',
            'prompt_tokens' => 1,
            'completion_tokens' => 2,
            'total_tokens' => 3,
            'provider_cost_micro_usd' => 100,
            'billable_micro_usd' => 135,
            'billing_source' => 'prepaid',
            'billed_at' => now(),
            'created_at' => now(),
        ]);

        $this->actingAsJwt($this->owner)
            ->getJson('/api/v1/microstock/billing/usage-events')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.file_name', 'asset.jpg')
            ->assertJsonPath('data.items.0.billing_source', 'prepaid');

        $item = $this->actingAsJwt($this->owner)
            ->getJson('/api/v1/microstock/billing/usage-events')
            ->json('data.items.0');

        $this->assertArrayNotHasKey('provider_cost_usd', $item);
        $this->assertArrayNotHasKey('rate_snapshot', $item);
    }

    public function test_usage_events_isolates_users(): void
    {
        $folder = $this->collection($this->owner);
        $file = HostedFile::query()->create([
            'user_id' => $this->owner->id,
            'workspace_id' => $folder->workspace_id,
            'folder_id' => $folder->id,
            'name' => 'private.jpg',
            'mime' => 'image/jpeg',
            'size' => 1024,
            'disk' => 'local',
            'path' => strtolower(Str::random(40)),
            'visibility' => 'private',
        ]);

        AiUsageEvent::query()->create([
            'user_id' => $this->owner->id,
            'file_id' => $file->id,
            'folder_id' => $folder->id,
            'stage' => 'vision',
            'alias_model' => 'gpt-5.6',
            'resolved_model' => 'gpt-5.6',
            'prompt_tokens' => 1,
            'completion_tokens' => 1,
            'total_tokens' => 2,
            'provider_cost_micro_usd' => 50,
            'billable_micro_usd' => 50,
            'billing_source' => 'prepaid',
            'billed_at' => now(),
            'created_at' => now(),
        ]);

        $this->actingAsJwt($this->other)
            ->getJson('/api/v1/microstock/billing/usage-events')
            ->assertOk()
            ->assertJsonCount(0, 'data.items');
    }

    private function collection(User $user): Folder
    {
        $ws = app(MicrostockService::class)->createWorkspace($user);

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

    private function grantMicrostockFree(User $user): void
    {
        $service = Service::query()->where('code', MicrostockCatalog::CODE_PLAN_FREE)->first()
            ?? Service::factory()->microstockPlanFree()->create();

        ServiceEntitlement::query()->updateOrCreate(
            ['user_id' => $user->id, 'service_id' => $service->id],
            [
                'status' => EntitlementStatus::Active,
                'quantity' => 1,
                'starts_at' => now(),
                'ends_at' => null,
            ],
        );

        $this->grantMicrostockAiCredits($user);
    }

    private function userWithRole(string $role, string $email): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'name' => $email,
            'password' => bcrypt('password'),
        ]);
        $user->assignRole($role);

        return $user;
    }
}
