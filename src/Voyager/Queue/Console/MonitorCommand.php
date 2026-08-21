<?php

namespace Voyager\Queue\Console;

use Voyager\Console\Command;
use Voyager\Contracts\Events\Dispatcher;
use Voyager\Contracts\Queue\Factory;
use Voyager\Queue\Events\QueueBusy;
use Voyager\NutsAndBolts\DataObjects\Carbon;
use Voyager\NutsAndBolts\Collection;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'queue:monitor')]
class MonitorCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected ?string $signature = 'queue:monitor
                       {queues : The names of the queues to monitor}
                       {--max=1000 : The maximum number of jobs that can be on the queue before an event is dispatched}
                       {--json : Output the queue size as JSON}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected string $description = 'Monitor the size of the specified queues';

    /**
     * The queue manager instance.
     *
     * @var \Voyager\Contracts\Queue\Factory
     */
    protected $manager;

    /**
     * The events dispatcher instance.
     *
     * @var \Voyager\Contracts\Events\Dispatcher
     */
    protected $events;

    /**
     * Create a new queue monitor command.
     *
     * @param  \Voyager\Contracts\Queue\Factory  $manager
     * @param  \Voyager\Contracts\Events\Dispatcher  $events
     */
    public function __construct(Factory $manager, Dispatcher $events)
    {
        parent::__construct();

        $this->manager = $manager;
        $this->events = $events;
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $queues = $this->parseQueues($this->argument('queues'));

        if ($this->option('json')) {
            $this->output->writeln((new Collection($queues))->map(function ($queue) {
                return array_merge($queue, [
                    'status' => str_contains($queue['status'], 'ALERT') ? 'ALERT' : 'OK',
                ]);
            })->toJson());
        } else {
            $this->displaySizes($queues);
        }

        $this->dispatchEvents($queues);
    }

    /**
     * Parse the queues into an array of the connections and queues.
     *
     * @param  string  $queues
     * @return \Voyager\NutsAndBolts\Collection
     */
    protected function parseQueues($queues)
    {
        return (new Collection(explode(',', $queues)))->map(function ($queue) {
            [$connection, $queue] = array_pad(explode(':', $queue, 2), 2, null);

            if (! isset($queue)) {
                $queue = $connection;
                $connection = $this->venusian['config']['queue.default'];
            }

            return [
                'connection' => $connection,
                'queue' => $queue,
                'size' => $size = $this->manager->connection($connection)->size($queue),
                'pending' => method_exists($this->manager->connection($connection), 'pendingSize')
                    ? $this->manager->connection($connection)->pendingSize($queue)
                    : null,
                'delayed' => method_exists($this->manager->connection($connection), 'delayedSize')
                    ? $this->manager->connection($connection)->delayedSize($queue)
                    : null,
                'reserved' => method_exists($this->manager->connection($connection), 'reservedSize')
                    ? $this->manager->connection($connection)->reservedSize($queue)
                    : null,
                'oldest_pending' => method_exists($this->manager->connection($connection), 'creationTimeOfOldestPendingJob')
                    ? $this->manager->connection($connection)->creationTimeOfOldestPendingJob($queue)
                    : null,
                'status' => $size >= $this->option('max') ? '<fg=yellow;options=bold>ALERT</>' : '<fg=green;options=bold>OK</>',
            ];
        });
    }

    /**
     * Display the queue sizes in the console.
     *
     * @param  \Voyager\NutsAndBolts\Collection  $queues
     * @return void
     */
    protected function displaySizes(Collection $queues)
    {
        $this->newLine();

        $this->components->twoColumnDetail('<fg=gray>Queue name</>', '<fg=gray>Size / Status</>');

        $queues->each(function ($queue) {
            $name = '['.$queue['connection'].'] '.$queue['queue'];
            $status = '['.$queue['size'].'] '.$queue['status'];

            $this->components->twoColumnDetail($name, $status);
            $this->components->twoColumnDetail('Pending jobs', $queue['pending'] ?? 'N/A');
            $this->components->twoColumnDetail('Delayed jobs', $queue['delayed'] ?? 'N/A');
            $this->components->twoColumnDetail('Reserved jobs', $queue['reserved'] ?? 'N/A');
            $this->components->twoColumnDetail('Oldest pending job', $queue['oldest_pending']
                ? Carbon::createFromTimestamp($queue['oldest_pending'])->diffForHumans()
                : 'N/A'
            );
            $this->line('');
        });

        $this->newLine();
    }

    /**
     * Fire the monitoring events.
     *
     * @param  \Voyager\NutsAndBolts\Collection  $queues
     * @return void
     */
    protected function dispatchEvents(Collection $queues)
    {
        foreach ($queues as $queue) {
            if ($queue['status'] == '<fg=green;options=bold>OK</>') {
                continue;
            }

            $this->events->dispatch(
                new QueueBusy(
                    $queue['connection'],
                    $queue['queue'],
                    $queue['size'],
                )
            );
        }
    }
}
