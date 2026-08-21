<?php

namespace Voyager\Queue\Console;

use Voyager\Console\Command;
use Voyager\Contracts\Cache\Repository as Cache;
use Voyager\NutsAndBolts\Concerns\InteractsWithTime;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'queue:restart')]
class RestartCommand extends Command
{
    use InteractsWithTime;

    /**
     * The console command name.
     *
     * @var string
     */
    protected ?string $name = 'queue:restart';

    /**
     * The console command description.
     *
     * @var string
     */
    protected string $description = 'Restart queue worker daemons after their current job';

    /**
     * The cache store implementation.
     *
     * @var \Voyager\Contracts\Cache\Repository
     */
    protected $cache;

    /**
     * Create a new queue restart command.
     *
     * @param  \Voyager\Contracts\Cache\Repository  $cache
     */
    public function __construct(Cache $cache)
    {
        parent::__construct();

        $this->cache = $cache;
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->cache->forever('illuminate:queue:restart', $this->currentTime());

        $this->components->info('Broadcasting queue restart signal.');
    }
}
