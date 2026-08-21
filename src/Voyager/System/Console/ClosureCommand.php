<?php

namespace Voyager\System\Console;

use Closure;
use Voyager\Console\Command;
use Voyager\Console\ManuallyFailedException;
use Voyager\MagicAliases\Schedule;
use Voyager\NutsAndBolts\Concerns\ForwardsCalls;
use ReflectionFunction;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @mixin \Voyager\Console\Scheduling\Event
 */
class ClosureCommand extends Command
{
    use ForwardsCalls;

    /**
     * The command callback.
     *
     * @var \Closure
     */
    protected \Closure $callback;

    /**
     * The console command description.
     *
     * @var string
     */
    protected string $description = '';

    /**
     * Create a new command instance.
     *
     * @param  string  $signature
     * @param  \Closure  $callback
     */
    public function __construct(string $signature, Closure $callback)
    {
        $this->callback = $callback;
        $this->signature = $signature;

        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $inputs = array_merge($input->getArguments(), $input->getOptions());

        $parameters = [];

        foreach ((new ReflectionFunction($this->callback))->getParameters() as $parameter) {
            if (isset($inputs[$parameter->getName()])) {
                $parameters[$parameter->getName()] = $inputs[$parameter->getName()];
            }
        }

        try {
            return (int) $this->venusian->call(
                $this->callback->bindTo($this, $this), $parameters
            );
        } catch (ManuallyFailedException $e) {
            $this->components->error($e->getMessage());

            return static::FAILURE;
        }
    }

    /**
     * Set the description for the command.
     *
     * @param  string  $description
     * @return $this
     */
    public function purpose(string $description): static
    {
        return $this->describe($description);
    }

    /**
     * Set the description for the command.
     *
     * @param  string  $description
     * @return $this
     */
    public function describe(string $description): static
    {
        $this->setDescription($description);

        return $this;
    }

    /**
     * Create a new scheduled event for the command.
     *
     * @param  array  $parameters
     * @return \Voyager\Console\Scheduling\Event
     */
    public function schedule(array $parameters = []): \Voyager\Console\Scheduling\Event
    {
        return Schedule::command($this->name, $parameters);
    }

    /**
     * Dynamically proxy calls to a new scheduled event.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     *
     * @throws \BadMethodCallException
     */
    public function __call($method, $parameters): mixed
    {
        return $this->forwardCallTo($this->schedule(), $method, $parameters);
    }
}
