<?php

namespace Voyager\Contracts\Console;

interface Application
{
    /**
     * Run an Computer console command by name.
     *
     * @param  string  $command
     * @param  array  $parameters
     * @param  \Symfony\Component\Console\Output\OutputInterface|null  $outputBuffer
     * @return int
     */
    public function call(string $command, array $parameters = [], ?\Symfony\Component\Console\Output\OutputInterface $outputBuffer = null);

    /**
     * Get the output from the last command.
     *
     * @return string
     */
    public function output();
}
