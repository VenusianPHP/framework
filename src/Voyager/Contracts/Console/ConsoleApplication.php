<?php

namespace Voyager\Contracts\Console;

use Symfony\Component\Console\Output\OutputInterface;

interface ConsoleApplication
{
    /**
     * Run a Computer console command by name.
     *
     * @param  string  $command
     * @param  array  $parameters
     * @param  OutputInterface|null  $outputBuffer
     * @return int
     */
    public function call(string $command, array $parameters = [], ?OutputInterface $outputBuffer = null);

    /**
     * Get the output from the last command.
     *
     * @return string
     */
    public function output(): string;
}