<?php

namespace App\Domain\ActivityLog\Support\Labels;

use App\Domain\Identity\Models\User;
use App\Support\ActivityLog\SubjectLabelPresenter;
use Illuminate\Database\Eloquent\Model;

/**
 * The one label the audit package can render itself: the user who caused or owns
 * a row. Identity is the host's authentication hub, so reading `User` from here is
 * the documented exception to the no-sibling-import rule.
 */
final class UserSubjectLabelPresenter implements SubjectLabelPresenter
{
    public function label(Model $subject): string
    {
        if (! $subject instanceof User) {
            return (string) ($subject->getKey() ?? '');
        }

        return $subject->name.' ('.$subject->email.')';
    }
}
