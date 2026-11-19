<?php

namespace App\Domain\ActivityLog\Support;

use App\Domain\ActivityLog\Support\Labels\UserSubjectLabelPresenter;
use App\Domain\Identity\Models\User;
use App\Support\ActivityLog\SubjectLabelPresenter;
use App\Support\ActivityLog\SubjectLabelRegistrar;
use Illuminate\Database\Eloquent\Model;

/**
 * Registry behind the kernel's {@see SubjectLabelRegistrar} seam.
 *
 * Seeds only the subject type this package can name without importing a sibling —
 * `User`, from the Identity hub, which every package may read. A domain that owns
 * another subject type registers its own presenter from its own provider (see
 * `NavigationServiceProvider::boot()` for `Menu`). A host without that package
 * falls back to the generic label, so the resource never depends on an absent class.
 *
 * Registered as a singleton — see `AuditServiceProvider`.
 */
class SubjectLabelRegistry implements SubjectLabelRegistrar
{
    /** @var array<class-string<Model>, SubjectLabelPresenter> */
    private array $presenters = [];

    public function __construct()
    {
        $this->register(User::class, new UserSubjectLabelPresenter);
    }

    /**
     * Declare how a model class is labelled in the trail.
     *
     * @param  class-string<Model>  $modelClass
     */
    public function register(string $modelClass, SubjectLabelPresenter $presenter): void
    {
        if (! class_exists($modelClass) || ! is_subclass_of($modelClass, Model::class)) {
            return;
        }

        $this->presenters[$modelClass] = $presenter;
    }

    /**
     * Resolve the presenter for a subject, or `null` when no domain registered one.
     */
    public function resolve(Model $subject): ?SubjectLabelPresenter
    {
        return $this->presenters[$subject::class] ?? null;
    }
}
