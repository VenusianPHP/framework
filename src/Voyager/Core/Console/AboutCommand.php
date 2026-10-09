<?php

namespace Voyager\Core\Console;

use Closure;
use Composer\InstalledVersions;
use Voyager\Console\Command;
use Voyager\NutsAndBolts\Collection;
use Voyager\NutsAndBolts\Composer;
use Voyager\NutsAndBolts\DataObjects\Str;
use Voyager\NutsAndBolts\DataObjects\Stringable;
use Symfony\Component\Console\Attribute\AsCommand;
use Voyager\Core\Extensions\FirstPartyExtension;
use Voyager\Core\Sketches\DeliverMailToSketches;
use Voyager\Contracts\Sketches\Kernel as SketchKernel;

#[AsCommand(name: 'about')]
class AboutCommand extends Command
{
    /**
     * The console command signature.
     *
     * @var string
     */
    protected ?string $signature = 'about {--only= : The section to display}
                {--json : Output the information as JSON}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected string $description = 'Display basic information about your application';

    /**
     * The sections the framework fills, in display order. Package sections follow them.
     *
     * @var array<int, string>
     */
    protected const array SECTIONS = ['Environment', 'Cache', 'Drivers', 'Event Loop', 'Extensions', 'Sketches'];

    /**
     * The data to display: per section, [label, value] pairs and resolvers that return label => value arrays.
     *
     * @var array<string, array<int, array{0: string, 1: mixed}|callable|string>>
     */
    protected static array $data = [];

    /**
     * The registered callables that add custom data to the command output.
     *
     * @var array<int, Closure(): void>
     */
    protected static array $customDataResolvers = [];

    public function __construct(protected Composer $composer)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->gatherApplicationInformation();

        (new Collection(static::$data))
            ->map(fn (array $items) => (new Collection($items))
                ->map(function (array|callable|string $value): array {
                    if (is_array($value)) {
                        return [$value];
                    }

                    if (is_string($value)) {
                        $value = $this->venusian->make($value);
                    }

                    return (new Collection($this->venusian->call($value)))
                        ->map(fn (mixed $value, string $key) => [$key, $value])
                        ->values()
                        ->all();
                })->flatten(1)
            )
            ->sortBy(function (Collection $data, string $key): int {
                $index = array_search($key, static::SECTIONS, true);

                return $index === false ? 99 : $index;
            })
            ->filter(fn (Collection $data, string $key): bool => ! $this->option('only') || in_array($this->toSearchKeyword($key), $this->sections(), true))
            ->pipe(fn (Collection $data) => $this->display($data));

        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * Display the application information.
     */
    protected function display(Collection $data): void
    {
        $this->option('json') ? $this->displayJson($data) : $this->displayDetail($data);
    }

    /**
     * Display the application information as a detail view.
     */
    protected function displayDetail(Collection $data): void
    {
        $data->each(function (Collection $data, string $section): void {
            $this->newLine();

            $this->components->twoColumnDetail('  <fg=green;options=bold>'.$section.'</>');

            $data->pipe(fn (Collection $data) => $section !== 'Environment' ? $data->sort() : $data)->each(function (array $detail): void {
                [$label, $value] = $detail;

                $this->components->twoColumnDetail($label, (string) value($value, false));
            });
        });
    }

    /**
     * Display the application information as JSON.
     */
    protected function displayJson(Collection $data): void
    {
        $output = $data->flatMap(fn (Collection $data, string $section) => [
            (new Stringable($section))->snake()->value() => $data->mapWithKeys(fn (array $item) => [
                $this->toSearchKeyword($item[0]) => value($item[1], true),
            ]),
        ]);

        $this->output->writeln(strip_tags(json_encode($output)));
    }

