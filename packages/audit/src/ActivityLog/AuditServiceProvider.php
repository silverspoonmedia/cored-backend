<?php

namespace App\Domain\ActivityLog;

use App\Domain\ActivityLog\Console\Commands\EncryptionMigrateActivityLogCommand;
use App\Domain\ActivityLog\Repositories\Contracts\ActivityLogRepositoryInterface;
use App\Domain\ActivityLog\Repositories\Eloquent\ActivityLogRepository;
use App\Domain\ActivityLog\Support\AuthActivityRecorder as AuditAuthRecorder;
use App\Domain\ActivityLog\Support\CryptoActivityRecorder;
use App\Domain\ActivityLog\Support\SensitiveActivityProperties;
use App\Domain\ActivityLog\Support\SubjectLabelRegistry;
use App\Domain\ActivityLog\Support\SubjectTypeResolver;
use App\Domain\ErrorLog\Console\Commands\CleanErrorLogsCommand;
use App\Domain\ErrorLog\Repositories\Contracts\ErrorLogRepositoryInterface;
use App\Domain\ErrorLog\Repositories\Eloquent\ErrorLogRepository;
use App\Domain\ErrorLog\Support\ErrorLogRecorder;
use App\Support\ActivityLog\AuthActivityRecorder;
use App\Support\ActivityLog\SubjectAliasRegistrar;
use App\Support\ActivityLog\SubjectLabelRegistrar;
use App\Support\Encryption\DecryptionAuditRecorder;
use App\Support\ErrorLogging\ExceptionRecorder;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Spatie\Activitylog\Actions\LogActivityAction;
use Spatie\Activitylog\Contracts\Activity as ActivityContract;
use Throwable;

/**
 * One provider for `silverspoonmedia/audit` — two core domains, one package.
 *
 * ActivityLog and ErrorLog are the same operational surface (what happened vs
 * what broke) and are consumed together, so they ship as one Composer package
 * while staying two namespaces, two route files and two registry entries.
 */
class AuditServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/errorlog.php', 'errorlog');

        $this->app->bind(ActivityLogRepositoryInterface::class, ActivityLogRepository::class);
        $this->app->bind(ErrorLogRepositoryInterface::class, ErrorLogRepository::class);

        // Kernel contracts — the kernel declares them, this package implements
        // them, so `EnvelopeEncryptionService` and `bootstrap/app.php` never
        // import a package class. The auth trail is a subscriber registered by
        // this package (`AuthActivityRecorder::subscribe()`), not by the kernel.
        $this->app->bind(DecryptionAuditRecorder::class, CryptoActivityRecorder::class);
        $this->app->bind(ExceptionRecorder::class, ErrorLogRecorder::class);
        $this->app->bind(AuthActivityRecorder::class, AuditAuthRecorder::class);

        $this->app->singleton(SubjectTypeResolver::class);
        $this->app->bind(SubjectAliasRegistrar::class, SubjectTypeResolver::class);

        $this->app->singleton(SubjectLabelRegistry::class);
        $this->app->bind(SubjectLabelRegistrar::class, SubjectLabelRegistry::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        // Plain (non-namespaced) lang path: FileLoader::loadPaths() resolves
        // "{path}/{locale}/{group}.php", so the path is the parent of the locale
        // directories. Keys stay `api/activity-log.*` / `api/error-log.*`.
        $this->app['translator']->getLoader()->addPath(__DIR__.'/../../resources/lang');

        if ($this->app->runningInConsole()) {
            $this->commands([
                CleanErrorLogsCommand::class,
                EncryptionMigrateActivityLogCommand::class,
            ]);
        }

        $this->registerSensitivePropertyEncryption();
        $this->registerErrorLogListener();

        // The activity-log half of the auth trail lives with this package: the
        // kernel listener & subscriber pair would otherwise make every auth event
        // carry a package class through the kernel.
        Event::subscribe(AuditAuthRecorder::class);
    }

    /**
     * Envelope-encrypt PII inside `properties` right before the audit row is
     * written (single choke point for every recorder, including the `activity()`
     * helper). Static callback list — clear first so re-boots in tests do not
     * stack duplicate callbacks.
     */
    private function registerSensitivePropertyEncryption(): void
    {
        LogActivityAction::clearBeforeLoggingCallbacks();
        LogActivityAction::beforeLogging(
            static fn (ActivityContract $activity) => SensitiveActivityProperties::encryptInPlace($activity),
        );
    }

    /**
     * `Log::error`+ becomes a grouped error row. Exceptions arrive through the
     * kernel's `reportable` hook + {@see ExceptionRecorder} instead, so the two
     * entries never double-count one failure.
     */
    private function registerErrorLogListener(): void
    {
        Event::listen(MessageLogged::class, function (MessageLogged $event): void {
            if (! setting_config('errorlog.enabled', true)) {
                return;
            }

            try {
                app(ErrorLogRecorder::class)->recordLog(
                    $event->level,
                    $event->message,
                    $event->context,
                );
            } catch (Throwable) {
                // Never break the logging pipeline.
            }
        });
    }
}
