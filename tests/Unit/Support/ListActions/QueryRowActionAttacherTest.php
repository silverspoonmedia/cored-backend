<?php

namespace Tests\Unit\Support\ListActions;

use App\Domain\Identity\Models\User;
use App\Support\ListActions\QueryRowAction;
use App\Support\ListActions\QueryRowActionAttacher;
use App\Support\ListActions\QueryRowActionBuilder;
use Tests\TestCase;

class QueryRowActionAttacherTest extends TestCase
{
    public function test_zips_rows_with_models_and_sets_actions(): void
    {
        $first = new User(['name' => 'A']);
        $first->id = 'id-a';
        $second = new User(['name' => 'B']);
        $second->id = 'id-b';

        $builder = new class implements QueryRowActionBuilder
        {
            public function resolve(mixed $model, ?User $actor = null): array
            {
                if (! $model instanceof User) {
                    return [];
                }

                return [
                    QueryRowAction::nav(
                        key: 'edit',
                        label: 'Edit',
                        icon: 'pencil',
                        variant: 'secondary',
                        route: 'users.edit',
                        params: ['id' => (string) $model->id],
                    ),
                ];
            }
        };

        $attached = QueryRowActionAttacher::attach(
            [
                ['id' => 'id-a', 'name' => 'A'],
                ['id' => 'id-b', 'name' => 'B'],
            ],
            [$first, $second],
            $builder,
            null,
        );

        $this->assertSame('edit', $attached[0]['actions'][0]['key']);
        $this->assertSame('users.edit', $attached[0]['actions'][0]['route']);
        $this->assertSame(['id' => 'id-b'], $attached[1]['actions'][0]['params']);
    }

    public function test_unwraps_resource_collection_data_key(): void
    {
        $user = new User(['name' => 'A']);
        $user->id = 'id-a';

        $builder = new class implements QueryRowActionBuilder
        {
            public function resolve(mixed $model, ?User $actor = null): array
            {
                return [];
            }
        };

        $attached = QueryRowActionAttacher::attach(
            ['data' => [['id' => 'id-a']]],
            [$user],
            $builder,
        );

        $this->assertSame('id-a', $attached[0]['id']);
        $this->assertSame([], $attached[0]['actions']);
    }
}
