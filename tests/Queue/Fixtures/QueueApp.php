<?php

namespace Venusian\Tests\Queue\Fixtures;

use PHPUnit\Framework\TestCase;
use Voyager\Core\RenderedInstance;
use Venusian\Tests\Database\Fixtures\DatabaseApp;

/**
 * The app with its queue tables on a fresh SQLite file (made by the queue:*-table stubs), its
 * redis connections on database 15 under a prefix of this run's own, and a journal file jobs
 * write to, so a test can see what ran and where.
 */
final class QueueApp
{
    public const int REDIS_DATABASE = 15;

    private static ?string $journal = null;

    public static function boot(): RenderedInstance
    {
        $app = DatabaseApp::boot();
        $stubs = dirname(__DIR__, 3).'/src/Voyager/Queue/Console/stubs';

        foreach (['jobs.stub' => 'jobs', 'failed_jobs.stub' => 'failed_jobs', 'batches.stub' => 'job_batches'] as $stub => $table) {
            self::migrate($stubs.'/'.$stub, $table);
        }

        // queue:work reads its restart signal from the default store; keep it in memory
        $app['config']->set('cache.default', 'array');
        $app['config']->set('queue.connections.database.connection', 'sqlite');
        $app['config']->set('queue.batching.database', 'sqlite');
        $app['config']->set('queue.failed', ['driver' => 'database-uuids', 'database' => 'sqlite', 'table' => 'failed_jobs']);
        $app['config']->set('database.redis.default.database', self::REDIS_DATABASE);
        $app['config']->set('database.redis.options.prefix', 'venusian_test_'.bin2hex(random_bytes(4)).':');

        self::$journal = sys_get_temp_dir().'/venusian-queue-'.bin2hex(random_bytes(6)).'.journal';

        return $app;
    }

    /** Runs a migration stub, its table filled in, on the default SQLite file. */
    public static function migrate(string $stub, string $table): void
    {
        $path = sys_get_temp_dir().'/venusian-migration-'.bin2hex(random_bytes(6)).'.php';
        file_put_contents($path, str_replace('{{table}}', $table, file_get_contents($stub)));

        try {
            (require $path)->up();
        } finally {
            unlink($path);
        }
    }

    /** Runs a computer command in-process; its exit code and what it wrote. */
    public static function run(RenderedInstance $app, array $input): array
    {
        $output = new \Symfony\Component\Console\Output\BufferedOutput;

        $code = $app->make(\Voyager\Contracts\Console\Kernel::class)->handle(new \Symfony\Component\Console\Input\ArrayInput($input), $output);

        return [$code, $output->fetch()];
    }

    public static function journal(): string
    {
        return (string) self::$journal;
    }

    /** @return list<string> the lines jobs wrote, in the order they wrote them */
    public static function lines(): array
    {
        return is_file((string) self::$journal) ? file(self::$journal, FILE_IGNORE_NEW_LINES) : [];
    }

    public static function redisReachable(): bool
    {
        $socket = @fsockopen('127.0.0.1', 6379, $errno, $errstr, 0.5);

        if ($socket === false) {
            return false;
        }

        fclose($socket);

        return true;
    }

    public static function tearDown(RenderedInstance $app, TestCase $test): void
    {
        if (self::redisReachable() && $app->isBound('redis')) {
            $connection = $app->get('redis')->connection();
            $prefix = $app['config']->get('database.redis.options.prefix');

            foreach ($connection->client()->keys('*') as $key) {
                $connection->client()->del(substr($key, strlen($prefix)));
            }
        }

        if (self::$journal && is_file(self::$journal)) {
            unlink(self::$journal);
        }

        self::$journal = null;
        DatabaseApp::tearDown($app, $test);
    }
}
