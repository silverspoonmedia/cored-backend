<?php

namespace Tests\Domain\Ai\Feature\Console\Commands;

use App\Domain\Ai\Enums\AiConnectionStatus;
use App\Domain\Ai\Models\AiConnection;
use App\Domain\Identity\Models\User;
use App\Support\Settings\Repositories\Contracts\SystemSettingRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class ImportLegacyEnvCommandTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        Cache::flush();

        config([
            'microstock.ai.vision.model' => 'internal.vision',
            'microstock.ai.thinking.model' => 'internal.thinking',
        ]);
    }

    #[Test]
    public function it_reports_when_no_legacy_values_exist(): void
    {
        $this->seedActor();

        $this->artisan('ai:import-legacy-env')
            ->expectsOutputToContain('No legacy AI env values found')
            ->assertSuccessful();

        $this->assertDatabaseCount('ai_connections', 0);
    }

    #[Test]
    public function it_imports_the_microstock_vision_and_thinking_connections(): void
    {
        Http::fake([
            'ai.example.test/*' => Http::response(['choices' => [['message' => ['content' => 'pong']]]], 200),
        ]);

        $this->seedActor();

        putenv('MICROSTOCK_AI_VISION_ENDPOINT=https://ai.example.test/v1/chat/completions');
        putenv('MICROSTOCK_AI_VISION_API_KEY=sk-vision-key');
        putenv('MICROSTOCK_AI_THINKING_ENDPOINT=https://ai.example.test/v1/chat/completions');
        putenv('MICROSTOCK_AI_THINKING_API_KEY=sk-thinking-key');

        try {
            $this->artisan('ai:import-legacy-env')->assertSuccessful();
        } finally {
            foreach (['MICROSTOCK_AI_VISION_ENDPOINT', 'MICROSTOCK_AI_VISION_API_KEY', 'MICROSTOCK_AI_THINKING_ENDPOINT', 'MICROSTOCK_AI_THINKING_API_KEY'] as $key) {
                putenv($key);
            }
        }

        $vision = AiConnection::query()->where('slug', 'microstock-vision')->firstOrFail();
        $this->assertSame('internal.vision', $vision->test_model);
        $this->assertSame('sk-vision-key', $vision->secret_payload['api_key']);
        $this->assertSame(AiConnectionStatus::Active, $vision->status);
        $this->assertSame('microstock-vision', app(SystemSettingRepositoryInterface::class)->snapshot()['microstock.ai.vision.connection'] ?? null);

        $thinking = AiConnection::query()->where('slug', 'microstock-thinking')->firstOrFail();
        $this->assertSame('internal.thinking', $thinking->test_model);
    }

    #[Test]
    public function dry_run_writes_nothing(): void
    {
        $this->seedActor();

        putenv('MICROSTOCK_AI_VISION_ENDPOINT=https://ai.example.test/v1/chat/completions');
        putenv('MICROSTOCK_AI_VISION_API_KEY=sk-vision');

        try {
            $this->artisan('ai:import-legacy-env', ['--dry-run' => true])->assertSuccessful();
        } finally {
            putenv('MICROSTOCK_AI_VISION_ENDPOINT');
            putenv('MICROSTOCK_AI_VISION_API_KEY');
        }

        $this->assertDatabaseCount('ai_connections', 0);
    }

    #[Test]
    public function it_skips_a_source_without_a_known_test_model(): void
    {
        config(['microstock.ai.vision.model' => '']);
        $this->seedActor();

        putenv('MICROSTOCK_AI_VISION_ENDPOINT=https://ai.example.test/v1/chat/completions');
        putenv('MICROSTOCK_AI_VISION_API_KEY=sk-vision');

        try {
            $this->artisan('ai:import-legacy-env')->assertFailed();
        } finally {
            putenv('MICROSTOCK_AI_VISION_ENDPOINT');
            putenv('MICROSTOCK_AI_VISION_API_KEY');
        }

        $this->assertDatabaseCount('ai_connections', 0);
    }

    #[Test]
    public function rerunning_refreshes_the_existing_row_in_place(): void
    {
        Http::fake([
            'ai.example.test/*' => Http::response(['choices' => [['message' => ['content' => 'pong']]]], 200),
        ]);

        $this->seedActor();

        putenv('MICROSTOCK_AI_VISION_ENDPOINT=https://ai.example.test/v1/chat/completions');
        putenv('MICROSTOCK_AI_VISION_API_KEY=sk-first');

        try {
            $this->artisan('ai:import-legacy-env')->assertSuccessful();

            putenv('MICROSTOCK_AI_VISION_API_KEY=sk-second');
            $this->artisan('ai:import-legacy-env')->assertSuccessful();
        } finally {
            putenv('MICROSTOCK_AI_VISION_ENDPOINT');
            putenv('MICROSTOCK_AI_VISION_API_KEY');
        }

        $this->assertDatabaseCount('ai_connections', 1);
        $this->assertSame('sk-second', AiConnection::query()->firstOrFail()->secret_payload['api_key']);
    }

    private function seedActor(): User
    {
        $user = User::factory()->create(['password' => Hash::make('password123')]);
        $user->assignRole('Root');

        return $user;
    }
}
