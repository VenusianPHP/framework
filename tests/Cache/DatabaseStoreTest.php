<?php

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Voyager\Cache\Repository;
use Voyager\Cache\DatabaseStore;
use Voyager\Cache\Async\DatabaseOperation;
use Voyager\Cache\Async\DatabaseOperationKind;
use Voyager\Contracts\Console\Kernel;
use Voyager\Database\Schema\Blueprint;
use Venusian\Tests\Database\Fixtures\DatabaseApp;

dataset('cache connections', function () {
    foreach (['sqlite', 'mysql', 'pgsql'] as $connection) {
        if (DatabaseApp::reachable($connection)) {
            yield $connection => [$connection];
        }
    }
});

beforeEach(fn () => $this->app = DatabaseApp::boot());
afterEach(fn () => DatabaseApp::tearDown($this->app, $this));

/**
 * A database store on cache and lock tables of this test's own, dropped at tearDown.
 *
 * @param array<string, mixed> $config
 */
function databaseCache(object $test, string $connection, array $config = []): Repository
{
    $table = DatabaseApp::table($test->app, $connection, fn (Blueprint $table) => [
        $table->string('key')->primary(), $table->mediumText('value'), $table->integer('expiration')->index(),
    ]);
    $locks = DatabaseApp::table($test->app, $connection, fn (Blueprint $table) => [
        $table->string('key')->primary(), $table->string('owner'), $table->integer('expiration')->index(),
    ]);

    $test->app['config']->set('cache.stores.database-test', [
        'driver' => 'database', 'connection' => $connection, 'table' => $table, 'lock_table' => $locks, 'prefix' => 'test:', ...$config,
    ]);

    return $test->app['cache']->store('database-test');
}

/** The store's rows as the table holds them, keyed without the store's prefix. */
function cacheRows(Repository $cache): array
{
    $store = $cache->getStore();

    return $store->getConnection()->table($store->getTable())->orderBy('key')->get()
        ->map(fn ($row) => ['key' => substr(((array) $row)['key'], strlen($store->getPrefix())), ...array_diff_key((array) $row, ['key' => true])])
        ->all();
}

/** Serializes with a NUL byte: a private property's name carries one. */
final class CacheSecret
{
    public function __construct(private string $secret) {}

    public function secret(): string
    {
        return $this->secret;
    }
}

it('keeps values as rows, and drops one that expired when it is read', function (string $connection) {
    $cache = databaseCache($this, $connection);
    $store = $cache->getStore();

    $cache->put('greeting', ['hello' => 'world'], 60);
    $cache->putMany(['a' => 1, 'b' => 2], 60);
    $cache->forever('kept', 'always');
    $store->getConnection()->table($store->getTable())->insert(['key' => 'test:stale', 'value' => serialize('old'), 'expiration' => time() - 1]);

    expect($cache->get('greeting'))->toBe(['hello' => 'world'])
        ->and($cache->many(['a', 'b', 'missing']))->toBe(['a' => 1, 'b' => 2, 'missing' => null])
        ->and($cache->get('stale'))->toBeNull()
        ->and(array_column(cacheRows($cache), 'key'))->toBe(['a', 'b', 'greeting', 'kept'])
        ->and((int) collect(cacheRows($cache))->firstWhere('key', 'kept')['expiration'])->toBeGreaterThan(time() + DatabaseStore::FOREVER - 5)
        ->and($cache->forget('kept'))->toBeTrue()
        ->and($cache->forget('kept'))->toBeFalse();
})->with('cache connections');

it('stores a value whose serialized form has a NUL byte, base64 encoded where the column can\'t hold one', function (string $connection) {
    $cache = databaseCache($this, $connection);

    $cache->put('secret', new CacheSecret('shh'), 60);
    $raw = cacheRows($cache)[0]['value'];

    expect($cache->get('secret')->secret())->toBe('shh')
        ->and($raw)->toBe($connection === 'mysql' ? serialize(new CacheSecret('shh')) : base64_encode(serialize(new CacheSecret('shh'))));
})->with('cache connections');

it('adds only where nothing live is', function (string $connection) {
    $cache = databaseCache($this, $connection);
    $store = $cache->getStore();
    $store->getConnection()->table($store->getTable())->insert(['key' => 'test:stale', 'value' => serialize('old'), 'expiration' => time() - 1]);

    expect($cache->add('fresh', 'first', 60))->toBeTrue()
        ->and($cache->add('fresh', 'second', 60))->toBeFalse()
        ->and($cache->add('stale', 'replaced', 60))->toBeTrue()
        ->and($cache->get('fresh'))->toBe('first')
        ->and($cache->get('stale'))->toBe('replaced');
})->with('cache connections');

it('counts a missing or expired key from zero, a live number on from itself, and leaves a non-number alone', function (string $connection) {
    $cache = databaseCache($this, $connection);
    $store = $cache->getStore();
    $store->getConnection()->table($store->getTable())->insert(['key' => 'test:lapsed', 'value' => serialize(40), 'expiration' => time() - 1]);
    $cache->put('text', 'words', 60);
    $cache->put('timed', 10, 60);

    expect($cache->increment('hits'))->toBe(1)
        ->and($cache->increment('hits', 4))->toBe(5)
        ->and($cache->decrement('hits', 2))->toBe(3)
        ->and($cache->decrement('below', 2))->toBe(-2)
        ->and($cache->increment('lapsed', 7))->toBe(7)
        ->and($cache->increment('text'))->toBeFalse()
        ->and($cache->get('text'))->toBe('words')
        ->and($cache->increment('timed'))->toBe(11)
        ->and((int) collect(cacheRows($cache))->firstWhere('key', 'timed')['expiration'])->toBeLessThanOrEqual(time() + 60)
        ->and((int) collect(cacheRows($cache))->firstWhere('key', 'hits')['expiration'])->toBeGreaterThan(time() + DatabaseStore::FOREVER - 5);
})->with('cache connections');

