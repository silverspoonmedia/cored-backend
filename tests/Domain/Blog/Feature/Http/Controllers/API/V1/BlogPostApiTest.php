<?php

namespace Tests\Domain\Blog\Feature\Http\Controllers\API\V1;

use App\Domain\Blog\Models\BlogMedia;
use App\Domain\Blog\Models\Category;
use App\Domain\Blog\Models\Post;
use App\Domain\Blog\Models\Tag;
use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class BlogPostApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_admin_can_create_post_with_categories_tags_and_sanitized_content(): void
    {
        $admin = $this->userWithRole('Admin');
        $category = Category::query()->create(['name' => 'News', 'slug' => 'news']);
        $tag = Tag::query()->create(['name' => 'Laravel', 'slug' => 'laravel']);
        $media = $this->createMedia($admin);

        $response = $this->actingAsJwt($admin)
            ->postJson('/api/v1/blog/posts', [
                'title' => 'Hello World',
                'excerpt' => 'First post',
                'content' => '<h2>Intro</h2><p>Hi <script>alert(1)</script><strong>there</strong></p>',
                'status' => 'published',
                'featured_media_id' => $media->id,
                'category_ids' => [$category->id],
                'tag_ids' => [$tag->id],
            ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Hello World')
            ->assertJsonPath('data.slug', 'hello-world')
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.category_ids.0', $category->id)
            ->assertJsonPath('data.tag_ids.0', $tag->id)
            ->assertJsonPath('data.featured_media.id', $media->id);

        $this->assertStringNotContainsString('<script>', (string) $response->json('data.content'));
        $this->assertStringContainsString('<strong>there</strong>', (string) $response->json('data.content'));
    }

    public function test_slug_uniqueness_is_enforced(): void
    {
        $admin = $this->userWithRole('Admin');
        Post::factory()->create(['title' => 'Hello World', 'slug' => 'hello-world']);

        $this->actingAsJwt($admin)
            ->postJson('/api/v1/blog/posts', ['title' => 'Hello World', 'content' => '<p>x</p>'])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'hello-world-2');
    }

    public function test_admin_can_update_post_and_sync_relations(): void
    {
        $admin = $this->userWithRole('Admin');
        $post = Post::factory()->create();
        $categoryA = Category::query()->create(['name' => 'A', 'slug' => 'a']);
        $categoryB = Category::query()->create(['name' => 'B', 'slug' => 'b']);
        $post->categories()->sync([$categoryA->id]);

        $this->actingAsJwt($admin)
            ->putJson('/api/v1/blog/posts/'.$post->id, [
                'title' => 'Edited',
                'status' => 'published',
                'category_ids' => [$categoryB->id],
                'tag_ids' => [],
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Edited')
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.category_ids.0', $categoryB->id)
            ->assertJsonPath('data.published_at', fn ($value) => $value !== null);

        $this->assertDatabaseMissing('blog_category_post', [
            'post_id' => $post->id,
            'category_id' => $categoryA->id,
        ]);
    }

    public function test_index_filters_by_status_and_search_with_paging(): void
    {
        $admin = $this->userWithRole('Admin');
        Post::factory()->published()->create(['title' => 'Alpha post']);
        Post::factory()->draft()->create(['title' => 'Beta post']);
        Post::factory()->count(12)->create(['title' => 'Filler']);

        $response = $this->actingAsJwt($admin)
            ->getJson('/api/v1/blog/posts?status=published&display_mode=query&page=1&per_page=10')
            ->assertOk();

        $this->assertSame(1, $response->json('data.pagination.total'));
        $this->assertSame('Alpha post', $response->json('data.items.0.title'));
    }

    public function test_index_rows_carry_query_row_actions_for_admin(): void
    {
        $admin = $this->userWithRole('Admin');
        $post = Post::factory()->published()->create(['title' => 'Action post']);

        $this->actingAsJwt($admin)
            ->getJson('/api/v1/blog/posts?display_mode=query&page=1&per_page=10')
            ->assertOk()
            ->assertJsonPath('data.items.0.actions.0.key', 'edit')
            ->assertJsonPath('data.items.0.actions.0.route', 'blog.posts.edit')
            ->assertJsonPath('data.items.0.actions.0.params.id', $post->id)
            ->assertJsonPath('data.items.0.actions.1.key', 'delete')
            ->assertJsonPath('data.items.0.actions.1.method', 'DELETE')
            ->assertJsonPath('data.items.0.actions.1.path', 'blog/posts/'.$post->id);
    }

    public function test_index_delete_action_hidden_without_manage_permission(): void
    {
        $manager = $this->userWithRole('Management');
        Post::factory()->published()->create(['title' => 'View only']);

        $response = $this->actingAsJwt($manager)
            ->getJson('/api/v1/blog/posts?display_mode=query&page=1&per_page=10')
            ->assertOk();

        $actions = collect($response->json('data.items.0.actions'));

        $this->assertSame(['edit'], $actions->pluck('key')->all());
    }

    public function test_trashed_rows_carry_restore_action_for_authorized_actor(): void
    {
        $admin = $this->userWithRole('Admin');
        $post = Post::factory()->create(['title' => 'Deleted one']);
        $post->delete();

        $this->actingAsJwt($admin)
            ->getJson('/api/v1/blog/posts/trashed?display_mode=query&page=1&per_page=10')
            ->assertOk()
            ->assertJsonPath('data.items.0.actions.0.key', 'restore')
            ->assertJsonPath('data.items.0.actions.0.method', 'POST')
            ->assertJsonPath('data.items.0.actions.0.path', 'blog/posts/'.$post->id.'/restore');
    }

    public function test_trashed_restore_action_hidden_without_restore_permission(): void
    {
        // Management can view posts but holds no `restore posts`.
        $viewer = $this->userWithRole('Management');
        $post = Post::factory()->create(['title' => 'Deleted two']);
        $post->delete();

        $this->actingAsJwt($viewer)
            ->getJson('/api/v1/blog/posts/trashed?display_mode=query&page=1&per_page=10')
            ->assertOk();

        $this->assertSame([], collect(
            $this->actingAsJwt($viewer)
                ->getJson('/api/v1/blog/posts/trashed?display_mode=query&page=1&per_page=10')
                ->json('data.items.0.actions') ?? []
        )->pluck('key')->all());
    }

    public function test_user_role_cannot_access_blog_management(): void
    {
        $user = $this->userWithRole('User');

        $this->actingAsJwt($user)
            ->postJson('/api/v1/blog/posts', ['title' => 'Nope'])
            ->assertForbidden();

        $this->actingAsJwt($user)
            ->getJson('/api/v1/blog/posts')
            ->assertForbidden();
    }

    public function test_management_role_can_view_but_not_manage(): void
    {
        $manager = $this->userWithRole('Management');

        $this->actingAsJwt($manager)
            ->getJson('/api/v1/blog/posts')
            ->assertOk();

        $this->actingAsJwt($manager)
            ->postJson('/api/v1/blog/posts', ['title' => 'Nope'])
            ->assertForbidden();
    }

    public function test_guest_cannot_access(): void
    {
        $this->getJson('/api/v1/blog/posts')
            ->assertUnauthorized();
    }

    public function test_destroy_soft_deletes_and_restore_recovers(): void
    {
        $admin = $this->userWithRole('Admin');
        $post = Post::factory()->create();

        $this->actingAsJwt($admin)
            ->deleteJson('/api/v1/blog/posts/'.$post->id)
            ->assertOk();

        $this->assertSoftDeleted('blog_posts', ['id' => $post->id]);

        $this->actingAsJwt($admin)
            ->postJson('/api/v1/blog/posts/'.$post->id.'/restore')
            ->assertOk()
            ->assertJsonPath('data.id', $post->id);

        $this->assertDatabaseHas('blog_posts', ['id' => $post->id, 'deleted_at' => null]);
    }

    public function test_trashed_listing_and_show(): void
    {
        $admin = $this->userWithRole('Admin');
        $post = Post::factory()->create(['title' => 'Deleted one']);
        $post->delete();

        $this->actingAsJwt($admin)
            ->getJson('/api/v1/blog/posts/trashed')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Deleted one');

        $this->actingAsJwt($admin)
            ->getJson('/api/v1/blog/posts/'.$post->id)
            ->assertOk()
            ->assertJsonPath('data.id', $post->id);
    }

    public function test_media_upload_stores_file_and_returns_url(): void
    {
        $disk = (string) config('blog.media.disk');
        Storage::fake($disk);
        $admin = $this->userWithRole('Admin');

        $response = $this->actingAsJwt($admin)
            ->post('/api/v1/blog/media', [
                'file' => UploadedFile::fake()->image('photo.png', 320, 240),
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.width', 320)
            ->assertJsonPath('data.height', 240);

        $path = BlogMedia::query()->sole()->path;
        Storage::disk($disk)->assertExists($path);
    }

    public function test_media_upload_rejects_non_images(): void
    {
        $admin = $this->userWithRole('Admin');

        $this->actingAsJwt($admin)
            ->post('/api/v1/blog/media', [
                'file' => UploadedFile::fake()->create('evil.pdf', 10, 'application/pdf'),
            ])
            ->assertStatus(422);
    }

    public function test_category_and_tag_crud(): void
    {
        $admin = $this->userWithRole('Admin');

        $category = $this->actingAsJwt($admin)
            ->postJson('/api/v1/blog/categories', ['name' => 'Technology'])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'technology')
            ->json('data.id');

        $this->actingAsJwt($admin)
            ->putJson('/api/v1/blog/categories/'.$category, ['name' => 'Tech'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Tech');

        $tag = $this->actingAsJwt($admin)
            ->postJson('/api/v1/blog/tags', ['name' => 'PHP'])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'php')
            ->json('data.id');

        $this->actingAsJwt($admin)
            ->putJson('/api/v1/blog/tags/'.$tag, ['name' => 'PHP 8'])
            ->assertOk();

        $this->actingAsJwt($admin)
            ->deleteJson('/api/v1/blog/tags/'.$tag)
            ->assertOk();

        $this->assertDatabaseMissing('blog_tags', ['id' => $tag]);
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);
        $user->assignRole($roleName);

        return $user;
    }

    private function actingAsJwt(User $user): static
    {
        $token = auth('api')->login($user);

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }

    private function createMedia(User $user): BlogMedia
    {
        return BlogMedia::query()->create([
            'uploaded_by' => $user->id,
            'disk' => 'public',
            'path' => 'blog/test-'.uniqid().'.png',
            'original_name' => 'test.png',
            'mime_type' => 'image/png',
            'size' => 1234,
            'width' => 320,
            'height' => 240,
        ]);
    }
}
