<?php

namespace Tests\Unit\Support;

use App\Support\ListQueryPaginator;
use Illuminate\Http\Request;
use Tests\TestCase;

class ListQueryPaginatorTest extends TestCase
{
    public function test_slices_to_default_page_size(): void
    {
        $rows = collect(range(1, 25))->map(fn (int $n): array => ['id' => $n, 'name' => 'Row '.$n]);

        $page = ListQueryPaginator::paginate($rows, Request::create('/api/v1/users', 'GET', [
            'page' => 1,
            'per_page' => 10,
        ]));

        $this->assertCount(10, $page->items());
        $this->assertSame(25, $page->total());
        $this->assertSame(1, $page->currentPage());
        $this->assertSame(3, $page->lastPage());
        $this->assertTrue($page->hasMorePages());
        $this->assertSame(1, $page->items()[0]['id']);
    }

    public function test_search_stringifies_nested_values(): void
    {
        $rows = collect([
            ['id' => 1, 'email' => 'alice@example.com', 'roles' => [['name' => 'Admin']]],
            ['id' => 2, 'email' => 'bob@example.com', 'roles' => [['name' => 'User']]],
        ]);

        $page = ListQueryPaginator::paginate($rows, Request::create('/api/v1/users', 'GET', [
            'page' => 1,
            'per_page' => 10,
            'search' => 'admin',
        ]));

        $this->assertSame(1, $page->total());
        $this->assertSame('alice@example.com', $page->items()[0]['email']);
    }

    public function test_caps_per_page_at_one_hundred(): void
    {
        $this->assertSame(100, ListQueryPaginator::perPage(
            Request::create('/', 'GET', ['per_page' => 500]),
        ));
        $this->assertSame(10, ListQueryPaginator::perPage(
            Request::create('/', 'GET', []),
        ));
    }

    public function test_pagination_meta_shape(): void
    {
        $rows = collect([['id' => 1], ['id' => 2], ['id' => 3]]);
        $page = ListQueryPaginator::paginate($rows, Request::create('/', 'GET', [
            'page' => 2,
            'per_page' => 2,
        ]));

        $this->assertSame([
            'current_page' => 2,
            'last_page' => 2,
            'per_page' => 2,
            'total' => 3,
            'has_more' => false,
        ], ListQueryPaginator::meta($page));
    }
}
