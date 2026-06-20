<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

#[Signature('pint')]
#[Description('Fix the coding style of the given path')]
class PintCommand extends Command
{
    protected function configure(): void
    {
        parent::configure();

        $this->ignoreValidationErrors();
    }

    public function handle(): int
    {
        $process = new Process(
            array_merge([PHP_BINARY, base_path('vendor/bin/pint')], $this->pintArguments()),
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
     * @return list<string>
     */
    protected function pintArguments(): array
    {
        $argv = request()->server('argv', []);

        $index = array_search('pint', $argv, true);

        if ($index === false) {
            return [];
        }

        return array_slice($argv, $index + 1);
    }
}
