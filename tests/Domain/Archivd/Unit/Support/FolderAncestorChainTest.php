<?php

namespace Tests\Domain\Archivd\Unit\Support;

use App\Domain\Archivd\Models\Folder;
use App\Domain\Archivd\Support\FolderAncestorChain;
use App\Domain\Identity\Models\User;
use Database\Seeders\ArchivdRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class FolderAncestorChainTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $this->seed(ArchivdRoleSeeder::class);
    }

    public function test_ancestors_uses_one_owner_folder_query(): void
    {
        $user = User::factory()->create([
            'email' => 'crumb-'.uniqid('', true).'@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole('User');

        $root = Folder::query()->create([
            'user_id' => $user->id,
            'parent_id' => null,
            'name' => 'Docs',
            'order' => 1,
            'visibility' => 'private',
        ]);
        $child = Folder::query()->create([
            'user_id' => $user->id,
            'parent_id' => $root->id,
            'name' => 'Invoices',
            'order' => 1,
            'visibility' => 'private',
        ]);
        $leaf = Folder::query()->create([
            'user_id' => $user->id,
            'parent_id' => $child->id,
            'name' => '2026',
            'order' => 1,
            'visibility' => 'private',
        ]);

        $folderQueries = 0;
        DB::listen(function ($query) use (&$folderQueries): void {
            if (str_contains((string) $query->sql, 'archivd_folders')) {
                $folderQueries++;
            }
        });

        $crumbs = FolderAncestorChain::ancestors($leaf);

        $this->assertSame([
            ['id' => $root->id, 'name' => 'Docs'],
            ['id' => $child->id, 'name' => 'Invoices'],
        ], $crumbs);
        $this->assertSame(1, $folderQueries);
    }
}
