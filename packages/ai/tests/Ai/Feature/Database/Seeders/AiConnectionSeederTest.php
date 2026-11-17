<?php

namespace Tests\Domain\Ai\Feature\Database\Seeders;

use App\Domain\Ai\Contracts\AiConnectionProbe;
use App\Domain\Ai\Enums\AiConnectionStatus;
use App\Domain\Ai\Models\AiConnection;
use App\Domain\Ai\Services\AiConnectionResolver;
use App\Domain\Ai\Support\AiConnectionTestResult;
use App\Domain\Identity\Models\User;
use App\Support\Settings\Repositories\Contracts\SystemSettingRepositoryInterface;
use App\Domain\Ai\Database\Seeders\AiConnectionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class AiConnectionSeederTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private string $sandbox = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        Cache::flush();

        $this->sandbox = storage_path('framework/testing-ai-seed-'.uniqid());
        File::ensureDirectoryExists($this->sandbox);

        config([
            'ai.seed.path' => $this->sandbox.'/ai-connections.local.php',
            'microstock.ai.vision.model' => 'internal.vision',
            'microstock.ai.thinking.model' => 'internal.thinking',
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->sandbox !== '') {
            File::deleteDirectory($this->sandbox);
        }

        parent::tearDown();
    }

    #[Test]
    public function it_skips_everything_when_app_env_is_not_local(): void
    {
        $this->seedActor();
        $this->writeDataFile();

        $this->assertSame('testing', app()->environment());

        $this->seed(AiConnectionSeeder::class);

        $this->assertDatabaseCount('ai_connections', 0);
        Http::assertNothingSent();
    }

    #[Test]
    public function it_skips_when_the_data_file_is_missing(): void
    {
        $this->app['env'] = 'local';
        $this->seedActor();

        $this->assertFileDoesNotExist(config('ai.seed.path'));

        $this->seed(AiConnectionSeeder::class);

        $this->assertDatabaseCount('ai_connections', 0);
        Http::assertNothingSent();
    }

    #[Test]
    public function it_skips_when_there_is_no_actor_to_credit(): void
    {
        $this->app['env'] = 'local';
        $this->writeDataFile();

        $this->assertSame(0, User::query()->count());

        $this->seed(AiConnectionSeeder::class);

        $this->assertDatabaseCount('ai_connections', 0);
    }

    #[Test]
    public function it_creates_probes_binds_and_promotes_the_default_connection(): void
    {
        $this->app['env'] = 'local';
        $actor = $this->seedActor();
        $this->writeDataFile();
        $this->fakeProbeOk();

        $this->seed(AiConnectionSeeder::class);

        $this->assertDatabaseCount('ai_connections', 2);

        $vision = AiConnection::query()->where('slug', 'microstock-vision')->firstOrFail();
        $this->assertSame(AiConnectionStatus::Active, $vision->status);
        $this->assertSame('internal.vision', $vision->test_model);
        $this->assertSame('sk-vision-key', $vision->secret_payload['api_key']);
        $this->assertSame($actor->id, $vision->created_by);
        $this->assertNotNull($vision->verified_at);
        $this->assertTrue($vision->is_default);

        $thinking = AiConnection::query()->where('slug', 'microstock-thinking')->firstOrFail();
        $this->assertSame('internal.thinking', $thinking->test_model);
        $this->assertSame(AiConnectionStatus::Active, $thinking->status);
        $this->assertFalse($thinking->is_default);

        $snapshot = app(SystemSettingRepositoryInterface::class)->snapshot();
        $this->assertSame('microstock-vision', $snapshot['microstock.ai.vision.connection'] ?? null);
        $this->assertSame('microstock-thinking', $snapshot['microstock.ai.thinking.connection'] ?? null);

        $this->assertSame('microstock-vision', app(AiConnectionResolver::class)->defaultSlug());
        $this->assertTrue(app(AiConnectionResolver::class)->configured('microstock-vision'));

        // Secrets are envelope-encrypted at rest — never plaintext in the column.
        $raw = (string) $vision->getRawOriginal('secret_payload');
        $this->assertStringNotContainsString('sk-vision-key', $raw);
    }

    #[Test]
    public function it_skips_entries_with_a_blank_endpoint_or_api_key(): void
    {
        $this->app['env'] = 'local';
        $this->seedActor();
        $this->fakeProbeOk();
        $this->writeDataFile([
            'microstock-vision' => [
                'label' => 'Blank key',
                'endpoint' => 'https://ai.example.test/v1/chat/completions',
                'api_key' => '',
                'binds' => ['microstock.ai.vision.connection'],
            ],
            'microstock-thinking' => [
                'label' => 'Complete',
                'endpoint' => 'https://ai.example.test/v1/chat/completions',
                'api_key' => 'sk-thinking-key',
                'binds' => ['microstock.ai.thinking.connection'],
            ],
        ]);

        $this->seed(AiConnectionSeeder::class);

        $this->assertDatabaseCount('ai_connections', 1);
        $this->assertDatabaseHas('ai_connections', ['slug' => 'microstock-thinking']);

        $snapshot = app(SystemSettingRepositoryInterface::class)->snapshot();
        $this->assertSame('microstock-thinking', $snapshot['microstock.ai.thinking.connection'] ?? null);
        $this->assertArrayNotHasKey('microstock.ai.vision.connection', $snapshot);
    }

    #[Test]
    public function it_skips_an_entry_without_a_resolvable_test_model(): void
    {
        $this->app['env'] = 'local';
        $this->seedActor();

        config(['microstock.ai.thinking.model' => '']);

        $this->writeDataFile([
            'microstock-thinking' => [
                'label' => 'No model',
                'endpoint' => 'https://ai.example.test/v1/chat/completions',
                'api_key' => 'sk-thinking-key',
                'binds' => ['microstock.ai.thinking.connection'],
            ],
        ]);

        $this->seed(AiConnectionSeeder::class);

        $this->assertDatabaseCount('ai_connections', 0);
        Http::assertNothingSent();
    }

    #[Test]
    public function it_ignores_a_binding_that_is_not_a_registered_setting(): void
    {
        $this->app['env'] = 'local';
        $this->seedActor();
        $this->fakeProbeOk();
        $this->writeDataFile([
            'microstock-vision' => [
                'label' => 'Typo binding',
                'endpoint' => 'https://ai.example.test/v1/chat/completions',
                'api_key' => 'sk-vision-key',
                // Kept so the entry is not skipped for a missing test model —
                // this test isolates the unknown-binding warning.
                'test_model' => 'vision-audit',
                'binds' => ['microstock.ai.vision.conection'],
            ],
        ]);

        $this->seed(AiConnectionSeeder::class);

        $this->assertDatabaseHas('ai_connections', ['slug' => 'microstock-vision']);

        $snapshot = app(SystemSettingRepositoryInterface::class)->snapshot();
        $this->assertArrayNotHasKey('microstock.ai.vision.conection', $snapshot);
    }

    #[Test]
    public function reseeding_with_an_unchanged_identity_does_not_demote_the_connection(): void
    {
        $this->app['env'] = 'local';
        $this->seedActor();
        $this->writeDataFile();
        $this->fakeProbeOk();

        $this->seed(AiConnectionSeeder::class);

        $vision = AiConnection::query()->where('slug', 'microstock-vision')->firstOrFail();
        $verifiedAt = $vision->verified_at?->toDateTimeString();

        $this->assertSame(AiConnectionStatus::Active, $vision->status);

        $this->seed(AiConnectionSeeder::class);

        $this->assertDatabaseCount('ai_connections', 2);

        $fresh = AiConnection::query()->where('slug', 'microstock-vision')->firstOrFail();
        $this->assertSame(AiConnectionStatus::Active, $fresh->status);
        $this->assertSame($verifiedAt, $fresh->verified_at?->toDateTimeString());
        $this->assertTrue($fresh->is_default);
    }

    #[Test]
    public function rotating_the_key_demotes_the_connection_and_reprobes_it(): void
    {
        $this->app['env'] = 'local';
        $this->seedActor();
        $this->writeDataFile();
        $this->fakeProbeOk();

        $this->seed(AiConnectionSeeder::class);

        $this->writeDataFile([
            'microstock-vision' => [
                'label' => 'Rotated',
                'endpoint' => 'https://ai.example.test/v1/chat/completions',
                'api_key' => 'sk-rotated-key',
                'binds' => ['microstock.ai.vision.connection'],
                'is_default' => true,
            ],
        ]);

        $this->seed(AiConnectionSeeder::class);

        $fresh = AiConnection::query()->where('slug', 'microstock-vision')->firstOrFail();
        $this->assertSame('sk-rotated-key', $fresh->secret_payload['api_key']);
        // The re-probe ran, so the rotated identity is verified again.
        $this->assertSame(AiConnectionStatus::Active, $fresh->status);
        $this->assertNotNull($fresh->verified_at);
    }

    #[Test]
    public function a_failed_probe_leaves_the_row_pending_but_still_bound(): void
    {
        $this->app['env'] = 'local';
        $this->seedActor();

        Http::fake([
            'ai.example.test/*' => Http::response(['error' => 'nope'], 401),
        ]);

        $this->writeDataFile([
            'microstock-vision' => [
                'label' => 'Rejected key',
                'endpoint' => 'https://ai.example.test/v1/chat/completions',
                'api_key' => 'sk-bad-key',
                'binds' => ['microstock.ai.vision.connection'],
            ],
        ]);

        $this->seed(AiConnectionSeeder::class);

        $connection = AiConnection::query()->where('slug', 'microstock-vision')->firstOrFail();
        $this->assertSame(AiConnectionStatus::Pending, $connection->status);
        $this->assertNull($connection->verified_at);
        $this->assertNotNull($connection->last_error_at);
        $this->assertNotNull($connection->last_error_message);

        // Still bound — the consumer reports "AI not configured" until Test passes.
        $snapshot = app(SystemSettingRepositoryInterface::class)->snapshot();
        $this->assertSame('microstock-vision', $snapshot['microstock.ai.vision.connection'] ?? null);

        // A pending row is never the default.
        $this->assertNull(app(AiConnectionResolver::class)->defaultSlug());
    }

    #[Test]
    public function disabling_probe_leaves_rows_pending_without_touching_the_network(): void
    {
        $this->app['env'] = 'local';
        $this->seedActor();

        $this->writeDataFile(probe: false);

        $this->seed(AiConnectionSeeder::class);

        $this->assertDatabaseCount('ai_connections', 2);
        $this->assertSame(
            2,
            AiConnection::query()->where('status', AiConnectionStatus::Pending->value)->count(),
        );

        Http::assertNothingSent();
        $this->assertNull(app(AiConnectionResolver::class)->defaultSlug());
    }

    #[Test]
    public function it_never_calls_the_probe_when_probing_is_disabled(): void
    {
        $this->app['env'] = 'local';
        $this->seedActor();

        $probe = new class implements AiConnectionProbe
        {
            public int $calls = 0;

            public function supports(AiConnection $connection): bool
            {
                return true;
            }

            public function probe(AiConnection $connection, array $secretPayload): AiConnectionTestResult
            {
                $this->calls++;

                return new AiConnectionTestResult(ok: true, message: 'stub', latencyMs: 1, connection: $connection);
            }
        };

        $this->app->instance(AiConnectionProbe::class, $probe);

        $this->writeDataFile(probe: false);

        $this->seed(AiConnectionSeeder::class);

        $this->assertSame(0, $probe->calls);
    }

    #[Test]
    public function an_already_active_row_is_not_reprobed_on_reseed(): void
    {
        $this->app['env'] = 'local';
        $this->seedActor();

        $this->writeDataFile();
        $this->fakeProbeOk();

        $this->seed(AiConnectionSeeder::class);
        Http::assertSentCount(2);

        // Re-registering clears the recorded requests; the 500 body is never
        // reached because every row is already active.
        Http::fake([
            'ai.example.test/*' => Http::response(['error' => 'should not be called'], 500),
        ]);

        $this->seed(AiConnectionSeeder::class);

        Http::assertNothingSent();

        $this->assertDatabaseCount('ai_connections', 2);
        $this->assertSame(
            2,
            AiConnection::query()->where('status', AiConnectionStatus::Active->value)->count(),
        );
    }

    /**
     * Register the probe HTTP fake. Called explicitly per test — a fake in
     * setUp() would win over any later registration (stub callbacks are
     * first-match-wins).
     */
    private function fakeProbeOk(): void
    {
        Http::fake([
            'ai.example.test/*' => Http::response(['choices' => [['message' => ['content' => 'pong']]]], 200),
        ]);
    }

    #[Test]
    public function it_resolves_a_data_file_that_hoists_shared_variables(): void
    {
        $this->app['env'] = 'local';
        $this->seedActor();
        $this->fakeProbeOk();

        // The shipped shape: one shared endpoint + key referenced by every entry.
        $this->writeRawDataFile(<<<'PHP'
        <?php

        $endpoint = 'https://ai.example.test/v1/chat/completions';
        $key = 'sk-shared-key';

        return [
            'connections' => [
                'microstock-vision' => [
                    'label' => 'Shared endpoint',
                    'endpoint' => $endpoint,
                    'api_key' => $key,
                    'binds' => ['microstock.ai.vision.connection'],
                    'is_default' => true,
                ],
                'microstock-thinking' => [
                    'endpoint' => $endpoint,
                    'api_key' => $key,
                    'binds' => ['microstock.ai.thinking.connection'],
                ],
            ],
        ];
        PHP);

        $this->seed(AiConnectionSeeder::class);

        $this->assertDatabaseCount('ai_connections', 2);

        foreach (['microstock-vision', 'microstock-thinking'] as $slug) {
            $connection = AiConnection::query()->where('slug', $slug)->firstOrFail();
            $this->assertSame('https://ai.example.test/v1/chat/completions', $connection->endpoint);
            $this->assertSame('sk-shared-key', $connection->secret_payload['api_key']);
            $this->assertSame(AiConnectionStatus::Active, $connection->status);
        }

        // A missing `label` falls back to the slug.
        $this->assertSame('microstock-thinking', AiConnection::query()->where('slug', 'microstock-thinking')->firstOrFail()->label);
    }

    #[Test]
    public function a_data_file_can_share_one_variable_and_override_it_per_entry(): void
    {
        $this->app['env'] = 'local';
        $this->seedActor();
        $this->fakeProbeOk();

        $this->writeRawDataFile(<<<'PHP'
        <?php

        $endpoint = 'https://ai.example.test/v1/chat/completions';
        $key = 'sk-shared-key';
        $keyThinking = 'sk-thinking-key';

        return [
            'connections' => [
                'microstock-vision' => [
                    'endpoint' => $endpoint,
                    'api_key' => $key,
                    'binds' => ['microstock.ai.vision.connection'],
                ],
                'microstock-thinking' => [
                    'endpoint' => $endpoint,
                    'api_key' => $keyThinking,
                    'binds' => ['microstock.ai.thinking.connection'],
                ],
            ],
        ];
        PHP);

        $this->seed(AiConnectionSeeder::class);

        $this->assertSame(
            'sk-shared-key',
            AiConnection::query()->where('slug', 'microstock-vision')->firstOrFail()->secret_payload['api_key'],
        );
        $this->assertSame(
            'sk-thinking-key',
            AiConnection::query()->where('slug', 'microstock-thinking')->firstOrFail()->secret_payload['api_key'],
        );
    }

    #[Test]
    public function a_null_api_key_in_the_template_is_skipped_not_fatal(): void
    {
        $this->app['env'] = 'local';
        $this->seedActor();

        // `$key = null` is the shipped placeholder for "not filled in yet".
        $this->writeRawDataFile(<<<'PHP'
        <?php

        $endpoint = 'https://ai.example.test/v1/chat/completions';
        $key = null;

        return [
            'connections' => [
                'microstock-vision' => [
                    'endpoint' => $endpoint,
                    'api_key' => $key,
                    'binds' => ['microstock.ai.vision.connection'],
                ],
            ],
        ];
        PHP);

        $this->seed(AiConnectionSeeder::class);

        $this->assertDatabaseCount('ai_connections', 0);
        Http::assertNothingSent();
    }

    #[Test]
    public function a_data_file_cannot_clobber_the_seeders_own_variables(): void
    {
        $this->app['env'] = 'local';
        $this->seedActor();
        $this->fakeProbeOk();

        // The file is loaded in an isolated scope: these names collide with the
        // seeder's internals and must not break resolution.
        $this->writeRawDataFile(<<<'PHP'
        <?php

        $path = '/nowhere';
        $data = 'not-an-array';
        $connections = 'not-an-array';
        $seeded = ['ghost'];
        $bindings = ['microstock.ai.vision.connection' => 'ghost'];
        $actor = null;

        return [
            'connections' => [
                'microstock-vision' => [
                    'endpoint' => 'https://ai.example.test/v1/chat/completions',
                    'api_key' => 'sk-vision-key',
                    'binds' => ['microstock.ai.vision.connection'],
                ],
            ],
        ];
        PHP);

        $this->seed(AiConnectionSeeder::class);

        $this->assertDatabaseHas('ai_connections', ['slug' => 'microstock-vision']);
        $this->assertSame('sk-vision-key', AiConnection::query()->firstOrFail()->secret_payload['api_key']);

        $snapshot = app(SystemSettingRepositoryInterface::class)->snapshot();
        $this->assertSame('microstock-vision', $snapshot['microstock.ai.vision.connection'] ?? null);
    }

    #[Test]
    public function it_inherits_the_defaults_block_and_lets_an_entry_override_it(): void
    {
        $this->app['env'] = 'local';
        $this->seedActor();
        $this->fakeProbeOk();

        // The shipped shape: one shared endpoint + key in `defaults`, entries
        // declare only what differs.
        $this->writeRawDataFile(<<<'PHP'
        <?php

        return [
            'probe' => true,
            'defaults' => [
                'endpoint' => 'https://ai.example.test/v1/chat/completions',
                'api_key' => 'sk-shared-key',
            ],
            'connections' => [
                'microstock-vision' => [
                    'label' => 'Inherits both',
                    'binds' => ['microstock.ai.vision.connection'],
                    'is_default' => true,
                ],
                'microstock-thinking' => [
                    'label' => 'Own endpoint',
                    'endpoint' => 'https://ai.example.test/v2/chat/completions',
                    'binds' => ['microstock.ai.thinking.connection'],
                ],
            ],
        ];
        PHP);

        $this->seed(AiConnectionSeeder::class);

        $this->assertDatabaseCount('ai_connections', 2);

        $vision = AiConnection::query()->where('slug', 'microstock-vision')->firstOrFail();
        $this->assertSame('https://ai.example.test/v1/chat/completions', $vision->endpoint);
        $this->assertSame('sk-shared-key', $vision->secret_payload['api_key']);

        $thinking = AiConnection::query()->where('slug', 'microstock-thinking')->firstOrFail();
        $this->assertSame('https://ai.example.test/v2/chat/completions', $thinking->endpoint);
        $this->assertSame('sk-shared-key', $thinking->secret_payload['api_key']);

        // label / binds / is_default are never inherited.
        $this->assertSame('Inherits both', $vision->label);
        $this->assertSame('Own endpoint', $thinking->label);
        $this->assertTrue($vision->is_default);
        $this->assertFalse($thinking->is_default);
    }

    #[Test]
    public function an_entry_with_a_blank_value_is_not_rescued_by_the_defaults_block(): void
    {
        $this->app['env'] = 'local';
        $this->seedActor();
        $this->fakeProbeOk();

        $this->writeRawDataFile(<<<'PHP'
        <?php

        return [
            'defaults' => [
                'endpoint' => 'https://ai.example.test/v1/chat/completions',
                'api_key' => 'sk-shared-key',
            ],
            'connections' => [
                'microstock-vision' => [
                    'endpoint' => '',
                    'binds' => ['microstock.ai.vision.connection'],
                ],
                'microstock-thinking' => [
                    'binds' => ['microstock.ai.thinking.connection'],
                ],
            ],
        ];
        PHP);

        $this->seed(AiConnectionSeeder::class);

        // Explicit blank parks the entry; the other inherits and seeds.
        $this->assertDatabaseCount('ai_connections', 1);
        $this->assertDatabaseHas('ai_connections', ['slug' => 'microstock-thinking']);
    }

    #[Test]
    public function a_defaults_block_cannot_smuggle_per_entry_keys(): void
    {
        $this->app['env'] = 'local';
        $this->seedActor();
        $this->fakeProbeOk();

        $this->writeRawDataFile(<<<'PHP'
        <?php

        return [
            'defaults' => [
                'endpoint' => 'https://ai.example.test/v1/chat/completions',
                'api_key' => 'sk-shared-key',
                // Ignored: neither key is inheritable.
                'label' => 'Shared label',
                'is_default' => true,
                'binds' => ['microstock.ai.vision.connection'],
            ],
            'connections' => [
                'microstock-vision' => [
                    'test_model' => 'probe-model',
                ],
            ],
        ];
        PHP);

        $this->seed(AiConnectionSeeder::class);

        $vision = AiConnection::query()->where('slug', 'microstock-vision')->firstOrFail();
        $this->assertSame('https://ai.example.test/v1/chat/completions', $vision->endpoint);
        $this->assertSame('sk-shared-key', $vision->secret_payload['api_key']);
        $this->assertSame('probe-model', $vision->test_model);

        // `label`, `is_default` and `binds` are per-entry — the defaults block
        // must not leak them.
        $this->assertSame('microstock-vision', $vision->label);
        $this->assertFalse($vision->is_default);

        $snapshot = app(SystemSettingRepositoryInterface::class)->snapshot();
        $this->assertArrayNotHasKey('microstock.ai.vision.connection', $snapshot);
    }

    private function seedActor(): User
    {
        $actor = User::factory()->create(['password' => Hash::make('password123')]);
        $actor->assignRole('Root');

        return $actor;
    }

    /**
     * @param  array<string, mixed>|null  $connections
     */
    private function writeDataFile(?array $connections = null, bool $probe = true): void
    {
        $payload = [
            'probe' => $probe,
            'connections' => $connections ?? [
                'microstock-vision' => [
                    'label' => 'Microstock vision (local)',
                    'endpoint' => 'https://ai.example.test/v1/chat/completions',
                    'api_key' => 'sk-vision-key',
                    'binds' => ['microstock.ai.vision.connection'],
                    'is_default' => true,
                ],
                'microstock-thinking' => [
                    'label' => 'Microstock thinking (local)',
                    'endpoint' => 'https://ai.example.test/v1/chat/completions',
                    'api_key' => 'sk-thinking-key',
                    'binds' => ['microstock.ai.thinking.connection'],
                ],
            ],
        ];

        File::put(
            (string) config('ai.seed.path'),
            '<?php return '.var_export($payload, true).';'.PHP_EOL,
        );
    }

    /**
     * Write a raw data file, so tests can exercise the plain-PHP idioms a real
     * gitignored file uses (hoisted variables, mid-file expressions).
     */
    private function writeRawDataFile(string $php): void
    {
        File::put((string) config('ai.seed.path'), $php);
    }
}
