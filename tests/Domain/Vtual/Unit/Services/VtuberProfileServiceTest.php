<?php

namespace Tests\Domain\Vtual\Unit\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Enums\ChannelVisibility;
use App\Domain\Vtual\Enums\ContentStatus;
use App\Domain\Vtual\Enums\ContentType;
use App\Domain\Vtual\Enums\EntityStatus;
use App\Domain\Vtual\Exceptions\VtualException;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\Platform;
use App\Domain\Vtual\Services\ContentService;
use App\Domain\Vtual\Services\CreatorService;
use App\Domain\Vtual\Services\VtuberProfileService;
use Database\Seeders\VtualRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class VtuberProfileServiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private VtuberProfileService $profiles;

    private CreatorService $creators;

    private ContentService $contents;

    private Platform $youtube;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $this->seed(VtualRoleSeeder::class);
        $this->profiles = app(VtuberProfileService::class);
        $this->creators = app(CreatorService::class);
        $this->contents = app(ContentService::class);
        $this->youtube = Platform::query()->where('key', 'youtube')->firstOrFail();
    }

    #[Test]
    public function show_loads_active_channels_and_contents(): void
    {
        $staff = $this->userWithRole('Admin');
        $creator = $this->creators->createOrphan($staff, ['display_name' => 'Profile Fox']);

        $channel = Channel::query()->create([
            'id' => (string) Str::uuid7(),
            'creator_id' => $creator->id,
            'platform_id' => $this->youtube->id,
            'external_id' => 'UCprofiletest1234567890ab',
            'handle' => 'profilefox',
            'display_name' => 'Profile Fox YT',
            'status' => ChannelStatus::Active,
        ]);

        Channel::query()->create([
            'id' => (string) Str::uuid7(),
            'creator_id' => $creator->id,
            'platform_id' => $this->youtube->id,
            'external_id' => 'pending:old',
            'status' => ChannelStatus::Pending,
        ]);

        $this->contents->upsertFromPrimary($channel, [
            'external_id' => 'vid-profile-1',
            'type' => ContentType::Vod->value,
            'status' => ContentStatus::Uploaded->value,
            'title' => 'Hello VOD',
        ]);

        $loaded = $this->profiles->show((string) $creator->id);

        $this->assertSame('Profile Fox', $loaded->display_name);
        $this->assertCount(1, $loaded->channels);
        $this->assertSame(ChannelStatus::Active, $loaded->channels->first()?->status);
        $this->assertCount(1, $loaded->getRelation('profileContents'));
        $this->assertSame('Hello VOD', $loaded->getRelation('profileContents')->first()?->title);
    }

    #[Test]
    public function show_excludes_private_discovered_channels(): void
    {
        $staff = $this->userWithRole('Admin');
        $creator = $this->creators->createOrphan($staff, ['display_name' => 'Private Fox']);

        Channel::query()->create([
            'id' => (string) Str::uuid7(),
            'creator_id' => $creator->id,
            'platform_id' => $this->youtube->id,
            'external_id' => 'UC-public-show',
            'display_name' => 'Public',
            'status' => ChannelStatus::Active,
            'visibility' => ChannelVisibility::Public,
        ]);

        Channel::query()->create([
            'id' => (string) Str::uuid7(),
            'creator_id' => $creator->id,
            'platform_id' => $this->youtube->id,
            'external_id' => 'UC-private-show',
            'display_name' => 'Private',
            'status' => ChannelStatus::Active,
            'visibility' => ChannelVisibility::Private,
        ]);

        $loaded = $this->profiles->show((string) $creator->id);

        $this->assertCount(1, $loaded->channels);
        $this->assertSame('UC-public-show', $loaded->channels->first()?->external_id);
    }

    #[Test]
    public function show_resolves_by_slug(): void
    {
        $staff = $this->userWithRole('Admin');
        $creator = $this->creators->createOrphan($staff, ['display_name' => 'Slug Fox']);

        $loaded = $this->profiles->show((string) $creator->slug);

        $this->assertSame($creator->id, $loaded->id);
        $this->assertSame('Slug Fox', $loaded->display_name);
    }

    #[Test]
    public function show_rejects_archived_creator(): void
    {
        $user = $this->userWithRole('Admin');
        $creator = $this->creators->createOrphan($user, ['display_name' => 'Archived Fox']);
        $creator->forceFill(['status' => EntityStatus::Archived])->save();

        $this->expectException(VtualException::class);
        $this->profiles->show((string) $creator->id);
    }

    private function userWithRole(string $role): User
    {
        $user = User::query()->create([
            'name' => $role.' Profile',
            'email' => strtolower($role).'-profile-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole($role);

        return $user;
    }
}
