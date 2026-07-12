<?php

namespace App\Console;

use App\Support\Database\DestructiveDatabaseCommandGuard;
use Illuminate\Console\Events\ArtisanStarting;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Event;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\EventDispatcher\EventDispatcher;

class ConsoleSafetyServiceProvider
{
    public function registerDestructiveDatabaseGuard(): void
    {
        $assert = function (string $commandName): void {
            if ($commandName === '') {
                return;
            }

            app(DestructiveDatabaseCommandGuard::class)->assertAllowed($commandName);
        };

        Event::listen(CommandStarting::class, function (CommandStarting $event) use ($assert): void {
            $assert($event->command);
        });

        Event::listen(ArtisanStarting::class, function (ArtisanStarting $event) use ($assert): void {
            $artisan = $event->artisan;
            $dispatcher = $artisan->getDispatcher();

            if ($dispatcher === null) {
                $dispatcher = new EventDispatcher;
                $artisan->setDispatcher($dispatcher);
            }

            $dispatcher->addListener(
                ConsoleEvents::COMMAND,
                function (ConsoleCommandEvent $consoleEvent) use ($assert): void {
                    $assert($consoleEvent->getCommand()?->getName() ?? '');
                },
                100,
            );
        });
    }

    public function registerTestCommandConfigClear(): void
    {
        $clearConfigCache = function (string $commandName): void {
            if ($commandName !== 'test') {
                return;
            }

            $configCache = base_path('bootstrap/cache/config.php');

            if (is_file($configCache)) {
                unlink($configCache);
            }
        };

        Event::listen(CommandStarting::class, function (CommandStarting $event) use ($clearConfigCache): void {
            $clearConfigCache($event->command);
        });

        Event::listen(ArtisanStarting::class, function (ArtisanStarting $event) use ($clearConfigCache): void {
            $artisan = $event->artisan;
            $dispatcher = $artisan->getDispatcher();

            if ($dispatcher === null) {
                $dispatcher = new EventDispatcher;
                $artisan->setDispatcher($dispatcher);
            }

            $dispatcher->addListener(
                ConsoleEvents::COMMAND,
                function (ConsoleCommandEvent $consoleEvent) use ($clearConfigCache): void {
                    $clearConfigCache($consoleEvent->getCommand()?->getName() ?? '');
                },
                200,
            );
        });
    }
}
