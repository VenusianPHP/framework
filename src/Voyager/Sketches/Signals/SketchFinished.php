<?php

declare(strict_types=1);

namespace Voyager\Sketches\Signals;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SketchFinished
{
    /**
     * Create a new event instance.
     *
     * @param  string  $command  The command name.
     * @param  \Symfony\Component\Console\Input\InputInterface  $input  The console input implementation.
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output  The command output implementation.
     * @param  int  $exit_code  The command exit code.
     */
    public function __construct(
        public string $command,
        public InputInterface $input,
        public OutputInterface $output,
        public int $exit_code,
    ) {
    }
}
