<?php

namespace Voyager\Database\Testing\Concerns;

use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Voyager\Contracts\Console\Kernel;

/**
 * Runs a database console command (migrate:fresh, migrate:rollback) for a test, failing loudly on a non-zero exit.
 */
trait RunsDatabaseCommands
{
    /**
     * @param  array<string, mixed>  $parameters
     *
     * @throws \RuntimeException when the command exits non-zero, carrying its output
     */
    protected function runDatabaseCommand(string $command, array $parameters = []): void
    {
        $input = new ArrayInput(['command' => $command, '--force' => true, ...$parameters]);
        $input->setInteractive(false);

        $status = $this->app->make(Kernel::class)->handle($input, $output = new BufferedOutput);

        if ($status !== 0) {
            throw new RuntimeException("[{$command}] exited with {$status}:\n".$output->fetch());
        }
    }
}
