<?php

namespace Tests\Domain\Vtual\Feature\Http\Controllers\API\V1;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Enums\ContentStatus;
use App\Domain\Vtual\Enums\ContentType;
use App\Domain\Vtual\Enums\EntityStatus;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\Platform;
use App\Domain\Vtual\Services\ContentService;
use App\Domain\Vtual\Services\CreatorService;
use App\Support\Product\Services\ProductActivationService;
use Database\Seeders\VtualRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class VtuberProfileApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private CreatorService $creators;

    private ContentService $contents;

    private ProductActivationService $activations;

    private Platform $youtube;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $this->seed(VtualRoleSeeder::class);
        $this->creators = app(CreatorService::class);
        $this->contents = app(ContentService::class);
        $this->activations = app(ProductActivationService::class);
        $this->youtube = Platform::query()->where('key', 'youtube')->firstOrFail();
    }

    #[Test]
    public function authenticated_user_can_view_profile_without_creator_membership(): void
    {
        $staff = $this->userWithRole('Admin');
        $viewer = $this->userWithRole('User');
        $this->activations->activateImplicit($viewer, 'Vtual');

        $creator = $this->creators->createOrphan($staff, ['display_name' => 'Aizawa Ema']);

        $channel = Channel::query()->create([
            'id' => (string) Str::uuid7(),
            'creator_id' => $creator->id,
            'platform_id' => $this->youtube->id,
            'external_id' => 'UCaizawaema1234567890abcd',
            'handle' => 'aizawaema',
            'display_name' => 'Aizawa Ema',
            'avatar_url' => 'https://img.example/avatar.jpg',
            'url' => 'https://www.youtube.com/channel/UCaizawaema1234567890abcd',
            'status' => ChannelStatus::Active,
        ]);

        $this->contents->upsertFromPrimary($channel, [
            'external_id' => 'vid-api-1',
            'type' => ContentType::Vod->value,
            'status' => ContentStatus::Uploaded->value,
            'title' => 'Archive Stream 1',
            'thumbnail_url' => 'https://img.example/thumb.jpg',
            'url' => 'https://www.youtube.com/watch?v=vid-api-1',
        ]);

        $response = $this->actingAsJwt($viewer)
            ->getJson('/api/v1/vtual/vtubers/'.$creator->id);

        $response->assertOk()
            ->assertJsonPath('data.display_name', 'Aizawa Ema')
            ->assertJsonPath('data.slug', $creator->slug)
            ->assertJsonPath('data.profile_url', route('vtual.vtuber.show', $creator->slug))
            ->assertJsonPath('data.channels.0.handle', 'aizawaema')
            ->assertJsonPath('data.contents.0.title', 'Archive Stream 1')
            ->assertJsonPath('data.contents.0.external_id', 'vid-api-1');
    }

    #[Test]
    public function authenticated_user_can_view_profile_by_slug(): void
    {
        $staff = $this->userWithRole('Admin');
        $viewer = $this->userWithRole('User');
        $this->activations->activateImplicit($viewer, 'Vtual');

        $creator = $this->creators->createOrphan($staff, ['display_name' => 'Slug Lookup']);

        $this->actingAsJwt($viewer)
            ->getJson('/api/v1/vtual/vtubers/'.$creator->slug)
            ->assertOk()
            ->assertJsonPath('data.id', $creator->id)
            ->assertJsonPath('data.display_name', 'Slug Lookup');
    }

    #[Test]
    public function archived_creator_returns_not_found(): void
    {
        $user = $this->userWithRole('User');
        $this->activations->activateImplicit($user, 'Vtual');

        $creator = $this->creators->createOrphan($this->userWithRole('Admin'), ['display_name' => 'Gone']);
        $creator->forceFill(['status' => EntityStatus::Archived])->save();

        $this->actingAsJwt($user)
            ->getJson('/api/v1/vtual/vtubers/'.$creator->id)
            ->assertNotFound();
    }

    private function actingAsJwt(User $user): static
    {
        $token = auth('api')->login($user);

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }

    private function userWithRole(string $role): User
    {
        $user = User::query()->create([
            'name' => $role.' VtuberApi',
            'email' => strtolower($role).'-vtuber-api-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole($role);

        return $user;
    }
}
