<?php

namespace Tests\Domain\Vtual\Unit\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Exceptions\VtualException;
use App\Domain\Vtual\Services\ChannelService;
use App\Domain\Vtual\Services\CreatorService;
use Database\Seeders\VtualRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class ChannelServiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private ChannelService $channels;

    private CreatorService $creators;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $this->seed(VtualRoleSeeder::class);
        $this->channels = app(ChannelService::class);
        $this->creators = app(CreatorService::class);
    }

    #[Test]
    public function create_youtube_channel_pending(): void
    {
        $user = $this->userWithRole('User');
        $creator = $this->creators->create($user, ['display_name' => 'Stream']);

        $channel = $this->channels->create($user, $creator->id, [
            'platform_key' => 'youtube',
            'external_id' => 'UC123',
            'handle' => '@stream',
        ]);

        $this->assertSame(ChannelStatus::Pending, $channel->status);
        $this->assertSame('UC123', $channel->external_id);
    }

    #[Test]
    public function kick_platform_is_rejected_while_disabled(): void
    {
        $user = $this->userWithRole('User');
        $creator = $this->creators->create($user, ['display_name' => 'Stream']);

        $this->expectException(VtualException::class);

        $this->channels->create($user, $creator->id, [
            'platform_key' => 'kick',
            'external_id' => 'kick-user-1',
        ]);
    }

    #[Test]
    public function duplicate_platform_external_id_rejected(): void
    {
        $user = $this->userWithRole('User');
        $creator = $this->creators->create($user, ['display_name' => 'Stream']);

        $this->channels->create($user, $creator->id, [
            'platform_key' => 'twitch',
            'external_id' => 'same-id',
        ]);

        $this->expectException(VtualException::class);

        $this->channels->create($user, $creator->id, [
            'platform_key' => 'twitch',
            'external_id' => 'same-id',
        ]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create([
            'email' => 'u'.uniqid('', true).'@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole($role);

        return $user;
    }
}
