<?php

namespace App\Events;

use App\Notifications\Activity\NotificationType;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired whenever an action should raise a role-targeted activity notification.
 *
 * This is the single generic entry point: any feature (user created, order
 * placed, …) dispatches this event and the SendActivityNotification listener
 * persists + fans it out. Emitters only describe WHAT happened — the engine
 * decides storage and (role-based) visibility.
 */
class ActivityNotificationEvent
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $data  Message replacements + render context.
     * @param  list<string>|null  $roles  Target role names; null = type defaults.
     * @param  array<string, mixed>  $actionParams  Route params for the deep link.
     */
    public function __construct(
        public readonly NotificationType $type,
        public readonly array $data = [],
        public readonly ?array $roles = null,
        public readonly ?string $actionRoute = null,
        public readonly array $actionParams = [],
    ) {}
}
