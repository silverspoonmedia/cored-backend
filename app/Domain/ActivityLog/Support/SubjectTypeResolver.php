<?php

namespace App\Domain\ActivityLog\Support;

use App\Domain\Navigation\Models\Menu;
use App\Domain\Playground\Models\Order;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Maps short API filter aliases to Eloquent morph class names.
 */
class SubjectTypeResolver
{
    /** @var array<string, class-string<Model>> */
    private const ALIASES = [
        'user' => User::class,
        'order' => Order::class,
        'menu' => Menu::class,
    ];

    /**
     * @return class-string<Model>|null
     */
    public function resolveMorphClass(?string $alias): ?string
    {
        if ($alias === null || $alias === '') {
            return null;
        }

        $class = self::ALIASES[$alias] ?? $alias;

        if (! is_subclass_of($class, Model::class)) {
            return null;
        }

        return (new $class)->getMorphClass();
    }

    /**
     * @return list<string>
     */
    public function supportedAliases(): array
    {
        return array_keys(self::ALIASES);
    }
}
