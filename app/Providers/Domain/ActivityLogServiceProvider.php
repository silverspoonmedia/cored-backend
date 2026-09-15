<?php

namespace App\Providers\Domain;

use App\Domain\ActivityLog\Repositories\Contracts\ActivityLogRepositoryInterface;
use App\Domain\ActivityLog\Repositories\Eloquent\ActivityLogRepository;
use App\Domain\ActivityLog\Support\SensitiveActivityProperties;
use Illuminate\Support\ServiceProvider;
use Spatie\Activitylog\Actions\LogActivityAction;

class ActivityLogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ActivityLogRepositoryInterface::class, ActivityLogRepository::class);
    }

    public function boot(): void
    {
        // Envelope-encrypt PII inside `properties` right before the audit row is
        // written (single choke point for every recorder, including the `activity()`
        // helper). Static callback list — clear first so re-boots in tests do not
        // stack duplicate callbacks.
        LogActivityAction::clearBeforeLoggingCallbacks();
        LogActivityAction::beforeLogging(
            static fn ($activity) => SensitiveActivityProperties::encryptInPlace($activity),
        );
    }
}
