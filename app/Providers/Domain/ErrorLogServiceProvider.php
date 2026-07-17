<?php

namespace App\Providers\Domain;

use App\Domain\ErrorLog\Repositories\Contracts\ErrorLogRepositoryInterface;
use App\Domain\ErrorLog\Repositories\Eloquent\ErrorLogRepository;
use App\Domain\ErrorLog\Support\ErrorLogRecorder;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Throwable;

class ErrorLogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ErrorLogRepositoryInterface::class, ErrorLogRepository::class);
    }

    public function boot(): void
    {
        Event::listen(MessageLogged::class, function (MessageLogged $event): void {
            if (! config('errorlog.enabled', true)) {
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
