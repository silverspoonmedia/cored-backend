<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * Wrapper so PHPStan is reachable the same way Pint is.
 *
 * `phpstan analyse` needs the Artisan-booted config (`config:clear` first) and a
 * memory limit above the CLI default, so the two flags a developer forgets are
 * passed here rather than documented and skipped.
 */
#[Signature('phpstan')]
#[Description('Run PHPStan static analysis')]
class PhpstanCommand extends Command
{
    protected function configure(): void
    {
        parent::configure();

        $this->ignoreValidationErrors();
    }

    public function handle(): int
    {
        $process = new Process(
            array_merge(
                [PHP_BINARY, base_path('vendor/bin/phpstan')],
                $this->phpstanArguments(),
            ),
            base_path(),
        );

        $process->setTimeout(null);

        if (Process::isTtySupported() && ! $this->input->hasParameterOption(['--help', '-h'])) {
            $process->setTty(true);
        }

        return $process->run(function ($type, string $buffer): void {
            $this->output->write($buffer);
        });
    }

    /**
     * Everything after `phpstan` on the command line, plus the defaults.
     *
     * `analyse` is prepended when no subcommand is given, so
     * `php artisan phpstan` means analyse rather than needing the word twice.
     *
     * @return list<string>
     */
    protected function phpstanArguments(): array
    {
        $argv = request()->server('argv', []);

        $index = array_search('phpstan', $argv, true);

        $passThrough = $index === false ? [] : array_values(array_slice($argv, $index + 1));

        $first = $passThrough[0] ?? null;

        if ($first === null || str_starts_with($first, '-')) {
            array_unshift($passThrough, 'analyse');
        }

        if (! in_array('--no-progress', $passThrough, true)) {
            $passThrough[] = '--no-progress';
        }

        if (! in_array('--memory-limit=1G', $passThrough, true)) {
            $passThrough[] = '--memory-limit=1G';
        }

        return $passThrough;
    }
}
