<?php

namespace Tests\Unit\Domain\FileHosting;

use App\Domain\FileHosting\Models\Folder;
use App\Domain\FileHosting\Models\HostedFile;
use App\Domain\FileHosting\Support\UniqueDisplayNameAllocator;
use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AllocateUniqueDisplayNameTest extends TestCase
{
    use RefreshDatabase;

    private UniqueDisplayNameAllocator $allocator;

    private string $ownerId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->allocator = new UniqueDisplayNameAllocator;
        $this->ownerId = (string) User::factory()->create()->id;
    }

    #[Test]
    public function returns_preferred_when_free(): void
    {
        $this->assertSame('A.zip', $this->allocateFile('A.zip'));
    }

    #[Test]
    public function renumbers_when_base_taken(): void
    {
        $this->seedFile('A.zip');

        $this->assertSame('A (2).zip', $this->allocateFile('A.zip'));
    }

    #[Test]
    public function uses_max_active_sequence_plus_one(): void
    {
        $this->seedFile('A.zip');
        $this->seedFile('A (3).zip');

        $this->assertSame('A (4).zip', $this->allocateFile('A.zip'));
    }

    #[Test]
    public function soft_deleted_base_is_reusable_then_sequence_continues(): void
    {
        $old = $this->seedFile('A.zip');
        $old->delete();
        $this->seedFile('A (3).zip');

        $this->assertSame('A.zip', $this->allocateFile('A.zip'));

        $this->seedFile('A.zip');
        $this->assertSame('A (4).zip', $this->allocateFile('A.zip'));
    }

    #[Test]
    public function is_case_insensitive_for_collision(): void
    {
        $this->seedFile('a.zip');

        $this->assertSame('A (2).zip', $this->allocateFile('A.zip'));
    }

    #[Test]
    public function ignore_file_id_allows_keeping_own_name(): void
    {
        $file = $this->seedFile('A.zip');

        $this->assertSame(
            'A.zip',
            $this->allocator->allocateFile($this->ownerId, null, 'A.zip', null, $file->id),
        );
    }

    #[Test]
    public function folder_allocate_renumbers_without_extension(): void
    {
        $this->seedFolder('Docs');

        $this->assertSame(
            'Docs (2)',
            $this->allocator->allocateFolder($this->ownerId, null, 'Docs'),
        );
    }

    #[Test]
    public function folder_uses_max_sequence_plus_one(): void
    {
        $this->seedFolder('Docs');
        $this->seedFolder('Docs (3)');

        $this->assertSame(
            'Docs (4)',
            $this->allocator->allocateFolder($this->ownerId, null, 'Docs'),
        );
    }

    private function allocateFile(string $preferred, ?string $folderId = null): string
    {
        return $this->allocator->allocateFile($this->ownerId, $folderId, $preferred);
    }

    private function seedFile(string $name, ?string $folderId = null): HostedFile
    {
        return HostedFile::query()->create([
            'user_id' => $this->ownerId,
            'folder_id' => $folderId,
            'name' => $name,
            'disk' => 'local',
            'path' => substr(hash('sha256', uniqid('', true)), 0, 40),
            'size' => 1,
            'mime' => 'application/zip',
        ]);
    }

    private function seedFolder(string $name, ?string $parentId = null): Folder
    {
        return Folder::query()->create([
            'user_id' => $this->ownerId,
            'parent_id' => $parentId,
            'name' => $name,
            'order' => 1,
        ]);
    }
}