    /**
     * Gather information about the application.
     */
    protected function gatherApplicationInformation(): void
    {
        self::$data = [];

        $formatCachedStatus = fn (bool $value): string => $value ? '<fg=green;options=bold>CACHED</>' : '<fg=yellow;options=bold>NOT CACHED</>';
        $formatPool = fn (int|false $value): string => $value === false ? 'OFF' : '<fg=yellow;options=bold>ENABLED</> <fg=gray;options=bold>/</> '.$value.' max';

        static::addToSection('Environment', fn () => [
            'Application Name' => config('app.name'),
            'Venusian Version' => InstalledVersions::getPrettyVersion('venusian/framework') ?? $this->venusian->version(),
            'PHP Version' => PHP_VERSION,
            'PHP Binary' => PHP_BINARY,
            'Thread Safety' => PHP_ZTS ? 'ZTS' : 'NTS',
            'Composer Version' => $this->composer->getVersion() ?? '<fg=yellow;options=bold>-</>',
            'Environment' => $this->venusian->environment(),
            'Operating System' => PHP_OS_FAMILY.' '.php_uname('m'),
        ]);

        static::addToSection('Cache', fn () => [
            'Config' => static::format((bool) $this->venusian->configurationIsCached(), console: $formatCachedStatus),
            'Signals' => static::format($this->venusian->signalsAreCached(), console: $formatCachedStatus),
        ]);

        static::addToSection('Drivers', fn () => array_filter([
            'Broadcasting' => config('broadcasting.default'),
            'Cache' => config('cache.default'),
            'Concurrency' => config('concurrency.default'),
            'Database' => config('database.default'),
            'Filesystem' => config('filesystems.default'),
            'Hashing' => config('hashing.driver'),
            'Async Http' => static::resolvedDriver(config('http.async.default', 'auto'), fn () => $this->venusian->get('http.async')->autoDriver()),
            'Logs' => static::withSecondary(config('logging.default'), config('logging.channels.'.config('logging.default').'.driver') === 'stack'
                ? config('logging.channels.'.config('logging.default').'.channels', [])
                : null),
            'Queue' => static::withSecondary(config('queue.default'), config('queue.connections.'.config('queue.default').'.driver') === 'failover'
                ? config('queue.connections.'.config('queue.default').'.connections', [])
                : null),
            'Redis' => config('database.redis.client'),
        ]));

        static::addToSection('Event Loop', fn () => [
            'Waiter Backend' => static::resolvedDriver(config('io-pools.pool_waiters.default', 'auto'), fn () => $this->venusian->get('waiter-backend-mgr')->autoDriver()),
            'Pace' => (int) config('io-pools.event_loop.pace_ms', 16).' ms',
            'Promise Engine' => config('io-pools.promise_engines.default', 'guzzle'),
            'Mail Handler' => config('io-pools.event_loop.mail_handlers.default', 'signal'),
            'Process Workers' => static::format(static::poolSize('process'), console: $formatPool),
            'Thread Workers' => static::format(static::poolSize('threads'), console: $formatPool),
        ]);

        static::addToSection('Extensions', fn () => (new Collection(FirstPartyExtension::cases()))
            ->mapWithKeys(fn (FirstPartyExtension $extension) => [
                $extension->extension() => static::format(
                    extension_loaded($extension->extension()) ? (string) phpversion($extension->extension()) : false,
                    console: fn (string|false $version): string => match (true) {
                        $version !== false => '<fg=green;options=bold>'.$version.'</>',
                        $extension->unsupportedOn(PHP_OS_FAMILY) !== null => '<fg=gray>'.$extension->unsupportedOn(PHP_OS_FAMILY).'</>',
                        default => '<fg=yellow;options=bold>NOT INSTALLED</> <fg=gray>install:ext '.$extension->extension().'</>',
                    },
                ),
            ])
            ->all());

        static::addToSection('Sketches', fn () => [
            'Refresh Rate' => (float) config('sketches.refresh_rate', 60).' Hz',
            'Mail Handler' => DeliverMailToSketches::HANDLER,
            'Paths' => static::format(
                (new Collection($this->venusian->make(SketchKernel::class)->sketchPaths()))
                    ->map(fn (string $path): string => Str::after(realpath($path) ?: $path, $this->venusian->basePath().'/'))
                    ->values()
                    ->all(),
                console: fn (array $paths): string => $paths === [] ? '<fg=yellow;options=bold>NONE</>' : implode(', ', $paths),
            ),
        ]);

        (new Collection(static::$customDataResolvers))->each->__invoke();
    }

