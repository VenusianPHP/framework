<?php

namespace Voyager\Contracts\Sketches;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

interface Kernel
{
    /**
     * Handle an incoming sketch command.
     *
     * @param  InputInterface  $input
     * @param  OutputInterface|null  $output
     * @return int
     */
    public function handle(InputInterface $input, ?OutputInterface $output = null): int;

    /**
     * Terminate the application.
     *
     * @param  InputInterface  $input
     * @param  int  $status
     * @return void
     */
    public function terminate(InputInterface $input, int $status): void;

    /**
     * Set the Computer commands provided by the application.
     *
     * @param  array  $sketches
     * @return $this
     */
    public function addSketches(array $sketches): static;

    /**
     * Set the paths that should have their Computer commands automatically discovered.
     *
     * @param  array  $paths
     * @return $this
     */
    public function addSketchPaths(array $paths): static;
}