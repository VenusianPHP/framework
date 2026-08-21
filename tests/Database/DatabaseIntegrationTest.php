<?php

namespace Tests\Database;

use Voyager\Database\Capsule\Manager as DB;
use Voyager\Database\Events\QueryExecuted;
use Voyager\Events\Dispatcher;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class DatabaseIntegrationTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected function setUp(): void
    {
        $db = new DB;
        $db->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
        $db->setAsGlobal();
        $db->setEventDispatcher(new Dispatcher);
    }

    public function testQueryExecutedToRawSql(): void
    {
        $connection = DB::connection();

        $connection->listen(function (QueryExecuted $query) use (&$queryExecuted): void {
            $queryExecuted = $query;
        });

        $connection->select('select ?', [true]);

        $this->assertInstanceOf(QueryExecuted::class, $queryExecuted);
        $this->assertSame('select ?', $queryExecuted->sql);
        $this->assertSame([true], $queryExecuted->bindings);
        $this->assertSame('select 1', $queryExecuted->toRawSql());
    }
}