it('counts on from the row another caller wrote when it loses the insert', function () {
    $cache = databaseCache($this, 'sqlite');
    $store = $cache->getStore();

    // the other caller's insert lands between this one's read and its own insert
    $racing = new class($store->getConnection(), $store->getTable(), $store->getPrefix()) extends DatabaseStore
    {
        protected function insertIfAbsent(array $row): bool
        {
            $this->table()->insert([...$row, 'value' => $this->encode(10)]);

            return false;
        }
    };

    expect($racing->increment('contested', 5))->toBe(15)
        ->and($cache->get('contested'))->toBe(15);
});

it('locks: one owner at a time, an expired lock taken over, refreshed and released by its owner', function (string $connection) {
    $cache = databaseCache($this, $connection, ['lock_lottery' => [0, 100]]);
    $store = $cache->getStore();

    $first = $cache->lock('job', 10);
    $second = $cache->lock('job', 10);
    $acquired = $first->acquire();
    $blocked = $second->acquire();
    $restored = $cache->restoreLock('job', $first->owner());
    $refreshed = $restored->refresh(120);
    $expiration = (int) $store->getConnection()->table($store->getLockTable())->value('expiration');
    $foreign = $second->release();
    $released = $restored->release();

    $store->getConnection()->table($store->getLockTable())->insert(['key' => 'test:abandoned', 'owner' => 'crashed', 'expiration' => time() - 1]);

    expect($acquired)->toBeTrue()
        ->and($blocked)->toBeFalse()
        ->and($refreshed)->toBeTrue()
        ->and($expiration)->toBeGreaterThan(time() + 100)
        ->and($foreign)->toBeFalse()
        ->and($released)->toBeTrue()
        ->and($cache->lock('abandoned', 10)->acquire())->toBeTrue()
        ->and($cache->lock('guarded', 10)->get(fn () => 'ran'))->toBe('ran')
        ->and($cache->lock('guarded', 10)->acquire())->toBeTrue();

    $cache->lock('guarded')->forceRelease();

    expect($store->getConnection()->table($store->getLockTable())->where('key', 'test:guarded')->exists())->toBeFalse();
})->with('cache connections');

it('unserializes only the classes the store allows', function () {
    $cache = databaseCache($this, 'sqlite');
    $this->app['config']->set('cache.serializable_classes', false);
    $this->app['config']->set('cache.stores.database-guarded', $this->app['config']->get('cache.stores.database-test'));

    $cache->put('secret', new CacheSecret('shh'), 60);

    expect($this->app['cache']->store('database-guarded')->get('secret'))->toBeInstanceOf(__PHP_Incomplete_Class::class);
});

it('offloads the store\'s operations to a worker, in order with the blocking calls on its connection', function (string $connection) {
    $cache = databaseCache($this, $connection);
    $async = $cache->async();

    $async->put('handed-over', ['from' => 'a worker'], 60);
    $counts = array_map(fn () => $async->increment('tally'), range(1, 10));

    expect($cache->get('handed-over'))->toBe(['from' => 'a worker'])
        ->and($async->get('tally')->wait())->toBe(10)
        ->and(array_map(fn ($count) => $count->wait(), $counts))->toBe(range(1, 10))
        ->and($this->app->get('process-workers')->workerCount())->toBeGreaterThan(0);
})->with('cache connections');

it('counts the TTL from the call: a put whose time ran out while it waited stores nothing', function () {
    $cache = databaseCache($this, 'sqlite');
    $store = $cache->getStore();
    $connection = $store->getConnection();

    $stored = new DatabaseOperation(
        'sqlite', $connection->getConfig(), $store->getTable(), '', DatabaseOperationKind::PUT, 'late', $store->encode('too late'), time() - 1,
    )->handle();

    expect($stored)->toBeTrue()
        ->and($cache->get('late'))->toBeNull();
});

it('refuses to offload while its connection has a transaction open', function () {
    $cache = databaseCache($this, 'sqlite');
    $connection = $cache->getStore()->getConnection();

    $connection->beginTransaction();
    $put = $cache->async()->put('inside', 'a transaction', 60);
    $connection->rollBack();

    expect(fn () => $put->wait())->toThrow(LogicException::class, 'has a transaction open');
});

it('writes the cache tables\' migration with cache:table, once', function () {
    $directory = sys_get_temp_dir().'/venusian-cache-table-'.bin2hex(random_bytes(4));
    $this->app->useDatabasePath($directory);
    $kernel = $this->app->make(Kernel::class);

    try {
        $first = $kernel->handle(new ArrayInput(['command' => 'cache:table']), new BufferedOutput);
        $again = $kernel->handle(new ArrayInput(['command' => 'make:cache-table']), $output = new BufferedOutput);
        $written = glob($directory.'/migrations/*_create_cache_table.php');

        expect($first)->toBe(0)
            ->and($again)->toBe(1)
            ->and($output->fetch())->toContain('Migration already exists.')
            ->and($written)->toHaveCount(1)
            ->and(file_get_contents($written[0]))->toContain("create('cache',")->toContain("create('cache_locks',");
    } finally {
        $this->app['files']->deleteDirectory($directory);
    }
});
