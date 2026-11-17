<?php

namespace App\Domain\ActivityLog\Support;

use App\Domain\Identity\Models\User;
use App\Support\ActivityLog\SubjectAliasRegistrar;
use Illuminate\Database\Eloquent\Model;

/**
 * Maps short API filter aliases to Eloquent morph class names.
 *
 * The map is a registry, not a constant, so this domain never imports a sibling
 * core package. It seeds only the subject type it owns (`user`, from the Identity
 * hub); a domain that owns another subject type declares its alias from its own
 * provider through the kernel contract {@see SubjectAliasRegistrar} (see
 * `NavigationServiceProvider::boot()` for `menu`). A host without that package
 * simply offers fewer filters.
 *
 * Registered as a singleton — see `AuditServiceProvider`.
 */
class SubjectTypeResolver implements SubjectAliasRegistrar
{
    /** @var array<string, class-string<Model>> */
    private array $aliases = [];

    public function __construct()
    {
        $this->register('user', User::class);
    }

    /**
     * Declare an alias for a model this domain does not own.
     */
    public function register(string $alias, string $modelClass): void
    {
        if ($alias === '' || ! class_exists($modelClass) || ! is_subclass_of($modelClass, Model::class)) {
            return;
        }

        $this->aliases[$alias] = $modelClass;
    }

    /**
     * @return class-string<Model>|null
     */
    public function resolveMorphClass(?string $alias): ?string
    {
        if ($alias === null || $alias === '') {
            return null;
        }

        $class = $this->aliases[$alias] ?? $alias;

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
        return array_keys($this->aliases);
    }
}
