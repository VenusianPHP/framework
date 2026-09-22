<?php

namespace Voyager\Contracts\Console;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

interface Kernel
{
    /**
     * Bootstrap the application for computer commands.
     *
     * @return void
     */
    public function bootstrap(): void;

    /**
     * Handle an incoming console command.
     *
     * @param  InputInterface  $input
     * @param  OutputInterface|null  $output
     * @return int
     */
    public function handle(InputInterface $input, ?OutputInterface $output = null): int;

    /**
     * Terminate the application.
     *
     * @param InputInterface $input
     * @param  int  $status
     * @return void
     */
    public function terminate(InputInterface $input, int $status): void;


    /**
     * Set the Computer commands provided by the application.
     *
     * @param  array  $commands
     * @return $this
     */
    public function addCommands(array $commands): static;

    /**
     * Set the paths that should have their Computer commands automatically discovered.
     *
     * @param  array  $paths
     * @return $this
     */
    public function addCommandPaths(array $paths): static;


}