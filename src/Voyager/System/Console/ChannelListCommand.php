<?php

namespace Voyager\System\Console;

use Closure;
use Voyager\Console\Command;
use Voyager\Contracts\Broadcasting\Broadcaster;
use Voyager\NutsAndBolts\Collection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Terminal;

#[AsCommand(name: 'channel:list')]
class ChannelListCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected ?string $name = 'channel:list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected string $description = 'List all registered private broadcast channels';

    /**
     * The terminal width resolver callback.
     *
     * @var \Closure|null
     */
    protected static ?\Closure $terminalWidthResolver = null;

    /**
     * Execute the console command.
     *
     * @param  \Voyager\Contracts\Broadcasting\Broadcaster  $broadcaster
     * @return void
     */
    public function handle(Broadcaster $broadcaster): void
    {
        $channels = $broadcaster->getChannels();

        if (! $this->venusian->providerIsLoaded('App\Providers\BroadcastServiceProvider') &&
            file_exists($this->venusian->path('Providers/BroadcastServiceProvider.php'))) {
            $this->components->warn('The [App\Providers\BroadcastServiceProvider] has not been loaded. Your private channels may not be loaded.');
        }

        if (! $channels->count()) {
            $this->components->error("Your application doesn't have any private broadcasting channels.");

            return;
        }

        $this->displayChannels($channels);
    }

    /**
     * Display the channel information on the console.
     *
     * @param  Collection  $channels
     * @return void
     */
    protected function displayChannels(Collection $channels): void
    {
        $this->output->writeln($this->forCli($channels));
    }

    /**
     * Convert the given channels to regular CLI output.
     *
     * @param  \Voyager\NutsAndBolts\Collection  $channels
     * @return array
     */
    protected function forCli(\Voyager\NutsAndBolts\Collection $channels): array
    {
        $maxChannelName = $channels->keys()->max(function ($channelName) {
            return mb_strlen($channelName);
        });

        $terminalWidth = $this->getTerminalWidth();

        $channelCount = $this->determineChannelCountOutput($channels, $terminalWidth);

        return $channels->map(function ($channel, $channelName) use ($maxChannelName, $terminalWidth) {
            $resolver = $channel instanceof Closure ? 'Closure' : $channel;

            $spaces = str_repeat(' ', max($maxChannelName + 6 - mb_strlen($channelName), 0));

            $dots = str_repeat('.', max(
                $terminalWidth - mb_strlen($channelName.$spaces.$resolver) - 6, 0
            ));

            $dots = empty($dots) ? $dots : " $dots";

            return sprintf(
                '  <fg=blue;options=bold>%s</> %s<fg=white>%s</><fg=#6C7280>%s</>',
                $channelName,
                $spaces,
                $resolver,
                $dots,
            );
        })
            ->filter()
            ->sort()
            ->prepend('')
            ->push('')->push($channelCount)->push('')
            ->toArray();
    }

    /**
     * Determine and return the output for displaying the number of registered channels in the CLI output.
     *
     * @param  \Voyager\NutsAndBolts\Collection  $channels
     * @param  int  $terminalWidth
     * @return string
     */
    protected function determineChannelCountOutput(\Voyager\NutsAndBolts\Collection $channels, int $terminalWidth): string
    {
        $channelCountText = 'Showing ['.$channels->count().'] private channels';

        $offset = $terminalWidth - mb_strlen($channelCountText) - 2;

        $spaces = str_repeat(' ', $offset);

        return $spaces.'<fg=blue;options=bold>Showing ['.$channels->count().'] private channels</>';
    }

    /**
     * Get the terminal width.
     *
     * @return int
     */
    public static function getTerminalWidth(): int
    {
        return is_null(static::$terminalWidthResolver)
            ? (new Terminal)->getWidth()
            : call_user_func(static::$terminalWidthResolver);
    }

    /**
     * Set a callback that should be used when resolving the terminal width.
     *
     * @param  \Closure|null  $resolver
     * @return void
     */
    public static function resolveTerminalWidthUsing(?\Closure $resolver): void
    {
        static::$terminalWidthResolver = $resolver;
    }
}
