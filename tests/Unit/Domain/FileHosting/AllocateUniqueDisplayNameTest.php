<?php

namespace Tests\Unit\Domain\FileHosting;

use App\Domain\FileHosting\Models\HostedFile;
use App\Domain\FileHosting\Services\DirectUploadService;
use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class AllocateUniqueDisplayNameTest extends TestCase
{
    use RefreshDatabase;

    private DirectUploadService $service;

    private string $ownerId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = $this->app->make(DirectUploadService::class);
        $this->ownerId = (string) User::factory()->create()->id;
    }

    #[Test]
    public function returns_preferred_when_free(): void
    {
        $this->assertSame('A.zip', $this->allocate('A.zip'));
    }

    #[Test]
    public function renumbers_when_base_taken(): void
    {
        $this->seedFile('A.zip');

        $this->assertSame('A (2).zip', $this->allocate('A.zip'));
    }

    #[Test]
    public function uses_max_active_sequence_plus_one(): void
    {
        $this->seedFile('A.zip');
        $this->seedFile('A (3).zip');

        $this->assertSame('A (4).zip', $this->allocate('A.zip'));
    }

    #[Test]
    public function soft_deleted_base_is_reusable_then_sequence_continues(): void
    {
        $old = $this->seedFile('A.zip');
        $old->delete();
        $this->seedFile('A (3).zip');

        $this->assertSame('A.zip', $this->allocate('A.zip'));

        $this->seedFile('A.zip');
        $this->assertSame('A (4).zip', $this->allocate('A.zip'));
    }

    #[Test]
    public function is_case_insensitive_for_collision(): void
    {
        $this->seedFile('a.zip');

        $this->assertSame('A (2).zip', $this->allocate('A.zip'));
    }

    private function allocate(string $preferred): string
    {
        $method = new ReflectionMethod(DirectUploadService::class, 'allocateUniqueDisplayName');
        $method->setAccessible(true);

        return (string) $method->invoke($this->service, $this->ownerId, null, $preferred);
    }

    private function seedFile(string $name): HostedFile
    {
        return HostedFile::query()->create([
            'user_id' => $this->ownerId,
            'folder_id' => null,
            'name' => $name,
            'disk' => 'local',
            'path' => substr(hash('sha256', uniqid('', true)), 0, 40),
            'size' => 1,
            'mime' => 'application/zip',
        ]);
    }
}