    /**
     * Add additional data to the output of the "about" command.
     *
     * @param  callable|string|array<string, mixed>  $data  label => value pairs, a label (with $value), or a resolver returning label => value pairs
     */
    public static function add(string $section, callable|string|array $data, ?string $value = null): void
    {
        static::$customDataResolvers[] = fn () => static::addToSection($section, $data, $value);
    }

    /**
     * Add additional data to the output of the "about" command.
     *
     * @param  callable|string|array<string, mixed>  $data
     */
    protected static function addToSection(string $section, callable|string|array $data, ?string $value = null): void
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                self::$data[$section][] = [$key, $value];
            }
        } elseif (is_callable($data) || ($value === null && class_exists($data))) {
            self::$data[$section][] = $data;
        } else {
            self::$data[$section][] = [$data, $value];
        }
    }

    /**
     * Get the sections provided to the command.
     *
     * @return array<int, string>
     */
    protected function sections(): array
    {
        return (new Collection(explode(',', $this->option('only') ?? '')))
            ->filter()
            ->map(fn (string $only): string => $this->toSearchKeyword($only))
            ->all();
    }

    /**
     * Materialize a function that formats a given value for CLI or JSON output.
     *
     * @param  (\Closure(mixed):(mixed))|null  $console
     * @param  (\Closure(mixed):(mixed))|null  $json
     * @return \Closure(bool):mixed
     */
    public static function format(mixed $value, ?Closure $console = null, ?Closure $json = null): Closure
    {
        return function (bool $isJson) use ($value, $console, $json): mixed {
            if ($isJson === true && $json instanceof Closure) {
                return value($json, $value);
            } elseif ($isJson === false && $console instanceof Closure) {
                return value($console, $value);
            }

            return value($value);
        };
    }

    /**
     * A driver name that may be "auto": the console shows both, JSON the one in use.
     *
     * @param  Closure(): string  $auto
     */
    protected static function resolvedDriver(string $configured, Closure $auto): Closure
    {
        $resolved = $configured === 'auto' ? $auto() : $configured;

        return static::format(
            value: $resolved,
            console: fn (string $value): string => $configured === 'auto'
                ? '<fg=yellow;options=bold>auto</> <fg=gray;options=bold>/</> '.$value
                : $value,
        );
    }

    /**
     * A stack or failover name with what it fans out to: the console shows both, JSON the list.
     *
     * @param  array<int, string>|null  $secondary
     */
    protected static function withSecondary(?string $name, ?array $secondary): Closure|string|null
    {
        if ($secondary === null) {
            return $name;
        }

        return static::format(
            value: $name,
            console: fn (?string $value): string => '<fg=yellow;options=bold>'.$value.'</> <fg=gray;options=bold>/</> '.implode(', ', $secondary),
            json: fn (): array => $secondary,
        );
    }

    /**
     * A worker pool's max workers, or false when it is disabled.
     */
    protected static function poolSize(string $pool): int|false
    {
        return config("io-pools.pool_workers.{$pool}.enabled", false)
            ? (int) config("io-pools.pool_workers.{$pool}.max_workers", 4)
            : false;
    }

    /**
     * Format the given string for searching: "Event Loop", "event-loop" and "event_loop" all match.
     */
    protected function toSearchKeyword(string $value): string
    {
        return (new Stringable($value))->replace('-', ' ')->lower()->snake()->value();
    }

    /**
     * Flush the registered about data.
     */
    public static function flushState(): void
    {
        static::$data = [];

        static::$customDataResolvers = [];
    }
}
