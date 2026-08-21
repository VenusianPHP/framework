<?php

namespace Voyager\Contracts\Console;

interface Kernel
{
    /**
     * Bootstrap the application for computer commands.
     *
     * @return void
     */
    public function bootstrap();

    /**
     * Handle an incoming console command.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface|null  $output
     * @return int
     */
    public function handle(\Symfony\Component\Console\Input\InputInterface $input, ?\Symfony\Component\Console\Output\OutputInterface $output = null);

    /**
     * Run a Computer console command by name.
     *
     * @param  string  $command
     * @param  array  $parameters
     * @param  \Symfony\Component\Console\Output\OutputInterface|null  $outputBuffer
     * @return int
     */
    public function call(string $command, array $parameters = [], ?\Symfony\Component\Console\Output\OutputInterface $outputBuffer = null);

    /**
     * Queue a Computer console command by name.
     *
     * @param  string  $command
     * @param  array  $parameters
     * @return \Voyager\System\Bus\PendingDispatch
     */
    public function queue(string $command, array $parameters = []);

    /**
     * Get every command registered with the console.
     *
     * @return array
     */
    public function all();

    /**
     * Get the output for the last run command.
     *
     * @return string
     */
    public function output();

    /**
     * Terminate the application.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  int  $status
     * @return void
     */
    public function terminate(\Symfony\Component\Console\Input\InputInterface $input, int $status);
}
