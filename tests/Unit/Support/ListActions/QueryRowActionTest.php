<?php

namespace Tests\Unit\Support\ListActions;

use App\Support\ListActions\QueryRowAction;
use InvalidArgumentException;
use Tests\TestCase;

class QueryRowActionTest extends TestCase
{
    public function test_nav_payload_omits_empty_query(): void
    {
        $action = QueryRowAction::nav(
            key: 'edit',
            label: 'Edit',
            icon: 'pencil',
            variant: 'secondary',
            route: 'users.edit',
            params: ['id' => 'abc'],
        );

        $this->assertSame([
            'key' => 'edit',
            'label' => 'Edit',
            'icon' => 'pencil',
            'variant' => 'secondary',
            'route' => 'users.edit',
            'params' => ['id' => 'abc'],
        ], $action->toArray());
    }

    public function test_href_and_mutate_payloads(): void
    {
        $href = QueryRowAction::href(
            key: 'view',
            label: 'View',
            icon: 'box-arrow-up-right',
            variant: 'info',
            href: 'https://example.test/p',
        );
        $this->assertSame('_blank', $href->toArray()['target']);

        $mutate = QueryRowAction::mutate(
            key: 'delete',
            label: 'Delete',
            icon: 'trash3',
            variant: 'danger',
            method: 'delete',
            path: 'users/1',
            confirm: ['title' => 'Delete', 'text' => 'Sure?'],
        );
        $payload = $mutate->toArray();
        $this->assertSame('DELETE', $payload['method']);
        $this->assertSame('users/1', $payload['path']);
        $this->assertSame(['title' => 'Delete', 'text' => 'Sure?'], $payload['confirm']);
    }

    public function test_rejects_mixed_or_empty_destination(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new QueryRowAction(
            key: 'x',
            label: 'X',
            icon: 'x',
            variant: 'secondary',
            route: 'users.edit',
            href: 'https://example.test',
        );
    }
}
