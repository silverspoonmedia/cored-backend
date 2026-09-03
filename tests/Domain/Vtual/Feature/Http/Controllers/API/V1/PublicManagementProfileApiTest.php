<?php

namespace Tests\Domain\Vtual\Feature\Http\Controllers\API\V1;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Enums\ChannelVisibility;
use App\Domain\Vtual\Enums\EntityStatus;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\Creator;
use App\Domain\Vtual\Models\Management;
use App\Domain\Vtual\Models\ManagementCreator;
use App\Domain\Vtual\Models\Platform;
use App\Domain\Vtual\Services\CreatorService;
use Database\Seeders\VtualRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class PublicManagementProfileApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private CreatorService $creators;

    private Platform $youtube;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $this->seed(VtualRoleSeeder::class);
        $this->creators = app(CreatorService::class);
        $this->youtube = Platform::query()->where('key', 'youtube')->firstOrFail();
    }

    #[Test]
    public function guest_can_view_active_management_profile_with_public_roster(): void
    {
        $staff = $this->userWithRole('Admin');
        $management = Management::query()->create([
            'id' => (string) Str::uuid7(),
            'slug' => 'neuron-talent',
            'name' => 'Neuron Talent',
            'description' => 'A talent org.',
            'logo_url' => 'https://img.example/logo.png',
            'status' => EntityStatus::Active,
        ]);

        $listed = $this->creators->createOrphan($staff, ['display_name' => 'Listed Fox']);
        Channel::query()->create([
            'id' => (string) Str::uuid7(),
            'creator_id' => $listed->id,
            'platform_id' => $this->youtube->id,
            'external_id' => 'UClistedfox1234567890abc',
            'handle' => 'listedfox',
            'display_name' => 'Listed Fox',
            'subscriber_count' => 42000,
            'status' => ChannelStatus::Active,
            'visibility' => ChannelVisibility::Public,
        ]);

        // Creator without any public channel must be hidden from roster.
        $hidden = $this->creators->createOrphan($staff, ['display_name' => 'Hidden Cat']);

        foreach ([$listed, $hidden] as $creator) {
            ManagementCreator::query()->create([
                'id' => (string) Str::uuid7(),
                'management_id' => $management->id,
                'creator_id' => $creator->id,
                'joined_at' => now(),
            ]);
        }

        $response = $this->getJson('/api/v1/vtual/public/managements/neuron-talent');

        $response->assertOk()
            ->assertJsonPath('data.slug', 'neuron-talent')
            ->assertJsonPath('data.name', 'Neuron Talent')
            ->assertJsonPath('data.logo_url', 'https://img.example/logo.png')
            ->assertJsonCount(1, 'data.creators')
            ->assertJsonPath('data.creators.0.display_name', 'Listed Fox')
            ->assertJsonPath('data.creators.0.total_subscribers', 42000);

        $this->assertArrayNotHasKey('id', $response->json('data'));
    }

    #[Test]
    public function archived_management_returns_not_found(): void
    {
        Management::query()->create([
            'id' => (string) Str::uuid7(),
            'slug' => 'gone-org',
            'name' => 'Gone Org',
            'status' => EntityStatus::Archived,
        ]);

        $this->getJson('/api/v1/vtual/public/managements/gone-org')
            ->assertNotFound();
    }

    #[Test]
    public function unknown_slug_returns_not_found(): void
    {
        $this->getJson('/api/v1/vtual/public/managements/does-not-exist')
            ->assertNotFound();
    }

    private function userWithRole(string $role): User
    {
        $user = User::query()->create([
            'name' => $role.' MgmtApi',
            'email' => strtolower($role).'-mgmt-api-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole($role);

        return $user;
    }
}
