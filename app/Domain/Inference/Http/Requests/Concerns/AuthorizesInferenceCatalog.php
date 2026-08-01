<?php

namespace App\Domain\Inference\Http\Requests\Concerns;

use Illuminate\Foundation\Http\FormRequest;

trait AuthorizesInferenceCatalog
{
    protected function canViewInferenceCatalog(): bool
    {
        if (! $this instanceof FormRequest) {
            return false;
        }

        return $this->user()?->can('view inference catalog') ?? false;
    }

    protected function canManageInferenceCatalog(): bool
    {
        if (! $this instanceof FormRequest) {
            return false;
        }

        return $this->user()?->can('manage inference catalog') ?? false;
    }
}
