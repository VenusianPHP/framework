<?php

use Carbon\Carbon;
use Voyager\Contracts\Database\Query\ConditionExpression;
use Voyager\Database\Connection;
use Voyager\Database\Instrument\Builder as InstrumentBuilder;
use Voyager\Database\Query\Builder;
use Voyager\Database\Query\Expression as Raw;
use Voyager\Database\Query\Grammars\Grammar;
use Voyager\Database\Query\Grammars\MariaDbGrammar;
use Voyager\Database\Query\Grammars\MySqlGrammar;
use Voyager\Database\Query\Grammars\PostgresGrammar;
use Voyager\Database\Query\Grammars\SQLiteGrammar;
use Voyager\Database\Query\Grammars\SqlServerGrammar;
use Voyager\Database\Query\JoinClause;
use Voyager\Database\Query\Processors\MySqlProcessor;
use Voyager\Database\Query\Processors\PostgresProcessor;
use Voyager\Database\Query\Processors\Processor;
use Voyager\Database\RecordNotFoundException;
use Voyager\Pagination\AbstractPaginator as Paginator;
use Voyager\Pagination\Cursor;
use Voyager\Pagination\CursorPaginator;
use Voyager\Pagination\LengthAwarePaginator;
use Voyager\NutsAndBolts\DataObjects\Str;
use Tests\Database\Fixtures\Enums\Bar;
use Tests\Database\IntegerStatus;
use Tests\Database\NonBackedStatus;
use Mockery as m;
include_once 'Enums.php';

test('basic select', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users');
    $this->assertSame('select * from "users"', $builder->toSql());
});

test('basic select with get columns', function () {
    $builder = queryBuilderGetBuilder();
    $builder->getProcessor()->shouldReceive('processSelect');
    $builder->getConnection()->shouldReceive('select')->once()->andReturnUsing(function ($sql) {
        $this->assertSame('select * from "users"', $sql);
    });
    $builder->getConnection()->shouldReceive('select')->once()->andReturnUsing(function ($sql) {
        $this->assertSame('select "foo", "bar" from "users"', $sql);
    });
    $builder->getConnection()->shouldReceive('select')->once()->andReturnUsing(function ($sql) {
        $this->assertSame('select "baz" from "users"', $sql);
    });

    $builder->from('users')->get();
    $this->assertNull($builder->columns);

    $builder->from('users')->get(['foo', 'bar']);
    $this->assertNull($builder->columns);

    $builder->from('users')->get('baz');
    $this->assertNull($builder->columns);

    $this->assertSame('select * from "users"', $builder->toSql());
    $this->assertNull($builder->columns);
});

test('basic select use write pdo', function () {
    $builder = queryBuilderGetMySqlBuilderWithProcessor();
    $builder->getConnection()->shouldReceive('select')->once()
        ->with('select * from `users`', [], false);
    $builder->useWritePdo()->select('*')->from('users')->get();

    $builder = queryBuilderGetMySqlBuilderWithProcessor();
    $builder->getConnection()->shouldReceive('select')->once()
        ->with('select * from `users`', [], true);
    $builder->select('*')->from('users')->get();
});

test('basic table wrapping protects quotation marks', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('some"table');
    $this->assertSame('select * from "some""table"', $builder->toSql());
});

test('alias wrapping as whole constant', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('x.y as foo.bar')->from('baz');
    $this->assertSame('select "x"."y" as "foo.bar" from "baz"', $builder->toSql());
});

test('alias wrapping with spaces in database name', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('w x.y.z as foo.bar')->from('baz');
    $this->assertSame('select "w x"."y"."z" as "foo.bar" from "baz"', $builder->toSql());
});

test('adding selects', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('foo')->addSelect('bar')->addSelect(['baz', 'boom'])->addSelect('bar')->from('users');
    $this->assertSame('select "foo", "bar", "baz", "boom" from "users"', $builder->toSql());
});

test('basic select with prefix', function () {
    $builder = queryBuilderGetBuilder(prefix: 'prefix_');
    $builder->select('*')->from('users');
    $this->assertSame('select * from "prefix_users"', $builder->toSql());
});

test('basic select distinct', function () {
    $builder = queryBuilderGetBuilder();
    $builder->distinct()->select('foo', 'bar')->from('users');
    $this->assertSame('select distinct "foo", "bar" from "users"', $builder->toSql());
});

test('basic select distinct on columns', function () {
    $builder = queryBuilderGetBuilder();
    $builder->distinct('foo')->select('foo', 'bar')->from('users');
    $this->assertSame('select distinct "foo", "bar" from "users"', $builder->toSql());

    $builder = queryBuilderGetPostgresBuilder();
    $builder->distinct('foo')->select('foo', 'bar')->from('users');
    $this->assertSame('select distinct on ("foo") "foo", "bar" from "users"', $builder->toSql());
});

test('basic alias', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('foo as bar')->from('users');
    $this->assertSame('select "foo" as "bar" from "users"', $builder->toSql());
});

test('alias with prefix', function () {
    $builder = queryBuilderGetBuilder(prefix: 'prefix_');
    $builder->select('*')->from('users as people');
    $this->assertSame('select * from "prefix_users" as "prefix_people"', $builder->toSql());
});

test('join aliases with prefix', function () {
    $builder = queryBuilderGetBuilder(prefix: 'prefix_');
    $builder->select('*')->from('services')->join('translations AS t', 't.item_id', '=', 'services.id');
    $this->assertSame('select * from "prefix_services" inner join "prefix_translations" as "prefix_t" on "prefix_t"."item_id" = "prefix_services"."id"', $builder->toSql());
});

test('basic table wrapping', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('public.users');
    $this->assertSame('select * from "public"."users"', $builder->toSql());
});

test('when callback', function () {
    $callback = function ($query, $condition) {
        $this->assertTrue($condition);

        $query->where('id', '=', 1);
    };

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->when(true, $callback)->where('email', 'foo');
    $this->assertSame('select * from "users" where "id" = ? and "email" = ?', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->when(false, $callback)->where('email', 'foo');
    $this->assertSame('select * from "users" where "email" = ?', $builder->toSql());
});

test('when callback with return', function () {
    $callback = function ($query, $condition) {
        $this->assertTrue($condition);

        return $query->where('id', '=', 1);
    };

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->when(true, $callback)->where('email', 'foo');
    $this->assertSame('select * from "users" where "id" = ? and "email" = ?', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->when(false, $callback)->where('email', 'foo');
    $this->assertSame('select * from "users" where "email" = ?', $builder->toSql());
});

test('when callback with default', function () {
    $callback = function ($query, $condition) {
        $this->assertSame('truthy', $condition);

        $query->where('id', '=', 1);
    };

    $default = function ($query, $condition) {
        $this->assertEquals(0, $condition);

        $query->where('id', '=', 2);
    };

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->when('truthy', $callback, $default)->where('email', 'foo');
    $this->assertSame('select * from "users" where "id" = ? and "email" = ?', $builder->toSql());
    $this->assertEquals([0 => 1, 1 => 'foo'], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->when(0, $callback, $default)->where('email', 'foo');
    $this->assertSame('select * from "users" where "id" = ? and "email" = ?', $builder->toSql());
    $this->assertEquals([0 => 2, 1 => 'foo'], $builder->getBindings());
});

test('unless callback', function () {
    $callback = function ($query, $condition) {
        $this->assertFalse($condition);

        $query->where('id', '=', 1);
    };

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->unless(false, $callback)->where('email', 'foo');
    $this->assertSame('select * from "users" where "id" = ? and "email" = ?', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->unless(true, $callback)->where('email', 'foo');
    $this->assertSame('select * from "users" where "email" = ?', $builder->toSql());
});

test('unless callback with return', function () {
    $callback = function ($query, $condition) {
        $this->assertFalse($condition);

        return $query->where('id', '=', 1);
    };

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->unless(false, $callback)->where('email', 'foo');
    $this->assertSame('select * from "users" where "id" = ? and "email" = ?', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->unless(true, $callback)->where('email', 'foo');
    $this->assertSame('select * from "users" where "email" = ?', $builder->toSql());
});

test('unless callback with default', function () {
    $callback = function ($query, $condition) {
        $this->assertEquals(0, $condition);

        $query->where('id', '=', 1);
    };

    $default = function ($query, $condition) {
        $this->assertSame('truthy', $condition);

        $query->where('id', '=', 2);
    };

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->unless(0, $callback, $default)->where('email', 'foo');
    $this->assertSame('select * from "users" where "id" = ? and "email" = ?', $builder->toSql());
    $this->assertEquals([0 => 1, 1 => 'foo'], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->unless('truthy', $callback, $default)->where('email', 'foo');
    $this->assertSame('select * from "users" where "id" = ? and "email" = ?', $builder->toSql());
    $this->assertEquals([0 => 2, 1 => 'foo'], $builder->getBindings());
});

test('tap callback', function () {
    $callback = function ($query) {
        return $query->where('id', '=', 1);
    };

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->tap($callback)->where('email', 'foo');
    $this->assertSame('select * from "users" where "id" = ? and "email" = ?', $builder->toSql());
});

test('pipe callback', function () {
    $query = queryBuilderGetBuilder();

    $result = $query->pipe(fn (Builder $query) => 5);
    $this->assertSame(5, $result);

    $result = $query->pipe(fn (Builder $query) => null);
    $this->assertSame($query, $result);

    $result = $query->pipe(function (Builder $query) {
        //
    });
    $this->assertSame($query, $result);

    $this->assertCount(0, $query->wheres);
    $result = $query->pipe(fn (Builder $query) => $query->where('foo', 'bar'));
    $this->assertSame($query, $result);
    $this->assertCount(1, $query->wheres);
});

test('basic wheres', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('id', '=', 1);
    $this->assertSame('select * from "users" where "id" = ?', $builder->toSql());
    $this->assertEquals([0 => 1], $builder->getBindings());
});

test('basic where not', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereNot('name', 'foo')->whereNot('name', '<>', 'bar');
    $this->assertSame('select * from "users" where not "name" = ? and not "name" <> ?', $builder->toSql());
    $this->assertEquals(['foo', 'bar'], $builder->getBindings());
});

test('wheres with array value', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('id', [12]);
    $this->assertSame('select * from "users" where "id" = ?', $builder->toSql());
    $this->assertEquals([0 => 12], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('id', '=', [12, 30]);
    $this->assertSame('select * from "users" where "id" = ?', $builder->toSql());
    $this->assertEquals([0 => 12], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('id', '!=', [12, 30]);
    $this->assertSame('select * from "users" where "id" != ?', $builder->toSql());
    $this->assertEquals([0 => 12], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('id', '<>', [12, 30]);
    $this->assertSame('select * from "users" where "id" <> ?', $builder->toSql());
    $this->assertEquals([0 => 12], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('id', '=', [[12, 30]]);
    $this->assertSame('select * from "users" where "id" = ?', $builder->toSql());
    $this->assertEquals([0 => 12], $builder->getBindings());
});

test('my sql wrapping protects quotation marks', function () {
    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->From('some`table');
    $this->assertSame('select * from `some``table`', $builder->toSql());
});

test('date based wheres accepts two arguments', function () {
    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users')->whereDate('created_at', 1);
    $this->assertSame('select * from `users` where date(`created_at`) = ?', $builder->toSql());

    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users')->whereDay('created_at', 1);
    $this->assertSame('select * from `users` where day(`created_at`) = ?', $builder->toSql());

    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users')->whereMonth('created_at', 1);
    $this->assertSame('select * from `users` where month(`created_at`) = ?', $builder->toSql());

    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users')->whereYear('created_at', 1);
    $this->assertSame('select * from `users` where year(`created_at`) = ?', $builder->toSql());
});

test('date based or wheres accepts two arguments', function () {
    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users')->where('id', 1)->orWhereDate('created_at', 1);
    $this->assertSame('select * from `users` where `id` = ? or date(`created_at`) = ?', $builder->toSql());

    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users')->where('id', 1)->orWhereDay('created_at', 1);
    $this->assertSame('select * from `users` where `id` = ? or day(`created_at`) = ?', $builder->toSql());

    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users')->where('id', 1)->orWhereMonth('created_at', 1);
    $this->assertSame('select * from `users` where `id` = ? or month(`created_at`) = ?', $builder->toSql());

    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users')->where('id', 1)->orWhereYear('created_at', 1);
    $this->assertSame('select * from `users` where `id` = ? or year(`created_at`) = ?', $builder->toSql());
});

test('date based wheres expression is not bound', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereDate('created_at', new Raw('NOW()'))->where('admin', true);
    $this->assertEquals([true], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereDay('created_at', new Raw('NOW()'));
    $this->assertEquals([], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereMonth('created_at', new Raw('NOW()'));
    $this->assertEquals([], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereYear('created_at', new Raw('NOW()'));
    $this->assertEquals([], $builder->getBindings());
});

test('where date my sql', function () {
    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users')->whereDate('created_at', '=', '2015-12-21');
    $this->assertSame('select * from `users` where date(`created_at`) = ?', $builder->toSql());
    $this->assertEquals([0 => '2015-12-21'], $builder->getBindings());

    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users')->whereDate('created_at', '=', new Raw('NOW()'));
    $this->assertSame('select * from `users` where date(`created_at`) = NOW()', $builder->toSql());
});

test('where day my sql', function () {
    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users')->whereDay('created_at', '=', 1);
    $this->assertSame('select * from `users` where day(`created_at`) = ?', $builder->toSql());
    $this->assertEquals([0 => 1], $builder->getBindings());
});

test('or where day my sql', function () {
    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users')->whereDay('created_at', '=', 1)->orWhereDay('created_at', '=', 2);
    $this->assertSame('select * from `users` where day(`created_at`) = ? or day(`created_at`) = ?', $builder->toSql());
    $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());
});

test('or where day postgres', function () {
    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('*')->from('users')->whereDay('created_at', '=', 1)->orWhereDay('created_at', '=', 2);
    $this->assertSame('select * from "users" where extract(day from "created_at") = ? or extract(day from "created_at") = ?', $builder->toSql());
    $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());
});

test('or where day sql server', function () {
    $builder = queryBuilderGetSqlServerBuilder();
    $builder->select('*')->from('users')->whereDay('created_at', '=', 1)->orWhereDay('created_at', '=', 2);
    $this->assertSame('select * from [users] where day([created_at]) = ? or day([created_at]) = ?', $builder->toSql());
    $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());
});

test('where month my sql', function () {
    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users')->whereMonth('created_at', '=', 5);
    $this->assertSame('select * from `users` where month(`created_at`) = ?', $builder->toSql());
    $this->assertEquals([0 => 5], $builder->getBindings());
});

test('or where month my sql', function () {
    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users')->whereMonth('created_at', '=', 5)->orWhereMonth('created_at', '=', 6);
    $this->assertSame('select * from `users` where month(`created_at`) = ? or month(`created_at`) = ?', $builder->toSql());
    $this->assertEquals([0 => 5, 1 => 6], $builder->getBindings());
});

test('or where month postgres', function () {
    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('*')->from('users')->whereMonth('created_at', '=', 5)->orWhereMonth('created_at', '=', 6);
    $this->assertSame('select * from "users" where extract(month from "created_at") = ? or extract(month from "created_at") = ?', $builder->toSql());
    $this->assertEquals([0 => 5, 1 => 6], $builder->getBindings());
});

test('or where month sql server', function () {
    $builder = queryBuilderGetSqlServerBuilder();
    $builder->select('*')->from('users')->whereMonth('created_at', '=', 5)->orWhereMonth('created_at', '=', 6);
    $this->assertSame('select * from [users] where month([created_at]) = ? or month([created_at]) = ?', $builder->toSql());
    $this->assertEquals([0 => 5, 1 => 6], $builder->getBindings());
});

test('where year my sql', function () {
    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users')->whereYear('created_at', '=', 2014);
    $this->assertSame('select * from `users` where year(`created_at`) = ?', $builder->toSql());
    $this->assertEquals([0 => 2014], $builder->getBindings());
});

test('or where year my sql', function () {
    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users')->whereYear('created_at', '=', 2014)->orWhereYear('created_at', '=', 2015);
    $this->assertSame('select * from `users` where year(`created_at`) = ? or year(`created_at`) = ?', $builder->toSql());
    $this->assertEquals([0 => 2014, 1 => 2015], $builder->getBindings());
});

test('or where year postgres', function () {
    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('*')->from('users')->whereYear('created_at', '=', 2014)->orWhereYear('created_at', '=', 2015);
    $this->assertSame('select * from "users" where extract(year from "created_at") = ? or extract(year from "created_at") = ?', $builder->toSql());
    $this->assertEquals([0 => 2014, 1 => 2015], $builder->getBindings());
});

test('or where year sql server', function () {
    $builder = queryBuilderGetSqlServerBuilder();
    $builder->select('*')->from('users')->whereYear('created_at', '=', 2014)->orWhereYear('created_at', '=', 2015);
    $this->assertSame('select * from [users] where year([created_at]) = ? or year([created_at]) = ?', $builder->toSql());
    $this->assertEquals([0 => 2014, 1 => 2015], $builder->getBindings());
});

test('where time my sql', function () {
    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users')->whereTime('created_at', '>=', '22:00');
    $this->assertSame('select * from `users` where time(`created_at`) >= ?', $builder->toSql());
    $this->assertEquals([0 => '22:00'], $builder->getBindings());
});

test('where time operator optional my sql', function () {
    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users')->whereTime('created_at', '22:00');
    $this->assertSame('select * from `users` where time(`created_at`) = ?', $builder->toSql());
    $this->assertEquals([0 => '22:00'], $builder->getBindings());
});

test('where time operator optional postgres', function () {
    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('*')->from('users')->whereTime('created_at', '22:00');
    $this->assertSame('select * from "users" where "created_at"::time = ?', $builder->toSql());
    $this->assertEquals([0 => '22:00'], $builder->getBindings());
});

test('where time sql server', function () {
    $builder = queryBuilderGetSqlServerBuilder();
    $builder->select('*')->from('users')->whereTime('created_at', '22:00');
    $this->assertSame('select * from [users] where cast([created_at] as time) = ?', $builder->toSql());
    $this->assertEquals([0 => '22:00'], $builder->getBindings());

    $builder = queryBuilderGetSqlServerBuilder();
    $builder->select('*')->from('users')->whereTime('created_at', new Raw('NOW()'));
    $this->assertSame('select * from [users] where cast([created_at] as time) = NOW()', $builder->toSql());
    $this->assertEquals([], $builder->getBindings());
});

test('or where time my sql', function () {
    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users')->whereTime('created_at', '<=', '10:00')->orWhereTime('created_at', '>=', '22:00');
    $this->assertSame('select * from `users` where time(`created_at`) <= ? or time(`created_at`) >= ?', $builder->toSql());
    $this->assertEquals([0 => '10:00', 1 => '22:00'], $builder->getBindings());
});

test('or where time postgres', function () {
    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('*')->from('users')->whereTime('created_at', '<=', '10:00')->orWhereTime('created_at', '>=', '22:00');
    $this->assertSame('select * from "users" where "created_at"::time <= ? or "created_at"::time >= ?', $builder->toSql());
    $this->assertEquals([0 => '10:00', 1 => '22:00'], $builder->getBindings());
});

test('or where time sql server', function () {
    $builder = queryBuilderGetSqlServerBuilder();
    $builder->select('*')->from('users')->whereTime('created_at', '<=', '10:00')->orWhereTime('created_at', '>=', '22:00');
    $this->assertSame('select * from [users] where cast([created_at] as time) <= ? or cast([created_at] as time) >= ?', $builder->toSql());
    $this->assertEquals([0 => '10:00', 1 => '22:00'], $builder->getBindings());

    $builder = queryBuilderGetSqlServerBuilder();
    $builder->select('*')->from('users')->whereTime('created_at', '<=', '10:00')->orWhereTime('created_at', new Raw('NOW()'));
    $this->assertSame('select * from [users] where cast([created_at] as time) <= ? or cast([created_at] as time) = NOW()', $builder->toSql());
    $this->assertEquals([0 => '10:00'], $builder->getBindings());
});

test('where date postgres', function () {
    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('*')->from('users')->whereDate('created_at', '=', '2015-12-21');
    $this->assertSame('select * from "users" where "created_at"::date = ?', $builder->toSql());
    $this->assertEquals([0 => '2015-12-21'], $builder->getBindings());

    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('*')->from('users')->whereDate('created_at', new Raw('NOW()'));
    $this->assertSame('select * from "users" where "created_at"::date = NOW()', $builder->toSql());

    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('*')->from('users')->whereDate('result->created_at', new Raw('NOW()'));
    $this->assertSame('select * from "users" where ("result"->>\'created_at\')::date = NOW()', $builder->toSql());

    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('*')->from('users')->whereDate(new Raw('COALESCE(created_at, updated_at)'), new Raw('NOW()'));
    $this->assertSame('select * from "users" where COALESCE(created_at, updated_at)::date = NOW()', $builder->toSql());

    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('*')->from('users')->whereDate(Str::of('result->created_at'), new Raw('NOW()'));
    $this->assertSame('select * from "users" where ("result"->>\'created_at\')::date = NOW()', $builder->toSql());
});

test('where day postgres', function () {
    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('*')->from('users')->whereDay('created_at', '=', 1);
    $this->assertSame('select * from "users" where extract(day from "created_at") = ?', $builder->toSql());
    $this->assertEquals([0 => 1], $builder->getBindings());
});

test('where month postgres', function () {
    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('*')->from('users')->whereMonth('created_at', '=', 5);
    $this->assertSame('select * from "users" where extract(month from "created_at") = ?', $builder->toSql());
    $this->assertEquals([0 => 5], $builder->getBindings());
});

test('where year postgres', function () {
    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('*')->from('users')->whereYear('created_at', '=', 2014);
    $this->assertSame('select * from "users" where extract(year from "created_at") = ?', $builder->toSql());
    $this->assertEquals([0 => 2014], $builder->getBindings());
});

test('where time postgres', function () {
    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('*')->from('users')->whereTime('created_at', '>=', '22:00');
    $this->assertSame('select * from "users" where "created_at"::time >= ?', $builder->toSql());
    $this->assertEquals([0 => '22:00'], $builder->getBindings());

    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('*')->from('users')->whereTime('result->created_at', '>=', '22:00');
    $this->assertSame('select * from "users" where ("result"->>\'created_at\')::time >= ?', $builder->toSql());
    $this->assertEquals([0 => '22:00'], $builder->getBindings());

    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('*')->from('users')->whereTime(new Raw('COALESCE(created_at, updated_at)'), '>=', '22:00');
    $this->assertSame('select * from "users" where COALESCE(created_at, updated_at)::time >= ?', $builder->toSql());
    $this->assertEquals([0 => '22:00'], $builder->getBindings());

    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('*')->from('users')->whereTime(Str::of('result->created_at'), '>=', '22:00');
    $this->assertSame('select * from "users" where ("result"->>\'created_at\')::time >= ?', $builder->toSql());
    $this->assertEquals([0 => '22:00'], $builder->getBindings());
});

test('where past', function () {
    Carbon::setTestNow('2022-04-20 23:45:06.123456');

    $testDate = Carbon::create('2022-04-20 23:45:06.123456');

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('posts')->wherePast('published_at');
    $this->assertSame('select * from "posts" where "published_at" < ?', $builder->toSql());
    $this->assertEquals([0 => $testDate], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('posts')->where('id', '=', 1)->orWherePast('published_at');
    $this->assertSame('select * from "posts" where "id" = ? or "published_at" < ?', $builder->toSql());
    $this->assertEquals([0 => 1, 1 => $testDate], $builder->getBindings());
});

test('where past uses array', function () {
    Carbon::setTestNow('2022-04-20 12:34:56.123456');

    $testDate = Carbon::create('2022-04-20 12:34:56.123456');

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('posts')->wherePast(['published_at', 'held_at']);
    $this->assertSame('select * from "posts" where "published_at" < ? and "held_at" < ?', $builder->toSql());
    $this->assertEquals([0 => $testDate, 1 => $testDate], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('posts')->where('id', '=', 1)->orWherePast(['published_at', 'held_at']);
    $this->assertSame('select * from "posts" where "id" = ? or "published_at" < ? or "held_at" < ?', $builder->toSql());
    $this->assertEquals([0 => 1, 1 => $testDate, 2 => $testDate], $builder->getBindings());
});

test('where today my s q l', function () {
    Carbon::setTestNow('2022-04-20 12:34:56.123456');

    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('posts')->whereToday('published_at');
    $this->assertSame('select * from `posts` where date(`published_at`) = ?', $builder->toSql());
    $this->assertEquals([0 => '2022-04-20'], $builder->getBindings());

    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('posts')->where('id', '=', 1)->orWhereToday('published_at');
    $this->assertSame('select * from `posts` where `id` = ? or date(`published_at`) = ?', $builder->toSql());
    $this->assertEquals([0 => 1, 1 => '2022-04-20'], $builder->getBindings());
});

test('passing array to where today my s q l', function () {
    Carbon::setTestNow('2022-04-20 12:34:56.123456');

    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('posts')->whereToday(['published_at', 'held_at']);
    $this->assertSame('select * from `posts` where date(`published_at`) = ? and date(`held_at`) = ?', $builder->toSql());
    $this->assertEquals([0 => '2022-04-20', 1 => '2022-04-20'], $builder->getBindings());

    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('posts')->where('id', '=', 1)->orWhereToday(['published_at', 'held_at']);
    $this->assertSame('select * from `posts` where `id` = ? or date(`published_at`) = ? or date(`held_at`) = ?', $builder->toSql());
    $this->assertEquals([0 => 1, 1 => '2022-04-20', 2 => '2022-04-20'], $builder->getBindings());
});

test('where today sql server', function () {
    Carbon::setTestNow('2022-04-20 12:34:56.123456');

    $builder = queryBuilderGetSqlServerBuilder();
    $builder->select('*')->from('posts')->whereToday('published_at');
    $this->assertSame('select * from [posts] where cast([published_at] as date) = ?', $builder->toSql());
    $this->assertEquals([0 => '2022-04-20'], $builder->getBindings());

    $builder = queryBuilderGetSqlServerBuilder();
    $builder->select('*')->from('posts')->where('id', '=', 1)->orWhereToday('published_at');
    $this->assertSame('select * from [posts] where [id] = ? or cast([published_at] as date) = ?', $builder->toSql());
    $this->assertEquals([0 => 1, 1 => '2022-04-20'], $builder->getBindings());
});

test('passing array to where today sql server', function () {
    Carbon::setTestNow('2022-04-20 12:34:56.123456');

    $builder = queryBuilderGetSqlServerBuilder();
    $builder->select('*')->from('posts')->whereToday(['published_at', 'held_at']);
    $this->assertSame('select * from [posts] where cast([published_at] as date) = ? and cast([held_at] as date) = ?', $builder->toSql());
    $this->assertEquals([0 => '2022-04-20', 1 => '2022-04-20'], $builder->getBindings());

    $builder = queryBuilderGetSqlServerBuilder();
    $builder->select('*')->from('posts')->where('id', '=', 1)->orWhereToday(['published_at', 'held_at']);
    $this->assertSame('select * from [posts] where [id] = ? or cast([published_at] as date) = ? or cast([held_at] as date) = ?', $builder->toSql());
    $this->assertEquals([0 => 1, 1 => '2022-04-20', 2 => '2022-04-20'], $builder->getBindings());
});

test('where future', function () {
    Carbon::setTestNow('2022-04-22 21:01:23.123456');

    $testDate = Carbon::create('2022-04-22 21:01:23.123456');

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('posts')->whereFuture('published_at');
    $this->assertSame('select * from "posts" where "published_at" > ?', $builder->toSql());
    $this->assertEquals([0 => $testDate], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('posts')->where('id', '=', 1)->orWhereFuture('published_at');
    $this->assertSame('select * from "posts" where "id" = ? or "published_at" > ?', $builder->toSql());
    $this->assertEquals([0 => 1, 1 => $testDate], $builder->getBindings());
});

test('passing array to where future', function () {
    Carbon::setTestNow('2022-04-22 01:23:45.123456');

    $testDate = Carbon::create('2022-04-22 01:23:45.123456');

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('posts')->whereFuture(['published_at', 'held_at']);
    $this->assertSame('select * from "posts" where "published_at" > ? and "held_at" > ?', $builder->toSql());
    $this->assertEquals([0 => $testDate, 1 => $testDate], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('posts')->where('id', '=', 1)->orWhereFuture(['published_at', 'held_at']);
    $this->assertSame('select * from "posts" where "id" = ? or "published_at" > ? or "held_at" > ?', $builder->toSql());
    $this->assertEquals([0 => 1, 1 => $testDate, 2 => $testDate], $builder->getBindings());
});

test('where like postgres', function () {
    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('*')->from('users')->where('id', 'like', '1');
    $this->assertSame('select * from "users" where "id"::text like ?', $builder->toSql());
    $this->assertEquals([0 => '1'], $builder->getBindings());

    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('*')->from('users')->where('id', 'LIKE', '1');
    $this->assertSame('select * from "users" where "id"::text LIKE ?', $builder->toSql());
    $this->assertEquals([0 => '1'], $builder->getBindings());

    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('*')->from('users')->where('id', 'ilike', '1');
    $this->assertSame('select * from "users" where "id"::text ilike ?', $builder->toSql());
    $this->assertEquals([0 => '1'], $builder->getBindings());

    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('*')->from('users')->where('id', 'not like', '1');
    $this->assertSame('select * from "users" where "id"::text not like ?', $builder->toSql());
    $this->assertEquals([0 => '1'], $builder->getBindings());

    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('*')->from('users')->where('id', 'not ilike', '1');
    $this->assertSame('select * from "users" where "id"::text not ilike ?', $builder->toSql());
    $this->assertEquals([0 => '1'], $builder->getBindings());
});

test('where like clause postgres', function () {
    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('*')->from('users')->whereLike('id', '1');
    $this->assertSame('select * from "users" where "id"::text ilike ?', $builder->toSql());
    $this->assertEquals([0 => '1'], $builder->getBindings());

    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('*')->from('users')->whereLike('id', '1', false);
    $this->assertSame('select * from "users" where "id"::text ilike ?', $builder->toSql());
    $this->assertEquals([0 => '1'], $builder->getBindings());

    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('*')->from('users')->whereLike('id', '1', true);
    $this->assertSame('select * from "users" where "id"::text like ?', $builder->toSql());
    $this->assertEquals([0 => '1'], $builder->getBindings());

    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('*')->from('users')->whereNotLike('id', '1');
    $this->assertSame('select * from "users" where "id"::text not ilike ?', $builder->toSql());
    $this->assertEquals([0 => '1'], $builder->getBindings());

    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('*')->from('users')->whereNotLike('id', '1', false);
    $this->assertSame('select * from "users" where "id"::text not ilike ?', $builder->toSql());
    $this->assertEquals([0 => '1'], $builder->getBindings());

    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('*')->from('users')->whereNotLike('id', '1', true);
    $this->assertSame('select * from "users" where "id"::text not like ?', $builder->toSql());
    $this->assertEquals([0 => '1'], $builder->getBindings());
});

test('where like clause mysql', function () {
    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users')->whereLike('id', '1');
    $this->assertSame('select * from `users` where `id` like ?', $builder->toSql());
    $this->assertEquals([0 => '1'], $builder->getBindings());

    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users')->whereLike('id', '1', false);
    $this->assertSame('select * from `users` where `id` like ?', $builder->toSql());
    $this->assertEquals([0 => '1'], $builder->getBindings());

    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users')->whereLike('id', '1', true);
    $this->assertSame('select * from `users` where `id` like binary ?', $builder->toSql());
    $this->assertEquals([0 => '1'], $builder->getBindings());

    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users')->whereNotLike('id', '1');
    $this->assertSame('select * from `users` where `id` not like ?', $builder->toSql());
    $this->assertEquals([0 => '1'], $builder->getBindings());

    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users')->whereNotLike('id', '1', false);
    $this->assertSame('select * from `users` where `id` not like ?', $builder->toSql());
    $this->assertEquals([0 => '1'], $builder->getBindings());

    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users')->whereNotLike('id', '1', true);
    $this->assertSame('select * from `users` where `id` not like binary ?', $builder->toSql());
    $this->assertEquals([0 => '1'], $builder->getBindings());
});

test('where like clause sqlite', function () {
    $builder = queryBuilderGetSQLiteBuilder();
    $builder->select('*')->from('users')->whereLike('id', '1');
    $this->assertSame('select * from "users" where "id" like ?', $builder->toSql());
    $this->assertEquals([0 => '1'], $builder->getBindings());

    $builder = queryBuilderGetSQLiteBuilder();
    $builder->select('*')->from('users')->whereLike('id', '1', true);
    $this->assertSame('select * from "users" where "id" glob ?', $builder->toSql());
    $this->assertEquals([0 => '1'], $builder->getBindings());

    $builder = queryBuilderGetSQLiteBuilder();
    $builder->select('*')->from('users')->whereLike('description', 'Hell* _orld?%', true);
    $this->assertSame('select * from "users" where "description" glob ?', $builder->toSql());
    $this->assertEquals([0 => 'Hell[*] ?orld[?]*'], $builder->getBindings());

    $builder = queryBuilderGetSQLiteBuilder();
    $builder->select('*')->from('users')->whereNotLike('id', '1');
    $this->assertSame('select * from "users" where "id" not like ?', $builder->toSql());
    $this->assertEquals([0 => '1'], $builder->getBindings());

    $builder = queryBuilderGetSQLiteBuilder();
    $builder->select('*')->from('users')->whereNotLike('description', 'Hell* _orld?%', true);
    $this->assertSame('select * from "users" where "description" not glob ?', $builder->toSql());
    $this->assertEquals([0 => 'Hell[*] ?orld[?]*'], $builder->getBindings());

    $builder = queryBuilderGetSQLiteBuilder();
    $builder->select('*')->from('users')->whereLike('name', 'John%', true)->whereNotLike('name', '%Doe%', true);
    $this->assertSame('select * from "users" where "name" glob ? and "name" not glob ?', $builder->toSql());
    $this->assertEquals([0 => 'John*', 1 => '*Doe*'], $builder->getBindings());

    $builder = queryBuilderGetSQLiteBuilder();
    $builder->select('*')->from('users')->whereLike('name', 'John%')->orWhereLike('name', 'Jane%', true);
    $this->assertSame('select * from "users" where "name" like ? or "name" glob ?', $builder->toSql());
    $this->assertEquals([0 => 'John%', 1 => 'Jane*'], $builder->getBindings());
});

test('where like clause sql server', function () {
    $builder = queryBuilderGetSqlServerBuilder();
    $builder->select('*')->from('users')->whereLike('id', '1');
    $this->assertSame('select * from [users] where [id] like ?', $builder->toSql());
    $this->assertEquals([0 => '1'], $builder->getBindings());

    $builder = queryBuilderGetSqlServerBuilder();
    $builder->select('*')->from('users')->whereLike('id', '1')->orWhereLike('id', '2');
    $this->assertSame('select * from [users] where [id] like ? or [id] like ?', $builder->toSql());
    $this->assertEquals([0 => '1', 1 => '2'], $builder->getBindings());

    $builder = queryBuilderGetSqlServerBuilder();
    $builder->select('*')->from('users')->whereNotLike('id', '1');
    $this->assertSame('select * from [users] where [id] not like ?', $builder->toSql());
    $this->assertEquals([0 => '1'], $builder->getBindings());
});

test('where date sqlite', function () {
    $builder = queryBuilderGetSQLiteBuilder();
    $builder->select('*')->from('users')->whereDate('created_at', '=', '2015-12-21');
    $this->assertSame('select * from "users" where strftime(\'%Y-%m-%d\', "created_at") = cast(? as text)', $builder->toSql());
    $this->assertEquals([0 => '2015-12-21'], $builder->getBindings());

    $builder = queryBuilderGetSQLiteBuilder();
    $builder->select('*')->from('users')->whereDate('created_at', new Raw('NOW()'));
    $this->assertSame('select * from "users" where strftime(\'%Y-%m-%d\', "created_at") = cast(NOW() as text)', $builder->toSql());
});

test('where day sqlite', function () {
    $builder = queryBuilderGetSQLiteBuilder();
    $builder->select('*')->from('users')->whereDay('created_at', '=', 1);
    $this->assertSame('select * from "users" where strftime(\'%d\', "created_at") = cast(? as text)', $builder->toSql());
    $this->assertEquals([0 => 1], $builder->getBindings());
});

test('where month sqlite', function () {
    $builder = queryBuilderGetSQLiteBuilder();
    $builder->select('*')->from('users')->whereMonth('created_at', '=', 5);
    $this->assertSame('select * from "users" where strftime(\'%m\', "created_at") = cast(? as text)', $builder->toSql());
    $this->assertEquals([0 => 5], $builder->getBindings());
});

test('where year sqlite', function () {
    $builder = queryBuilderGetSQLiteBuilder();
    $builder->select('*')->from('users')->whereYear('created_at', '=', 2014);
    $this->assertSame('select * from "users" where strftime(\'%Y\', "created_at") = cast(? as text)', $builder->toSql());
    $this->assertEquals([0 => 2014], $builder->getBindings());
});

test('where time sqlite', function () {
    $builder = queryBuilderGetSQLiteBuilder();
    $builder->select('*')->from('users')->whereTime('created_at', '>=', '22:00');
    $this->assertSame('select * from "users" where strftime(\'%H:%M:%S\', "created_at") >= cast(? as text)', $builder->toSql());
    $this->assertEquals([0 => '22:00'], $builder->getBindings());
});

test('where time operator optional sqlite', function () {
    $builder = queryBuilderGetSQLiteBuilder();
    $builder->select('*')->from('users')->whereTime('created_at', '22:00');
    $this->assertSame('select * from "users" where strftime(\'%H:%M:%S\', "created_at") = cast(? as text)', $builder->toSql());
    $this->assertEquals([0 => '22:00'], $builder->getBindings());
});

test('where date sql server', function () {
    $builder = queryBuilderGetSqlServerBuilder();
    $builder->select('*')->from('users')->whereDate('created_at', '=', '2015-12-21');
    $this->assertSame('select * from [users] where cast([created_at] as date) = ?', $builder->toSql());
    $this->assertEquals([0 => '2015-12-21'], $builder->getBindings());

    $builder = queryBuilderGetSqlServerBuilder();
    $builder->select('*')->from('users')->whereDate('created_at', new Raw('NOW()'));
    $this->assertSame('select * from [users] where cast([created_at] as date) = NOW()', $builder->toSql());
});

test('where day sql server', function () {
    $builder = queryBuilderGetSqlServerBuilder();
    $builder->select('*')->from('users')->whereDay('created_at', '=', 1);
    $this->assertSame('select * from [users] where day([created_at]) = ?', $builder->toSql());
    $this->assertEquals([0 => 1], $builder->getBindings());
});

test('where month sql server', function () {
    $builder = queryBuilderGetSqlServerBuilder();
    $builder->select('*')->from('users')->whereMonth('created_at', '=', 5);
    $this->assertSame('select * from [users] where month([created_at]) = ?', $builder->toSql());
    $this->assertEquals([0 => 5], $builder->getBindings());
});

test('where year sql server', function () {
    $builder = queryBuilderGetSqlServerBuilder();
    $builder->select('*')->from('users')->whereYear('created_at', '=', 2014);
    $this->assertSame('select * from [users] where year([created_at]) = ?', $builder->toSql());
    $this->assertEquals([0 => 2014], $builder->getBindings());
});

test('where null safe equals', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereNullSafeEquals('foo', 'bar');
    $this->assertSame('select * from "users" where "foo" is not distinct from ?', $builder->toSql());
    $this->assertEquals(['bar'], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereNullSafeEquals('foo', 'bar')->whereNullSafeEquals('baz', 'qux');
    $this->assertSame('select * from "users" where "foo" is not distinct from ? and "baz" is not distinct from ?', $builder->toSql());
    $this->assertEquals(['bar', 'qux'], $builder->getBindings());
});

test('or where null safe equals', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('foo', 'bar')->orWhereNullSafeEquals('baz', 'qux');
    $this->assertSame('select * from "users" where "foo" = ? or "baz" is not distinct from ?', $builder->toSql());
    $this->assertEquals(['bar', 'qux'], $builder->getBindings());
});

test('where null safe equals via null safe operator', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('foo', '<=>', 'bar');
    $this->assertSame('select * from "users" where "foo" is not distinct from ?', $builder->toSql());
    $this->assertEquals(['bar'], $builder->getBindings());
});

test('where null safe equals with null via operator', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('foo', '<=>', null);
    $this->assertSame('select * from "users" where "foo" is null', $builder->toSql());
});

test('where null safe equals my sql', function () {
    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users')->whereNullSafeEquals('foo', 'bar');
    $this->assertSame('select * from `users` where `foo` <=> ?', $builder->toSql());
    $this->assertEquals(['bar'], $builder->getBindings());

    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users')->where('foo', '<=>', 'bar');
    $this->assertSame('select * from `users` where `foo` <=> ?', $builder->toSql());
    $this->assertEquals(['bar'], $builder->getBindings());
});

test('where null safe equals s q lite', function () {
    $builder = queryBuilderGetSQLiteBuilder();
    $builder->select('*')->from('users')->whereNullSafeEquals('foo', 'bar');
    $this->assertSame('select * from "users" where "foo" is ?', $builder->toSql());
    $this->assertEquals(['bar'], $builder->getBindings());

    $builder = queryBuilderGetSQLiteBuilder();
    $builder->select('*')->from('users')->where('foo', '<=>', 'bar');
    $this->assertSame('select * from "users" where "foo" is ?', $builder->toSql());
    $this->assertEquals(['bar'], $builder->getBindings());
});

test('where null safe equals postgres', function () {
    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('*')->from('users')->whereNullSafeEquals('foo', 'bar');
    $this->assertSame('select * from "users" where "foo" is not distinct from ?', $builder->toSql());
    $this->assertEquals(['bar'], $builder->getBindings());

    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('*')->from('users')->where('foo', '<=>', 'bar');
    $this->assertSame('select * from "users" where "foo" is not distinct from ?', $builder->toSql());
    $this->assertEquals(['bar'], $builder->getBindings());
});

test('where null safe equals sql server', function () {
    $builder = queryBuilderGetSqlServerBuilder();
    $builder->select('*')->from('users')->whereNullSafeEquals('foo', 'bar');
    $this->assertSame('select * from [users] where exists (select [foo] intersect select ?)', $builder->toSql());
    $this->assertEquals(['bar'], $builder->getBindings());

    $builder = queryBuilderGetSqlServerBuilder();
    $builder->select('*')->from('users')->where('foo', '<=>', 'bar');
    $this->assertSame('select * from [users] where exists (select [foo] intersect select ?)', $builder->toSql());
    $this->assertEquals(['bar'], $builder->getBindings());
});

test('where betweens', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereBetween('id', [1, 2]);
    $this->assertSame('select * from "users" where "id" between ? and ?', $builder->toSql());
    $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereBetween('id', [[1, 2, 3]]);
    $this->assertSame('select * from "users" where "id" between ? and ?', $builder->toSql());
    $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereBetween('id', [[1], [2, 3]]);
    $this->assertSame('select * from "users" where "id" between ? and ?', $builder->toSql());
    $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereNotBetween('id', [1, 2]);
    $this->assertSame('select * from "users" where "id" not between ? and ?', $builder->toSql());
    $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereBetween('id', [new Raw(1), new Raw(2)]);
    $this->assertSame('select * from "users" where "id" between 1 and 2', $builder->toSql());
    $this->assertEquals([], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $period = now()->startOfDay()->toPeriod(now()->addDay()->startOfDay());
    $builder->select('*')->from('users')->whereBetween('created_at', $period);
    $this->assertSame('select * from "users" where "created_at" between ? and ?', $builder->toSql());
    $this->assertEquals([now()->startOfDay(), now()->addDay()->startOfDay()], $builder->getBindings());

    // custom long carbon period date
    $builder = queryBuilderGetBuilder();
    $period = now()->startOfDay()->toPeriod(now()->addMonth()->startOfDay());
    $builder->select('*')->from('users')->whereBetween('created_at', $period);
    $this->assertSame('select * from "users" where "created_at" between ? and ?', $builder->toSql());
    $this->assertEquals([now()->startOfDay(), now()->addMonth()->startOfDay()], $builder->getBindings());

    // DatePeriod with end date
    $builder = queryBuilderGetBuilder();
    $period = new \DatePeriod(now()->startOfDay(), new \DateInterval('P1D'), now()->addDays(5)->startOfDay());
    $builder->select('*')->from('users')->whereBetween('created_at', $period);
    $this->assertSame('select * from "users" where "created_at" between ? and ?', $builder->toSql());
    $this->assertEquals([now()->startOfDay(), now()->addDays(5)->startOfDay()], $builder->getBindings());

    // DatePeriod with recurrence count (no end date)
    $builder = queryBuilderGetBuilder();
    $period = new \DatePeriod(now()->startOfDay(), new \DateInterval('P1D'), 5);
    $builder->select('*')->from('users')->whereBetween('created_at', $period);
    $this->assertSame('select * from "users" where "created_at" between ? and ?', $builder->toSql());
    $this->assertEquals([now()->startOfDay(), now()->addDays(5)->startOfDay()], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereBetween('id', collect([1, 2]));
    $this->assertSame('select * from "users" where "id" between ? and ?', $builder->toSql());
    $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());

    $subqueryBuilder = queryBuilderGetBuilder();
    $subqueryBuilder->select('id')->from('posts')->where('status', 'published')->orderByDesc('created_at')->limit(1);
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereBetween($subqueryBuilder, collect([1, 2]));
    $this->assertSame('select * from "users" where (select "id" from "posts" where "status" = ? order by "created_at" desc limit 1) between ? and ?', $builder->toSql());
    $this->assertEquals([0 => 'published', 1 => 1, 2 => 2], $builder->getBindings());
});

test('or where between', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('id', '=', 1)->orWhereBetween('id', [3, 5]);
    $this->assertSame('select * from "users" where "id" = ? or "id" between ? and ?', $builder->toSql());
    $this->assertEquals([0 => 1, 1 => 3, 2 => 5], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('id', '=', 1)->orWhereBetween('id', [[3, 4, 5]]);
    $this->assertSame('select * from "users" where "id" = ? or "id" between ? and ?', $builder->toSql());
    $this->assertEquals([0 => 1, 1 => 3, 2 => 4], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('id', '=', 1)->orWhereBetween('id', [[3, 5]]);
    $this->assertSame('select * from "users" where "id" = ? or "id" between ? and ?', $builder->toSql());
    $this->assertEquals([0 => 1, 1 => 3, 2 => 5], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('id', '=', 1)->orWhereBetween('id', [[4], [6, 8]]);
    $this->assertSame('select * from "users" where "id" = ? or "id" between ? and ?', $builder->toSql());
    $this->assertEquals([0 => 1, 1 => 4, 2 => 6], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('id', '=', 1)->orWhereBetween('id', collect([3, 4]));
    $this->assertSame('select * from "users" where "id" = ? or "id" between ? and ?', $builder->toSql());
    $this->assertEquals([0 => 1, 1 => 3, 2 => 4], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('id', '=', 1)->orWhereBetween('id', [new Raw(3), new Raw(4)]);
    $this->assertSame('select * from "users" where "id" = ? or "id" between 3 and 4', $builder->toSql());
    $this->assertEquals([0 => 1], $builder->getBindings());
});

test('or where not between', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('id', '=', 1)->orWhereNotBetween('id', [3, 5]);
    $this->assertSame('select * from "users" where "id" = ? or "id" not between ? and ?', $builder->toSql());
    $this->assertEquals([0 => 1, 1 => 3, 2 => 5], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('id', '=', 1)->orWhereNotBetween('id', [[3, 4, 5]]);
    $this->assertSame('select * from "users" where "id" = ? or "id" not between ? and ?', $builder->toSql());
    $this->assertEquals([0 => 1, 1 => 3, 2 => 4], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('id', '=', 1)->orWhereNotBetween('id', [[3, 5]]);
    $this->assertSame('select * from "users" where "id" = ? or "id" not between ? and ?', $builder->toSql());
    $this->assertEquals([0 => 1, 1 => 3, 2 => 5], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('id', '=', 1)->orWhereNotBetween('id', [[4], [6, 8]]);
    $this->assertSame('select * from "users" where "id" = ? or "id" not between ? and ?', $builder->toSql());
    $this->assertEquals([0 => 1, 1 => 4, 2 => 6], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('id', '=', 1)->orWhereNotBetween('id', collect([3, 4]));
    $this->assertSame('select * from "users" where "id" = ? or "id" not between ? and ?', $builder->toSql());
    $this->assertEquals([0 => 1, 1 => 3, 2 => 4], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('id', '=', 1)->orWhereNotBetween('id', [new Raw(3), new Raw(4)]);
    $this->assertSame('select * from "users" where "id" = ? or "id" not between 3 and 4', $builder->toSql());
    $this->assertEquals([0 => 1], $builder->getBindings());
});

test('where between columns', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereBetweenColumns('id', ['users.created_at', 'users.updated_at']);
    $this->assertSame('select * from "users" where "id" between "users"."created_at" and "users"."updated_at"', $builder->toSql());
    $this->assertEquals([], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereNotBetweenColumns('id', ['created_at', 'updated_at']);
    $this->assertSame('select * from "users" where "id" not between "created_at" and "updated_at"', $builder->toSql());
    $this->assertEquals([], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereBetweenColumns('id', [new Raw(1), new Raw(2)]);
    $this->assertSame('select * from "users" where "id" between 1 and 2', $builder->toSql());
    $this->assertEquals([], $builder->getBindings());

    $subqueryBuilder = queryBuilderGetBuilder();
    $subqueryBuilder->select('created_at')->from('posts')->where('status', 'published')->orderByDesc('created_at')->limit(1);
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereBetweenColumns($subqueryBuilder, ['created_at', 'updated_at']);
    $this->assertSame('select * from "users" where (select "created_at" from "posts" where "status" = ? order by "created_at" desc limit 1) between "created_at" and "updated_at"', $builder->toSql());
    $this->assertEquals([0 => 'published'], $builder->getBindings());
});

test('or where between columns', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('id', 2)->orWhereBetweenColumns('id', ['users.created_at', 'users.updated_at']);
    $this->assertSame('select * from "users" where "id" = ? or "id" between "users"."created_at" and "users"."updated_at"', $builder->toSql());
    $this->assertEquals([0 => 2], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('id', 2)->orWhereBetweenColumns('id', ['created_at', 'updated_at']);
    $this->assertSame('select * from "users" where "id" = ? or "id" between "created_at" and "updated_at"', $builder->toSql());
    $this->assertEquals([0 => 2], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('id', 2)->orWhereBetweenColumns('id', [new Raw(1), new Raw(2)]);
    $this->assertSame('select * from "users" where "id" = ? or "id" between 1 and 2', $builder->toSql());
    $this->assertEquals([0 => 2], $builder->getBindings());
});

test('or where not between columns', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('id', 2)->orWhereNotBetweenColumns('id', ['users.created_at', 'users.updated_at']);
    $this->assertSame('select * from "users" where "id" = ? or "id" not between "users"."created_at" and "users"."updated_at"', $builder->toSql());
    $this->assertEquals([0 => 2], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('id', 2)->orWhereNotBetweenColumns('id', ['created_at', 'updated_at']);
    $this->assertSame('select * from "users" where "id" = ? or "id" not between "created_at" and "updated_at"', $builder->toSql());
    $this->assertEquals([0 => 2], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('id', 2)->orWhereNotBetweenColumns('id', [new Raw(1), new Raw(2)]);
    $this->assertSame('select * from "users" where "id" = ? or "id" not between 1 and 2', $builder->toSql());
    $this->assertEquals([0 => 2], $builder->getBindings());
});

test('where value between', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereValueBetween('2020-01-01 19:30:00', ['created_at', 'updated_at']);
    $this->assertSame('select * from "users" where ? between "created_at" and "updated_at"', $builder->toSql());
    $this->assertEquals([0 => '2020-01-01 19:30:00'], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereValueBetween('2020-01-01 19:30:00', ['created_at', 'updated_at']);
    $this->assertSame('select * from "users" where ? between "created_at" and "updated_at"', $builder->toSql());
    $this->assertEquals([0 => '2020-01-01 19:30:00'], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereValueBetween('2020-01-01 19:30:00', [new Raw(1), new Raw(2)]);
    $this->assertSame('select * from "users" where ? between 1 and 2', $builder->toSql());
    $this->assertEquals([0 => '2020-01-01 19:30:00'], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereValueBetween(new Raw(1), ['created_at', 'updated_at']);
    $this->assertSame('select * from "users" where 1 between "created_at" and "updated_at"', $builder->toSql());
});

test('or where value between', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('id', 2)->orWhereValueBetween('2020-01-01 19:30:00', ['created_at', 'updated_at']);
    $this->assertSame('select * from "users" where "id" = ? or ? between "created_at" and "updated_at"', $builder->toSql());
    $this->assertEquals([0 => 2, 1 => '2020-01-01 19:30:00'], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('id', 2)->orWhereValueBetween('2020-01-01 19:30:00', ['created_at', 'updated_at']);
    $this->assertSame('select * from "users" where "id" = ? or ? between "created_at" and "updated_at"', $builder->toSql());
    $this->assertEquals([0 => 2, 1 => '2020-01-01 19:30:00'], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('id', 2)->orWhereValueBetween('2020-01-01 19:30:00', [new Raw(1), new Raw(2)]);
    $this->assertSame('select * from "users" where "id" = ? or ? between 1 and 2', $builder->toSql());
    $this->assertEquals([0 => 2, 1 => '2020-01-01 19:30:00'], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('id', 2)->orWhereValueBetween(new Raw(1), ['created_at', 'updated_at']);
    $this->assertSame('select * from "users" where "id" = ? or 1 between "created_at" and "updated_at"', $builder->toSql());
});

test('where value not between', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereValueNotBetween('2020-01-01 19:30:00', ['created_at', 'updated_at']);
    $this->assertSame('select * from "users" where ? not between "created_at" and "updated_at"', $builder->toSql());
    $this->assertEquals([0 => '2020-01-01 19:30:00'], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereValueNotBetween('2020-01-01 19:30:00', ['created_at', 'updated_at']);
    $this->assertSame('select * from "users" where ? not between "created_at" and "updated_at"', $builder->toSql());
    $this->assertEquals([0 => '2020-01-01 19:30:00'], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereValueNotBetween('2020-01-01 19:30:00', [new Raw(1), new Raw(2)]);
    $this->assertSame('select * from "users" where ? not between 1 and 2', $builder->toSql());
    $this->assertEquals([0 => '2020-01-01 19:30:00'], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereValueNotBetween(new Raw(1), ['created_at', 'updated_at']);
    $this->assertSame('select * from "users" where 1 not between "created_at" and "updated_at"', $builder->toSql());
});

test('or where value not between', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('id', 2)->orWhereValueNotBetween('2020-01-01 19:30:00', ['created_at', 'updated_at']);
    $this->assertSame('select * from "users" where "id" = ? or ? not between "created_at" and "updated_at"', $builder->toSql());
    $this->assertEquals([0 => 2, 1 => '2020-01-01 19:30:00'], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('id', 2)->orWhereValueNotBetween('2020-01-01 19:30:00', ['created_at', 'updated_at']);
    $this->assertSame('select * from "users" where "id" = ? or ? not between "created_at" and "updated_at"', $builder->toSql());
    $this->assertEquals([0 => 2, 1 => '2020-01-01 19:30:00'], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('id', 2)->orWhereValueNotBetween('2020-01-01 19:30:00', [new Raw(1), new Raw(2)]);
    $this->assertSame('select * from "users" where "id" = ? or ? not between 1 and 2', $builder->toSql());
    $this->assertEquals([0 => 2, 1 => '2020-01-01 19:30:00'], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('id', 2)->orWhereValueNotBetween(new Raw(1), ['created_at', 'updated_at']);
    $this->assertSame('select * from "users" where "id" = ? or 1 not between "created_at" and "updated_at"', $builder->toSql());
});

test('basic or wheres', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('id', '=', 1)->orWhere('email', '=', 'foo');
    $this->assertSame('select * from "users" where "id" = ? or "email" = ?', $builder->toSql());
    $this->assertEquals([0 => 1, 1 => 'foo'], $builder->getBindings());
});

test('basic or where not', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->orWhereNot('name', 'foo')->orWhereNot('name', '<>', 'bar');
    $this->assertSame('select * from "users" where not "name" = ? or not "name" <> ?', $builder->toSql());
    $this->assertEquals(['foo', 'bar'], $builder->getBindings());
});

test('raw wheres', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereRaw('id = ? or email = ?', [1, 'foo']);
    $this->assertSame('select * from "users" where id = ? or email = ?', $builder->toSql());
    $this->assertEquals([0 => 1, 1 => 'foo'], $builder->getBindings());
});

test('raw or wheres', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('id', '=', 1)->orWhereRaw('email = ?', ['foo']);
    $this->assertSame('select * from "users" where "id" = ? or email = ?', $builder->toSql());
    $this->assertEquals([0 => 1, 1 => 'foo'], $builder->getBindings());
});

test('basic where ins', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereIn('id', [1, 2, 3]);
    $this->assertSame('select * from "users" where "id" in (?, ?, ?)', $builder->toSql());
    $this->assertEquals([0 => 1, 1 => 2, 2 => 3], $builder->getBindings());

    // associative arrays as values:
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereIn('id', [
        'issue' => 45582,
        'id' => 2,
        3,
    ]);
    $this->assertSame('select * from "users" where "id" in (?, ?, ?)', $builder->toSql());
    $this->assertEquals([0 => 45582, 1 => 2, 2 => 3], $builder->getBindings());

    // can accept some nested arrays as values.
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereIn('id', [
        ['issue' => 45582],
        ['id' => 2],
        [3],
    ]);
    $this->assertSame('select * from "users" where "id" in (?, ?, ?)', $builder->toSql());
    $this->assertEquals([0 => 45582, 1 => 2, 2 => 3], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('id', '=', 1)->orWhereIn('id', [1, 2, 3]);
    $this->assertSame('select * from "users" where "id" = ? or "id" in (?, ?, ?)', $builder->toSql());
    $this->assertEquals([0 => 1, 1 => 1, 2 => 2, 3 => 3], $builder->getBindings());
});

test('basic where ins exception', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereIn('id', [
        [
            'a' => 1,
            'b' => 1,
        ],
        ['c' => 2],
        [3],
    ]);
})->throws(InvalidArgumentException::class);

test('basic where not ins', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereNotIn('id', [1, 2, 3]);
    $this->assertSame('select * from "users" where "id" not in (?, ?, ?)', $builder->toSql());
    $this->assertEquals([0 => 1, 1 => 2, 2 => 3], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('id', '=', 1)->orWhereNotIn('id', [1, 2, 3]);
    $this->assertSame('select * from "users" where "id" = ? or "id" not in (?, ?, ?)', $builder->toSql());
    $this->assertEquals([0 => 1, 1 => 1, 2 => 2, 3 => 3], $builder->getBindings());
});

test('raw where ins', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereIn('id', [new Raw(1)]);
    $this->assertSame('select * from "users" where "id" in (1)', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('id', '=', 1)->orWhereIn('id', [new Raw(1)]);
    $this->assertSame('select * from "users" where "id" = ? or "id" in (1)', $builder->toSql());
    $this->assertEquals([0 => 1], $builder->getBindings());
});

test('empty where ins', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereIn('id', []);
    $this->assertSame('select * from "users" where 0 = 1', $builder->toSql());
    $this->assertEquals([], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('id', '=', 1)->orWhereIn('id', []);
    $this->assertSame('select * from "users" where "id" = ? or 0 = 1', $builder->toSql());
    $this->assertEquals([0 => 1], $builder->getBindings());
});

test('empty where not ins', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereNotIn('id', []);
    $this->assertSame('select * from "users" where 1 = 1', $builder->toSql());
    $this->assertEquals([], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('id', '=', 1)->orWhereNotIn('id', []);
    $this->assertSame('select * from "users" where "id" = ? or 1 = 1', $builder->toSql());
    $this->assertEquals([0 => 1], $builder->getBindings());
});

test('where integer in raw', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereIntegerInRaw('id', [
        '1a', 2, Bar::FOO,
    ]);
    $this->assertSame('select * from "users" where "id" in (1, 2, 5)', $builder->toSql());
    $this->assertEquals([], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereIntegerInRaw('id', [
        ['id' => '1a'],
        ['id' => 2],
        ['any' => '3'],
        ['id' => Bar::FOO],
    ]);
    $this->assertSame('select * from "users" where "id" in (1, 2, 3, 5)', $builder->toSql());
    $this->assertEquals([], $builder->getBindings());
});

test('or where integer in raw', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('id', '=', 1)->orWhereIntegerInRaw('id', ['1a', 2]);
    $this->assertSame('select * from "users" where "id" = ? or "id" in (1, 2)', $builder->toSql());
    $this->assertEquals([0 => 1], $builder->getBindings());
});

test('where integer not in raw', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereIntegerNotInRaw('id', ['1a', 2]);
    $this->assertSame('select * from "users" where "id" not in (1, 2)', $builder->toSql());
    $this->assertEquals([], $builder->getBindings());
});

test('or where integer not in raw', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('id', '=', 1)->orWhereIntegerNotInRaw('id', ['1a', 2]);
    $this->assertSame('select * from "users" where "id" = ? or "id" not in (1, 2)', $builder->toSql());
    $this->assertEquals([0 => 1], $builder->getBindings());
});

test('empty where integer in raw', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereIntegerInRaw('id', []);
    $this->assertSame('select * from "users" where 0 = 1', $builder->toSql());
    $this->assertEquals([], $builder->getBindings());
});

test('empty where integer not in raw', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereIntegerNotInRaw('id', []);
    $this->assertSame('select * from "users" where 1 = 1', $builder->toSql());
    $this->assertEquals([], $builder->getBindings());
});

test('basic where column', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereColumn('first_name', 'last_name')->orWhereColumn('first_name', 'middle_name');
    $this->assertSame('select * from "users" where "first_name" = "last_name" or "first_name" = "middle_name"', $builder->toSql());
    $this->assertEquals([], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereColumn('updated_at', '>', 'created_at');
    $this->assertSame('select * from "users" where "updated_at" > "created_at"', $builder->toSql());
    $this->assertEquals([], $builder->getBindings());
});

test('array where column', function () {
    $conditions = [
        ['first_name', 'last_name'],
        ['updated_at', '>', 'created_at'],
    ];

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereColumn($conditions);
    $this->assertSame('select * from "users" where ("first_name" = "last_name" and "updated_at" > "created_at")', $builder->toSql());
    $this->assertEquals([], $builder->getBindings());
});

test('where fulltext my sql', function () {
    $builder = queryBuilderGetMySqlBuilderWithProcessor();
    $builder->select('*')->from('users')->whereFullText('body', 'Hello World');
    $this->assertSame('select * from `users` where match (`body`) against (? in natural language mode)', $builder->toSql());
    $this->assertEquals(['Hello World'], $builder->getBindings());

    $builder = queryBuilderGetMySqlBuilderWithProcessor();
    $builder->select('*')->from('users')->whereFullText('body', 'Hello World', ['expanded' => true]);
    $this->assertSame('select * from `users` where match (`body`) against (? in natural language mode with query expansion)', $builder->toSql());
    $this->assertEquals(['Hello World'], $builder->getBindings());

    $builder = queryBuilderGetMySqlBuilderWithProcessor();
    $builder->select('*')->from('users')->whereFullText('body', '+Hello -World', ['mode' => 'boolean']);
    $this->assertSame('select * from `users` where match (`body`) against (? in boolean mode)', $builder->toSql());
    $this->assertEquals(['+Hello -World'], $builder->getBindings());

    $builder = queryBuilderGetMySqlBuilderWithProcessor();
    $builder->select('*')->from('users')->whereFullText('body', '+Hello -World', ['mode' => 'boolean', 'expanded' => true]);
    $this->assertSame('select * from `users` where match (`body`) against (? in boolean mode)', $builder->toSql());
    $this->assertEquals(['+Hello -World'], $builder->getBindings());

    $builder = queryBuilderGetMySqlBuilderWithProcessor();
    $builder->select('*')->from('users')->whereFullText(['body', 'title'], 'Car,Plane');
    $this->assertSame('select * from `users` where match (`body`, `title`) against (? in natural language mode)', $builder->toSql());
    $this->assertEquals(['Car,Plane'], $builder->getBindings());
});

test('where fulltext postgres', function () {
    $builder = queryBuilderGetPostgresBuilderWithProcessor();
    $builder->select('*')->from('users')->whereFullText('body', 'Hello World');
    $this->assertSame('select * from "users" where (to_tsvector(\'english\', "body")) @@ plainto_tsquery(\'english\', ?)', $builder->toSql());
    $this->assertEquals(['Hello World'], $builder->getBindings());

    $builder = queryBuilderGetPostgresBuilderWithProcessor();
    $builder->select('*')->from('users')->whereFullText('body', 'Hello World', ['language' => 'simple']);
    $this->assertSame('select * from "users" where (to_tsvector(\'simple\', "body")) @@ plainto_tsquery(\'simple\', ?)', $builder->toSql());
    $this->assertEquals(['Hello World'], $builder->getBindings());

    $builder = queryBuilderGetPostgresBuilderWithProcessor();
    $builder->select('*')->from('users')->whereFullText('body', 'Hello World', ['mode' => 'plain']);
    $this->assertSame('select * from "users" where (to_tsvector(\'english\', "body")) @@ plainto_tsquery(\'english\', ?)', $builder->toSql());
    $this->assertEquals(['Hello World'], $builder->getBindings());

    $builder = queryBuilderGetPostgresBuilderWithProcessor();
    $builder->select('*')->from('users')->whereFullText('body', 'Hello World', ['mode' => 'phrase']);
    $this->assertSame('select * from "users" where (to_tsvector(\'english\', "body")) @@ phraseto_tsquery(\'english\', ?)', $builder->toSql());
    $this->assertEquals(['Hello World'], $builder->getBindings());

    $builder = queryBuilderGetPostgresBuilderWithProcessor();
    $builder->select('*')->from('users')->whereFullText('body', '+Hello -World', ['mode' => 'websearch']);
    $this->assertSame('select * from "users" where (to_tsvector(\'english\', "body")) @@ websearch_to_tsquery(\'english\', ?)', $builder->toSql());
    $this->assertEquals(['+Hello -World'], $builder->getBindings());

    $builder = queryBuilderGetPostgresBuilderWithProcessor();
    $builder->select('*')->from('users')->whereFullText('body', 'Hello World', ['language' => 'simple', 'mode' => 'plain']);
    $this->assertSame('select * from "users" where (to_tsvector(\'simple\', "body")) @@ plainto_tsquery(\'simple\', ?)', $builder->toSql());
    $this->assertEquals(['Hello World'], $builder->getBindings());

    $builder = queryBuilderGetPostgresBuilderWithProcessor();
    $builder->select('*')->from('users')->whereFullText(['body', 'title'], 'Car Plane');
    $this->assertSame('select * from "users" where (to_tsvector(\'english\', "body") || to_tsvector(\'english\', "title")) @@ plainto_tsquery(\'english\', ?)', $builder->toSql());
    $this->assertEquals(['Car Plane'], $builder->getBindings());

    $builder = queryBuilderGetPostgresBuilderWithProcessor();
    $builder->select('*')->from('users')->whereFullText(['body', 'title'], 'Air | Plan:* -Car', ['mode' => 'raw']);
    $this->assertSame('select * from "users" where (to_tsvector(\'english\', "body") || to_tsvector(\'english\', "title")) @@ to_tsquery(\'english\', ?)', $builder->toSql());
    $this->assertEquals(['Air | Plan:* -Car'], $builder->getBindings());

    $builder = queryBuilderGetPostgresBuilderWithProcessor();
    $builder->select('*')->from('users')->whereFullText('search_vector', 'Hello World', ['vector' => true]);
    $this->assertSame('select * from "users" where ("search_vector") @@ plainto_tsquery(\'english\', ?)', $builder->toSql());
    $this->assertEquals(['Hello World'], $builder->getBindings());

    $builder = queryBuilderGetPostgresBuilderWithProcessor();
    $builder->select('*')->from('users')->whereFullText('search_vector_nl', 'Hello World', ['vector' => true, 'language' => 'dutch']);
    $this->assertSame('select * from "users" where ("search_vector_nl") @@ plainto_tsquery(\'dutch\', ?)', $builder->toSql());
    $this->assertEquals(['Hello World'], $builder->getBindings());

    $builder = queryBuilderGetPostgresBuilderWithProcessor();
    $builder->select('*')->from('users')->whereFullText('search_vector', '+Hello -World', ['vector' => true, 'mode' => 'websearch']);
    $this->assertSame('select * from "users" where ("search_vector") @@ websearch_to_tsquery(\'english\', ?)', $builder->toSql());
    $this->assertEquals(['+Hello -World'], $builder->getBindings());

    $builder = queryBuilderGetPostgresBuilderWithProcessor();
    $builder->select('*')->from('users')->whereFullText(['tsv_title', 'tsv_body'], 'Car Plane', ['vector' => true]);
    $this->assertSame('select * from "users" where ("tsv_title" || "tsv_body") @@ plainto_tsquery(\'english\', ?)', $builder->toSql());
    $this->assertEquals(['Car Plane'], $builder->getBindings());
});

test('where all', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereAll(['last_name', 'email'], '%Otwell%');
    $this->assertSame('select * from "users" where ("last_name" = ? and "email" = ?)', $builder->toSql());
    $this->assertEquals(['%Otwell%', '%Otwell%'], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereAll(['last_name', 'email'], 'not like', '%Otwell%');
    $this->assertSame('select * from "users" where ("last_name" not like ? and "email" not like ?)', $builder->toSql());
    $this->assertEquals(['%Otwell%', '%Otwell%'], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereAll([
        fn (Builder $query) => $query->where('last_name', 'like', '%Otwell%'),
        fn (Builder $query) => $query->where('email', 'like', '%Otwell%'),
    ]);
    $this->assertSame('select * from "users" where (("last_name" like ?) and ("email" like ?))', $builder->toSql());
    $this->assertEquals(['%Otwell%', '%Otwell%'], $builder->getBindings());
});

test('or where all', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('first_name', 'like', '%Taylor%')->orWhereAll(['last_name', 'email'], 'like', '%Otwell%');
    $this->assertSame('select * from "users" where "first_name" like ? or ("last_name" like ? and "email" like ?)', $builder->toSql());
    $this->assertEquals(['%Taylor%', '%Otwell%', '%Otwell%'], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('first_name', 'like', '%Taylor%')->whereAll(['last_name', 'email'], 'like', '%Otwell%', 'or');
    $this->assertSame('select * from "users" where "first_name" like ? or ("last_name" like ? and "email" like ?)', $builder->toSql());
    $this->assertEquals(['%Taylor%', '%Otwell%', '%Otwell%'], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('first_name', 'like', '%Taylor%')->orWhereAll(['last_name', 'email'], '%Otwell%');
    $this->assertSame('select * from "users" where "first_name" like ? or ("last_name" = ? and "email" = ?)', $builder->toSql());
    $this->assertEquals(['%Taylor%', '%Otwell%', '%Otwell%'], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('first_name', 'like', '%Taylor%')->orWhereAll([
        fn (Builder $query) => $query->where('last_name', 'like', '%Otwell%'),
        fn (Builder $query) => $query->where('email', 'like', '%Otwell%'),
    ]);
    $this->assertSame('select * from "users" where "first_name" like ? or (("last_name" like ?) and ("email" like ?))', $builder->toSql());
    $this->assertEquals(['%Taylor%', '%Otwell%', '%Otwell%'], $builder->getBindings());
});

test('where any', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereAny(['last_name', 'email'], 'like', '%Otwell%');
    $this->assertSame('select * from "users" where ("last_name" like ? or "email" like ?)', $builder->toSql());
    $this->assertEquals(['%Otwell%', '%Otwell%'], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereAny(['last_name', 'email'], '%Otwell%');
    $this->assertSame('select * from "users" where ("last_name" = ? or "email" = ?)', $builder->toSql());
    $this->assertEquals(['%Otwell%', '%Otwell%'], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereAny([
        fn (Builder $query) => $query->where('last_name', 'like', '%Otwell%'),
        fn (Builder $query) => $query->where('email', 'like', '%Otwell%'),
    ]);
    $this->assertSame('select * from "users" where (("last_name" like ?) or ("email" like ?))', $builder->toSql());
    $this->assertEquals(['%Otwell%', '%Otwell%'], $builder->getBindings());
});

test('or where any', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('first_name', 'like', '%Taylor%')->orWhereAny(['last_name', 'email'], 'like', '%Otwell%');
    $this->assertSame('select * from "users" where "first_name" like ? or ("last_name" like ? or "email" like ?)', $builder->toSql());
    $this->assertEquals(['%Taylor%', '%Otwell%', '%Otwell%'], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('first_name', 'like', '%Taylor%')->whereAny(['last_name', 'email'], 'like', '%Otwell%', 'or');
    $this->assertSame('select * from "users" where "first_name" like ? or ("last_name" like ? or "email" like ?)', $builder->toSql());
    $this->assertEquals(['%Taylor%', '%Otwell%', '%Otwell%'], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('first_name', 'like', '%Taylor%')->orWhereAny(['last_name', 'email'], '%Otwell%');
    $this->assertSame('select * from "users" where "first_name" like ? or ("last_name" = ? or "email" = ?)', $builder->toSql());
    $this->assertEquals(['%Taylor%', '%Otwell%', '%Otwell%'], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('first_name', 'like', '%Taylor%')->orWhereAny([
        fn (Builder $query) => $query->where('last_name', 'like', '%Otwell%'),
        fn (Builder $query) => $query->where('email', 'like', '%Otwell%'),
    ]);
    $this->assertSame('select * from "users" where "first_name" like ? or (("last_name" like ?) or ("email" like ?))', $builder->toSql());
    $this->assertEquals(['%Taylor%', '%Otwell%', '%Otwell%'], $builder->getBindings());
});

test('where none', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereNone(['last_name', 'email'], 'like', '%Otwell%');
    $this->assertSame('select * from "users" where not ("last_name" like ? or "email" like ?)', $builder->toSql());
    $this->assertEquals(['%Otwell%', '%Otwell%'], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereNone(['last_name', 'email'], 'Otwell');
    $this->assertSame('select * from "users" where not ("last_name" = ? or "email" = ?)', $builder->toSql());
    $this->assertEquals(['Otwell', 'Otwell'], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('first_name', 'like', '%Taylor%')->whereNone(['last_name', 'email'], 'like', '%Otwell%');
    $this->assertSame('select * from "users" where "first_name" like ? and not ("last_name" like ? or "email" like ?)', $builder->toSql());
    $this->assertEquals(['%Taylor%', '%Otwell%', '%Otwell%'], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereNone([
        fn (Builder $query) => $query->where('last_name', 'like', '%Otwell%'),
        fn (Builder $query) => $query->where('email', 'like', '%Otwell%'),
    ]);
    $this->assertSame('select * from "users" where not (("last_name" like ?) or ("email" like ?))', $builder->toSql());
    $this->assertEquals(['%Otwell%', '%Otwell%'], $builder->getBindings());
});

test('or where none', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('first_name', 'like', '%Taylor%')->orWhereNone(['last_name', 'email'], 'like', '%Otwell%');
    $this->assertSame('select * from "users" where "first_name" like ? or not ("last_name" like ? or "email" like ?)', $builder->toSql());
    $this->assertEquals(['%Taylor%', '%Otwell%', '%Otwell%'], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('first_name', 'like', '%Taylor%')->whereNone(['last_name', 'email'], 'like', '%Otwell%', 'or');
    $this->assertSame('select * from "users" where "first_name" like ? or not ("last_name" like ? or "email" like ?)', $builder->toSql());
    $this->assertEquals(['%Taylor%', '%Otwell%', '%Otwell%'], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('first_name', 'like', '%Taylor%')->orWhereNone(['last_name', 'email'], '%Otwell%');
    $this->assertSame('select * from "users" where "first_name" like ? or not ("last_name" = ? or "email" = ?)', $builder->toSql());
    $this->assertEquals(['%Taylor%', '%Otwell%', '%Otwell%'], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('first_name', 'like', '%Taylor%')->orWhereNone([
        fn (Builder $query) => $query->where('last_name', 'like', '%Otwell%'),
        fn (Builder $query) => $query->where('email', 'like', '%Otwell%'),
    ]);
    $this->assertSame('select * from "users" where "first_name" like ? or not (("last_name" like ?) or ("email" like ?))', $builder->toSql());
    $this->assertEquals(['%Taylor%', '%Otwell%', '%Otwell%'], $builder->getBindings());
});

test('unions', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('id', '=', 1);
    $builder->union(queryBuilderGetBuilder()->select('*')->from('users')->where('id', '=', 2));
    $this->assertSame('(select * from "users" where "id" = ?) union (select * from "users" where "id" = ?)', $builder->toSql());
    $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());

    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users')->where('id', '=', 1);
    $builder->union(queryBuilderGetMySqlBuilder()->select('*')->from('users')->where('id', '=', 2));
    $this->assertSame('(select * from `users` where `id` = ?) union (select * from `users` where `id` = ?)', $builder->toSql());
    $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());

    $builder = queryBuilderGetMySqlBuilder();
    $expectedSql = '(select `a` from `t1` where `a` = ? and `b` = ?) union (select `a` from `t2` where `a` = ? and `b` = ?) order by `a` asc limit 10';
    $union = queryBuilderGetMySqlBuilder()->select('a')->from('t2')->where('a', 11)->where('b', 2);
    $builder->select('a')->from('t1')->where('a', 10)->where('b', 1)->union($union)->orderBy('a')->limit(10);
    $this->assertEquals($expectedSql, $builder->toSql());
    $this->assertEquals([0 => 10, 1 => 1, 2 => 11, 3 => 2], $builder->getBindings());

    $builder = queryBuilderGetPostgresBuilder();
    $expectedSql = '(select "name" from "users" where "id" = ?) union (select "name" from "users" where "id" = ?)';
    $builder->select('name')->from('users')->where('id', '=', 1);
    $builder->union(queryBuilderGetPostgresBuilder()->select('name')->from('users')->where('id', '=', 2));
    $this->assertEquals($expectedSql, $builder->toSql());
    $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());

    $builder = queryBuilderGetSQLiteBuilder();
    $expectedSql = 'select * from (select "name" from "users" where "id" = ?) union select * from (select "name" from "users" where "id" = ?)';
    $builder->select('name')->from('users')->where('id', '=', 1);
    $builder->union(queryBuilderGetSQLiteBuilder()->select('name')->from('users')->where('id', '=', 2));
    $this->assertEquals($expectedSql, $builder->toSql());
    $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());

    $builder = queryBuilderGetSqlServerBuilder();
    $expectedSql = 'select * from (select [name] from [users] where [id] = ?) as [temp_table] union select * from (select [name] from [users] where [id] = ?) as [temp_table]';
    $builder->select('name')->from('users')->where('id', '=', 1);
    $builder->union(queryBuilderGetSqlServerBuilder()->select('name')->from('users')->where('id', '=', 2));
    $this->assertEquals($expectedSql, $builder->toSql());
    $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $instrumentBuilder = new InstrumentBuilder(queryBuilderGetBuilder());
    $builder->select('*')->from('users')->where('id', '=', 1)->union($instrumentBuilder->select('*')->from('users')->where('id', '=', 2));
    $this->assertSame('(select * from "users" where "id" = ?) union (select * from "users" where "id" = ?)', $builder->toSql());
    $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());
});

test('union alls', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('id', '=', 1);
    $builder->unionAll(queryBuilderGetBuilder()->select('*')->from('users')->where('id', '=', 2));
    $this->assertSame('(select * from "users" where "id" = ?) union all (select * from "users" where "id" = ?)', $builder->toSql());
    $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());

    $expectedSql = '(select * from "users" where "id" = ?) union all (select * from "users" where "id" = ?)';
    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('*')->from('users')->where('id', '=', 1);
    $builder->unionAll(queryBuilderGetBuilder()->select('*')->from('users')->where('id', '=', 2));
    $this->assertEquals($expectedSql, $builder->toSql());
    $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $instrumentBuilder = new InstrumentBuilder(queryBuilderGetBuilder());
    $builder->select('*')->from('users')->where('id', '=', 1);
    $builder->unionAll($instrumentBuilder->select('*')->from('users')->where('id', '=', 2));
    $this->assertSame('(select * from "users" where "id" = ?) union all (select * from "users" where "id" = ?)', $builder->toSql());
    $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());
});

test('multiple unions', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('id', '=', 1);
    $builder->union(queryBuilderGetBuilder()->select('*')->from('users')->where('id', '=', 2));
    $builder->union(queryBuilderGetBuilder()->select('*')->from('users')->where('id', '=', 3));
    $this->assertSame('(select * from "users" where "id" = ?) union (select * from "users" where "id" = ?) union (select * from "users" where "id" = ?)', $builder->toSql());
    $this->assertEquals([0 => 1, 1 => 2, 2 => 3], $builder->getBindings());
});

test('multiple union alls', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('id', '=', 1);
    $builder->unionAll(queryBuilderGetBuilder()->select('*')->from('users')->where('id', '=', 2));
    $builder->unionAll(queryBuilderGetBuilder()->select('*')->from('users')->where('id', '=', 3));
    $this->assertSame('(select * from "users" where "id" = ?) union all (select * from "users" where "id" = ?) union all (select * from "users" where "id" = ?)', $builder->toSql());
    $this->assertEquals([0 => 1, 1 => 2, 2 => 3], $builder->getBindings());
});

test('union order bys', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('id', '=', 1);
    $builder->union(queryBuilderGetBuilder()->select('*')->from('users')->where('id', '=', 2));
    $builder->orderBy('id', 'desc');
    $this->assertSame('(select * from "users" where "id" = ?) union (select * from "users" where "id" = ?) order by "id" desc', $builder->toSql());
    $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());
});

test('union limits and offsets', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users');
    $builder->union(queryBuilderGetBuilder()->select('*')->from('dogs'));
    $builder->offset(5)->limit(10);
    $this->assertSame('(select * from "users") union (select * from "dogs") limit 10 offset 5', $builder->toSql());

    $expectedSql = '(select * from "users") union (select * from "dogs") limit 10 offset 5';
    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('*')->from('users');
    $builder->union(queryBuilderGetBuilder()->select('*')->from('dogs'));
    $builder->offset(5)->limit(10);
    $this->assertEquals($expectedSql, $builder->toSql());

    $expectedSql = '(select * from "users" limit 11) union (select * from "dogs" limit 22) limit 10 offset 5';
    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('*')->from('users')->limit(11);
    $builder->union(queryBuilderGetBuilder()->select('*')->from('dogs')->limit(22));
    $builder->offset(5)->limit(10);
    $this->assertEquals($expectedSql, $builder->toSql());
});

test('union with join', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users');
    $builder->union(queryBuilderGetBuilder()->select('*')->from('dogs')->join('breeds', function ($join) {
        $join->on('dogs.breed_id', '=', 'breeds.id')
            ->where('breeds.is_native', '=', 1);
    }));
    $this->assertSame('(select * from "users") union (select * from "dogs" inner join "breeds" on "dogs"."breed_id" = "breeds"."id" and "breeds"."is_native" = ?)', $builder->toSql());
    $this->assertEquals([0 => 1], $builder->getBindings());
});

test('my sql union order bys', function () {
    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users')->where('id', '=', 1);
    $builder->union(queryBuilderGetMySqlBuilder()->select('*')->from('users')->where('id', '=', 2));
    $builder->orderBy('id', 'desc');
    $this->assertSame('(select * from `users` where `id` = ?) union (select * from `users` where `id` = ?) order by `id` desc', $builder->toSql());
    $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());
});

test('my sql union limits and offsets', function () {
    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users');
    $builder->union(queryBuilderGetMySqlBuilder()->select('*')->from('dogs'));
    $builder->offset(5)->limit(10);
    $this->assertSame('(select * from `users`) union (select * from `dogs`) limit 10 offset 5', $builder->toSql());
});

test('union aggregate', function () {
    $expected = 'select count(*) as aggregate from ((select * from `posts`) union (select * from `videos`)) as `temp_table`';
    $builder = queryBuilderGetMySqlBuilder();
    $builder->getConnection()->shouldReceive('select')->once()->with($expected, [], true);
    $builder->getProcessor()->shouldReceive('processSelect')->once();
    $builder->from('posts')->union(queryBuilderGetMySqlBuilder()->from('videos'))->count();

    $expected = 'select count(*) as aggregate from ((select `id` from `posts`) union (select `id` from `videos`)) as `temp_table`';
    $builder = queryBuilderGetMySqlBuilder();
    $builder->getConnection()->shouldReceive('select')->once()->with($expected, [], true);
    $builder->getProcessor()->shouldReceive('processSelect')->once();
    $builder->from('posts')->select('id')->union(queryBuilderGetMySqlBuilder()->from('videos')->select('id'))->count();

    $expected = 'select count(*) as aggregate from ((select * from "posts") union (select * from "videos")) as "temp_table"';
    $builder = queryBuilderGetPostgresBuilder();
    $builder->getConnection()->shouldReceive('select')->once()->with($expected, [], true);
    $builder->getProcessor()->shouldReceive('processSelect')->once();
    $builder->from('posts')->union(queryBuilderGetPostgresBuilder()->from('videos'))->count();

    $expected = 'select count(*) as aggregate from (select * from (select * from "posts") union select * from (select * from "videos")) as "temp_table"';
    $builder = queryBuilderGetSQLiteBuilder();
    $builder->getConnection()->shouldReceive('select')->once()->with($expected, [], true);
    $builder->getProcessor()->shouldReceive('processSelect')->once();
    $builder->from('posts')->union(queryBuilderGetSQLiteBuilder()->from('videos'))->count();

    $expected = 'select count(*) as aggregate from (select * from (select * from [posts]) as [temp_table] union select * from (select * from [videos]) as [temp_table]) as [temp_table]';
    $builder = queryBuilderGetSqlServerBuilder();
    $builder->getConnection()->shouldReceive('select')->once()->with($expected, [], true);
    $builder->getProcessor()->shouldReceive('processSelect')->once();
    $builder->from('posts')->union(queryBuilderGetSqlServerBuilder()->from('videos'))->count();
});

test('having aggregate', function () {
    $expected = 'select count(*) as aggregate from (select (select `count(*)` from `videos` where `posts`.`id` = `videos`.`post_id`) as `videos_count` from `posts` having `videos_count` > ?) as `temp_table`';
    $builder = queryBuilderGetMySqlBuilder();
    $builder->getConnection()->shouldReceive('getDatabaseName');
    $builder->getConnection()->shouldReceive('select')->once()->with($expected, [0 => 1], true)->andReturn([['aggregate' => 1]]);
    $builder->getProcessor()->shouldReceive('processSelect')->once()->andReturnUsing(function ($builder, $results) {
        return $results;
    });

    $builder->from('posts')->selectSub(function ($query) {
        $query->from('videos')->select('count(*)')->whereColumn('posts.id', '=', 'videos.post_id');
    }, 'videos_count')->having('videos_count', '>', 1);
    $builder->count();
});

test('sub select where ins', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereIn('id', function ($q) {
        $q->select('id')->from('users')->where('age', '>', 25)->limit(3);
    });
    $this->assertSame('select * from "users" where "id" in (select "id" from "users" where "age" > ? limit 3)', $builder->toSql());
    $this->assertEquals([25], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereNotIn('id', function ($q) {
        $q->select('id')->from('users')->where('age', '>', 25)->limit(3);
    });
    $this->assertSame('select * from "users" where "id" not in (select "id" from "users" where "age" > ? limit 3)', $builder->toSql());
    $this->assertEquals([25], $builder->getBindings());
});

test('basic where nulls', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereNull('id');
    $this->assertSame('select * from "users" where "id" is null', $builder->toSql());
    $this->assertEquals([], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('id', '=', 1)->orWhereNull('id');
    $this->assertSame('select * from "users" where "id" = ? or "id" is null', $builder->toSql());
    $this->assertEquals([0 => 1], $builder->getBindings());
});

test('basic where null expressions mysql', function () {
    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users')->whereNull(new Raw('id'));
    $this->assertSame('select * from `users` where id is null', $builder->toSql());
    $this->assertEquals([], $builder->getBindings());

    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users')->where('id', '=', 1)->orWhereNull(new Raw('id'));
    $this->assertSame('select * from `users` where `id` = ? or id is null', $builder->toSql());
    $this->assertEquals([0 => 1], $builder->getBindings());
});

test('json where null mysql', function () {
    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users')->whereNull('items->id');
    $this->assertSame('select * from `users` where (json_extract(`items`, \'$."id"\') is null OR json_type(json_extract(`items`, \'$."id"\')) = \'NULL\')', $builder->toSql());
});

test('json where not null mysql', function () {
    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users')->whereNotNull('items->id');
    $this->assertSame('select * from `users` where (json_extract(`items`, \'$."id"\') is not null AND json_type(json_extract(`items`, \'$."id"\')) != \'NULL\')', $builder->toSql());
});

test('json where null expression mysql', function () {
    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users')->whereNull(new Raw('items->id'));
    $this->assertSame('select * from `users` where (json_extract(`items`, \'$."id"\') is null OR json_type(json_extract(`items`, \'$."id"\')) = \'NULL\')', $builder->toSql());
});

test('json where not null expression mysql', function () {
    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users')->whereNotNull(new Raw('items->id'));
    $this->assertSame('select * from `users` where (json_extract(`items`, \'$."id"\') is not null AND json_type(json_extract(`items`, \'$."id"\')) != \'NULL\')', $builder->toSql());
});

test('array where nulls', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereNull(['id', 'expires_at']);
    $this->assertSame('select * from "users" where "id" is null and "expires_at" is null', $builder->toSql());
    $this->assertEquals([], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('id', '=', 1)->orWhereNull(['id', 'expires_at']);
    $this->assertSame('select * from "users" where "id" = ? or "id" is null or "expires_at" is null', $builder->toSql());
    $this->assertEquals([0 => 1], $builder->getBindings());
});

test('basic where not nulls', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereNotNull('id');
    $this->assertSame('select * from "users" where "id" is not null', $builder->toSql());
    $this->assertEquals([], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('id', '>', 1)->orWhereNotNull('id');
    $this->assertSame('select * from "users" where "id" > ? or "id" is not null', $builder->toSql());
    $this->assertEquals([0 => 1], $builder->getBindings());
});

test('array where not nulls', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereNotNull(['id', 'expires_at']);
    $this->assertSame('select * from "users" where "id" is not null and "expires_at" is not null', $builder->toSql());
    $this->assertEquals([], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('id', '>', 1)->orWhereNotNull(['id', 'expires_at']);
    $this->assertSame('select * from "users" where "id" > ? or "id" is not null or "expires_at" is not null', $builder->toSql());
    $this->assertEquals([0 => 1], $builder->getBindings());
});

test('group bys', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->groupBy('email');
    $this->assertSame('select * from "users" group by "email"', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->groupBy('id', 'email');
    $this->assertSame('select * from "users" group by "id", "email"', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->groupBy(['id', 'email']);
    $this->assertSame('select * from "users" group by "id", "email"', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->groupBy(new Raw('DATE(created_at)'));
    $this->assertSame('select * from "users" group by DATE(created_at)', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->groupByRaw('DATE(created_at), ? DESC', ['foo']);
    $this->assertSame('select * from "users" group by DATE(created_at), ? DESC', $builder->toSql());
    $this->assertEquals(['foo'], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->havingRaw('?', ['havingRawBinding'])->groupByRaw('?', ['groupByRawBinding'])->whereRaw('?', ['whereRawBinding']);
    $this->assertEquals(['whereRawBinding', 'groupByRawBinding', 'havingRawBinding'], $builder->getBindings());
});

test('order bys', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->orderBy('email')->orderBy('age', 'desc');
    $this->assertSame('select * from "users" order by "email" asc, "age" desc', $builder->toSql());

    $builder->orders = null;
    $this->assertSame('select * from "users"', $builder->toSql());

    $builder->orders = [];
    $this->assertSame('select * from "users"', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->orderBy('email')->orderByRaw('"age" ? desc', ['foo']);
    $this->assertSame('select * from "users" order by "email" asc, "age" ? desc', $builder->toSql());
    $this->assertEquals(['foo'], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->orderByDesc('name');
    $this->assertSame('select * from "users" order by "name" desc', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('posts')->where('public', 1)
        ->unionAll(queryBuilderGetBuilder()->select('*')->from('videos')->where('public', 1))
        ->orderByRaw('field(category, ?, ?) asc', ['news', 'opinion']);
    $this->assertSame('(select * from "posts" where "public" = ?) union all (select * from "videos" where "public" = ?) order by field(category, ?, ?) asc', $builder->toSql());
    $this->assertEquals([1, 1, 'news', 'opinion'], $builder->getBindings());
});

test('latest', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->latest();
    $this->assertSame('select * from "users" order by "created_at" desc', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->latest()->limit(1);
    $this->assertSame('select * from "users" order by "created_at" desc limit 1', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->latest('updated_at');
    $this->assertSame('select * from "users" order by "updated_at" desc', $builder->toSql());
});

test('oldest', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->oldest();
    $this->assertSame('select * from "users" order by "created_at" asc', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->oldest()->limit(1);
    $this->assertSame('select * from "users" order by "created_at" asc limit 1', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->oldest('updated_at');
    $this->assertSame('select * from "users" order by "updated_at" asc', $builder->toSql());
});

test('in random order my sql', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->inRandomOrder();
    $this->assertSame('select * from "users" order by RANDOM()', $builder->toSql());
});

test('in random order my sql grammar without seed', function () {
    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users')->inRandomOrder();
    $this->assertSame('select * from `users` order by RAND()', $builder->toSql());
});

test('in random order my sql grammar with seed', function () {
    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users')->inRandomOrder(123);
    $this->assertSame('select * from `users` order by RAND(123)', $builder->toSql());
});

test('in random order postgres', function () {
    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('*')->from('users')->inRandomOrder();
    $this->assertSame('select * from "users" order by RANDOM()', $builder->toSql());
});

test('in random order sql server', function () {
    $builder = queryBuilderGetSqlServerBuilder();
    $builder->select('*')->from('users')->inRandomOrder();
    $this->assertSame('select * from [users] order by NEWID()', $builder->toSql());
});

test('in order of', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->inOrderOf('status', ['active', 'pending', 'inactive']);
    $this->assertSame('select * from "users" order by case when "status" = ? then 0 when "status" = ? then 1 when "status" = ? then 2 else 3 end', $builder->toSql());
    $this->assertEquals(['active', 'pending', 'inactive'], $builder->getBindings());
});

test('in order of with existing orders', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->inOrderOf('status', ['active', 'pending'])->orderBy('name');
    $this->assertSame('select * from "users" order by case when "status" = ? then 0 when "status" = ? then 1 else 2 end, "name" asc', $builder->toSql());
    $this->assertEquals(['active', 'pending'], $builder->getBindings());
});

test('in order of with empty values', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->inOrderOf('status', []);
    $this->assertSame('select * from "users"', $builder->toSql());
});

test('in order of with single value', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->inOrderOf('status', ['active']);
    $this->assertSame('select * from "users" order by case when "status" = ? then 0 else 1 end', $builder->toSql());
    $this->assertEquals(['active'], $builder->getBindings());
});

test('in order of my sql', function () {
    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users')->inOrderOf('status', ['active', 'pending']);
    $this->assertSame('select * from `users` order by case when `status` = ? then 0 when `status` = ? then 1 else 2 end', $builder->toSql());
    $this->assertEquals(['active', 'pending'], $builder->getBindings());
});

test('in order of postgres', function () {
    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('*')->from('users')->inOrderOf('status', ['active', 'pending']);
    $this->assertSame('select * from "users" order by case when "status" = ? then 0 when "status" = ? then 1 else 2 end', $builder->toSql());
    $this->assertEquals(['active', 'pending'], $builder->getBindings());
});

test('in order of sql server', function () {
    $builder = queryBuilderGetSqlServerBuilder();
    $builder->select('*')->from('users')->inOrderOf('status', ['active', 'pending']);
    $this->assertSame('select * from [users] order by case when [status] = ? then 0 when [status] = ? then 1 else 2 end', $builder->toSql());
    $this->assertEquals(['active', 'pending'], $builder->getBindings());
});

test('in order of with integer values', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->inOrderOf('id', [5, 2, 8]);
    $this->assertSame('select * from "users" order by case when "id" = ? then 0 when "id" = ? then 1 when "id" = ? then 2 else 3 end', $builder->toSql());
    $this->assertEquals([5, 2, 8], $builder->getBindings());
});

test('in order of with where clause', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('active', true)->inOrderOf('status', ['pending', 'approved']);
    $this->assertSame('select * from "users" where "active" = ? order by case when "status" = ? then 0 when "status" = ? then 1 else 2 end', $builder->toSql());
    $this->assertEquals([true, 'pending', 'approved'], $builder->getBindings());
});

test('order bys sql server', function () {
    $builder = queryBuilderGetSqlServerBuilder();
    $builder->select('*')->from('users')->orderBy('email')->orderBy('age', 'desc');
    $this->assertSame('select * from [users] order by [email] asc, [age] desc', $builder->toSql());

    $builder->orders = null;
    $this->assertSame('select * from [users]', $builder->toSql());

    $builder->orders = [];
    $this->assertSame('select * from [users]', $builder->toSql());

    $builder = queryBuilderGetSqlServerBuilder();
    $builder->select('*')->from('users')->orderBy('email');
    $this->assertSame('select * from [users] order by [email] asc', $builder->toSql());

    $builder = queryBuilderGetSqlServerBuilder();
    $builder->select('*')->from('users')->orderByDesc('name');
    $this->assertSame('select * from [users] order by [name] desc', $builder->toSql());

    $builder = queryBuilderGetSqlServerBuilder();
    $builder->select('*')->from('users')->orderByRaw('[age] asc');
    $this->assertSame('select * from [users] order by [age] asc', $builder->toSql());

    $builder = queryBuilderGetSqlServerBuilder();
    $builder->select('*')->from('users')->orderBy('email')->orderByRaw('[age] ? desc', ['foo']);
    $this->assertSame('select * from [users] order by [email] asc, [age] ? desc', $builder->toSql());
    $this->assertEquals(['foo'], $builder->getBindings());

    $builder = queryBuilderGetSqlServerBuilder();
    $builder->select('*')->from('users')->offset(25)->limit(10)->orderByRaw('[email] desc');
    $this->assertSame('select * from [users] order by [email] desc offset 25 rows fetch next 10 rows only', $builder->toSql());
});

test('reorder', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->orderBy('name');
    $this->assertSame('select * from "users" order by "name" asc', $builder->toSql());
    $builder->reorder();
    $this->assertSame('select * from "users"', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->orderBy('name');
    $this->assertSame('select * from "users" order by "name" asc', $builder->toSql());
    $builder->reorder('email', 'desc');
    $this->assertSame('select * from "users" order by "email" desc', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('first');
    $builder->union(queryBuilderGetBuilder()->select('*')->from('second'));
    $builder->orderBy('name');
    $this->assertSame('(select * from "first") union (select * from "second") order by "name" asc', $builder->toSql());
    $builder->reorder();
    $this->assertSame('(select * from "first") union (select * from "second")', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->orderByRaw('?', [true]);
    $this->assertEquals([true], $builder->getBindings());
    $builder->reorder();
    $this->assertEquals([], $builder->getBindings());
});

test('order by sub queries', function () {
    $expected = 'select * from "users" order by (select "created_at" from "logins" where "user_id" = "users"."id" limit 1)';
    $subQuery = function ($query) {
        return $query->select('created_at')->from('logins')->whereColumn('user_id', 'users.id')->limit(1);
    };

    $builder = queryBuilderGetBuilder()->select('*')->from('users')->orderBy($subQuery);
    $this->assertSame("$expected asc", $builder->toSql());

    $builder = queryBuilderGetBuilder()->select('*')->from('users')->orderBy($subQuery, 'desc');
    $this->assertSame("$expected desc", $builder->toSql());

    $builder = queryBuilderGetBuilder()->select('*')->from('users')->orderByDesc($subQuery);
    $this->assertSame("$expected desc", $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('posts')->where('public', 1)
        ->unionAll(queryBuilderGetBuilder()->select('*')->from('videos')->where('public', 1))
        ->orderBy(queryBuilderGetBuilder()->selectRaw('field(category, ?, ?)', ['news', 'opinion']));
    $this->assertSame('(select * from "posts" where "public" = ?) union all (select * from "videos" where "public" = ?) order by (select field(category, ?, ?)) asc', $builder->toSql());
    $this->assertEquals([1, 1, 'news', 'opinion'], $builder->getBindings());
});

test('order by invalid direction param', function () {

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->orderBy('age', 'asec');
})->throws(InvalidArgumentException::class);

test('havings', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->having('email', '>', 1);
    $this->assertSame('select * from "users" having "email" > ?', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')
        ->orHaving('email', '=', 'test@example.com')
        ->orHaving('email', '=', 'test2@example.com');
    $this->assertSame('select * from "users" having "email" = ? or "email" = ?', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->groupBy('email')->having('email', '>', 1);
    $this->assertSame('select * from "users" group by "email" having "email" > ?', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select('email as foo_email')->from('users')->having('foo_email', '>', 1);
    $this->assertSame('select "email" as "foo_email" from "users" having "foo_email" > ?', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select(['category', new Raw('count(*) as "total"')])->from('item')->where('department', '=', 'popular')->groupBy('category')->having('total', '>', new Raw('3'));
    $this->assertSame('select "category", count(*) as "total" from "item" where "department" = ? group by "category" having "total" > 3', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select(['category', new Raw('count(*) as "total"')])->from('item')->where('department', '=', 'popular')->groupBy('category')->having('total', '>', 3);
    $this->assertSame('select "category", count(*) as "total" from "item" where "department" = ? group by "category" having "total" > ?', $builder->toSql());
});

test('nested havings', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->having('email', '=', 'foo')->orHaving(function ($q) {
        $q->having('name', '=', 'bar')->having('age', '=', 25);
    });
    $this->assertSame('select * from "users" having "email" = ? or ("name" = ? and "age" = ?)', $builder->toSql());
    $this->assertEquals([0 => 'foo', 1 => 'bar', 2 => 25], $builder->getBindings());
});

test('nested having bindings', function () {
    $builder = queryBuilderGetBuilder();
    $builder->having('email', '=', 'foo')->having(function ($q) {
        $q->selectRaw('?', ['ignore'])->having('name', '=', 'bar');
    });
    $this->assertEquals([0 => 'foo', 1 => 'bar'], $builder->getBindings());
});

test('having betweens', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->havingBetween('id', [1, 2, 3]);
    $this->assertSame('select * from "users" having "id" between ? and ?', $builder->toSql());
    $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->havingBetween('id', [[1, 2], [3, 4]]);
    $this->assertSame('select * from "users" having "id" between ? and ?', $builder->toSql());
    $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());
});

test('having null', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->havingNull('email');
    $this->assertSame('select * from "users" having "email" is null', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')
        ->havingNull('email')
        ->havingNull('phone');
    $this->assertSame('select * from "users" having "email" is null and "phone" is null', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')
        ->orHavingNull('email')
        ->orHavingNull('phone');
    $this->assertSame('select * from "users" having "email" is null or "phone" is null', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->groupBy('email')->havingNull('email');
    $this->assertSame('select * from "users" group by "email" having "email" is null', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select('email as foo_email')->from('users')->havingNull('foo_email');
    $this->assertSame('select "email" as "foo_email" from "users" having "foo_email" is null', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select(['category', new Raw('count(*) as "total"')])->from('item')->where('department', '=', 'popular')->groupBy('category')->havingNull('total');
    $this->assertSame('select "category", count(*) as "total" from "item" where "department" = ? group by "category" having "total" is null', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select(['category', new Raw('count(*) as "total"')])->from('item')->where('department', '=', 'popular')->groupBy('category')->havingNull('total');
    $this->assertSame('select "category", count(*) as "total" from "item" where "department" = ? group by "category" having "total" is null', $builder->toSql());
});

test('having not null', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->havingNotNull('email');
    $this->assertSame('select * from "users" having "email" is not null', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')
        ->havingNotNull('email')
        ->havingNotNull('phone');
    $this->assertSame('select * from "users" having "email" is not null and "phone" is not null', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')
        ->orHavingNotNull('email')
        ->orHavingNotNull('phone');
    $this->assertSame('select * from "users" having "email" is not null or "phone" is not null', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->groupBy('email')->havingNotNull('email');
    $this->assertSame('select * from "users" group by "email" having "email" is not null', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select('email as foo_email')->from('users')->havingNotNull('foo_email');
    $this->assertSame('select "email" as "foo_email" from "users" having "foo_email" is not null', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select(['category', new Raw('count(*) as "total"')])->from('item')->where('department', '=', 'popular')->groupBy('category')->havingNotNull('total');
    $this->assertSame('select "category", count(*) as "total" from "item" where "department" = ? group by "category" having "total" is not null', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select(['category', new Raw('count(*) as "total"')])->from('item')->where('department', '=', 'popular')->groupBy('category')->havingNotNull('total');
    $this->assertSame('select "category", count(*) as "total" from "item" where "department" = ? group by "category" having "total" is not null', $builder->toSql());
});

test('having expression', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->having(
        new class() implements ConditionExpression
        {
            public function getValue(\Voyager\Database\Grammar $grammar)
            {
                return '1 = 1';
            }
        }
    );
    $this->assertSame('select * from "users" having 1 = 1', $builder->toSql());
    $this->assertSame([], $builder->getBindings());
});

test('having shortcut', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->having('email', 1)->orHaving('email', 2);
    $this->assertSame('select * from "users" having "email" = ? or "email" = ?', $builder->toSql());
});

test('having followed by select get', function () {
    $builder = queryBuilderGetBuilder();
    $query = 'select "category", count(*) as "total" from "item" where "department" = ? group by "category" having "total" > ?';
    $builder->getConnection()->shouldReceive('select')->once()->with($query, ['popular', 3], true)->andReturn([['category' => 'rock', 'total' => 5]]);
    $builder->getProcessor()->shouldReceive('processSelect')->andReturnUsing(function ($builder, $results) {
        return $results;
    });
    $builder->from('item');
    $result = $builder->select(['category', new Raw('count(*) as "total"')])->where('department', '=', 'popular')->groupBy('category')->having('total', '>', 3)->get();
    $this->assertEquals([['category' => 'rock', 'total' => 5]], $result->all());

    // Using \Raw value
    $builder = queryBuilderGetBuilder();
    $query = 'select "category", count(*) as "total" from "item" where "department" = ? group by "category" having "total" > 3';
    $builder->getConnection()->shouldReceive('select')->once()->with($query, ['popular'], true)->andReturn([['category' => 'rock', 'total' => 5]]);
    $builder->getProcessor()->shouldReceive('processSelect')->andReturnUsing(function ($builder, $results) {
        return $results;
    });
    $builder->from('item');
    $result = $builder->select(['category', new Raw('count(*) as "total"')])->where('department', '=', 'popular')->groupBy('category')->having('total', '>', new Raw('3'))->get();
    $this->assertEquals([['category' => 'rock', 'total' => 5]], $result->all());
});

test('raw havings', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->havingRaw('user_foo < user_bar');
    $this->assertSame('select * from "users" having user_foo < user_bar', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->having('baz', '=', 1)->orHavingRaw('user_foo < user_bar');
    $this->assertSame('select * from "users" having "baz" = ? or user_foo < user_bar', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->havingBetween('last_login_date', ['2018-11-16', '2018-12-16'])->orHavingRaw('user_foo < user_bar');
    $this->assertSame('select * from "users" having "last_login_date" between ? and ? or user_foo < user_bar', $builder->toSql());
});

test('limits and offsets', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->offset(5)->limit(10);
    $this->assertSame('select * from "users" limit 10 offset 5', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->limit(null);
    $this->assertSame('select * from "users"', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->limit(0);
    $this->assertSame('select * from "users" limit 0', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->offset(5)->limit(10);
    $this->assertSame('select * from "users" limit 10 offset 5', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->offset(0)->limit(0);
    $this->assertSame('select * from "users" limit 0 offset 0', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->offset(-5)->limit(-10);
    $this->assertSame('select * from "users" offset 0', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->offset(null)->limit(null);
    $this->assertSame('select * from "users" offset 0', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->offset(5)->limit(null);
    $this->assertSame('select * from "users" offset 5', $builder->toSql());
});

test('for page', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->forPage(2, 15);
    $this->assertSame('select * from "users" limit 15 offset 15', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->forPage(0, 15);
    $this->assertSame('select * from "users" limit 15 offset 0', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->forPage(-2, 15);
    $this->assertSame('select * from "users" limit 15 offset 0', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->forPage(2, 0);
    $this->assertSame('select * from "users" limit 0 offset 0', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->forPage(0, 0);
    $this->assertSame('select * from "users" limit 0 offset 0', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->forPage(-2, 0);
    $this->assertSame('select * from "users" limit 0 offset 0', $builder->toSql());
});

test('for page before id', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->forPageBeforeId(15, null);
    $this->assertSame('select * from "users" where "id" is not null order by "id" desc limit 15', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->forPageBeforeId(15, 0);
    $this->assertSame('select * from "users" where "id" < ? order by "id" desc limit 15', $builder->toSql());
});

test('for page after id', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->forPageAfterId(15, null);
    $this->assertSame('select * from "users" where "id" is not null order by "id" asc limit 15', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->forPageAfterId(15, 0);
    $this->assertSame('select * from "users" where "id" > ? order by "id" asc limit 15', $builder->toSql());
});

test('get count for pagination with bindings', function () {
    $builder = queryBuilderGetBuilder();
    $builder->from('users')->selectSub(function ($q) {
        $q->select('body')->from('posts')->where('id', 4);
    }, 'post');

    $builder->getConnection()->shouldReceive('select')->once()->with('select count(*) as aggregate from "users"', [], true)->andReturn([['aggregate' => 1]]);
    $builder->getProcessor()->shouldReceive('processSelect')->once()->andReturnUsing(function ($builder, $results) {
        return $results;
    });

    $count = $builder->getCountForPagination();
    $this->assertEquals(1, $count);
    $this->assertEquals([4], $builder->getBindings());
});

test('get count for pagination with column aliases', function () {
    $builder = queryBuilderGetBuilder();
    $columns = ['body as post_body', 'teaser', 'posts.created as published'];
    $builder->from('posts')->select($columns);

    $builder->getConnection()->shouldReceive('select')->once()->with('select count("body", "teaser", "posts"."created") as aggregate from "posts"', [], true)->andReturn([['aggregate' => 1]]);
    $builder->getProcessor()->shouldReceive('processSelect')->once()->andReturnUsing(function ($builder, $results) {
        return $results;
    });

    $count = $builder->getCountForPagination($columns);
    $this->assertEquals(1, $count);
});

test('get count for pagination with union', function () {
    $builder = queryBuilderGetBuilder();
    $builder->from('posts')->select('id')->union(queryBuilderGetBuilder()->from('videos')->select('id'));

    $builder->getConnection()->shouldReceive('select')->once()->with('select count(*) as aggregate from ((select "id" from "posts") union (select "id" from "videos")) as "temp_table"', [], true)->andReturn([['aggregate' => 1]]);
    $builder->getProcessor()->shouldReceive('processSelect')->once()->andReturnUsing(function ($builder, $results) {
        return $results;
    });

    $count = $builder->getCountForPagination();
    $this->assertEquals(1, $count);
});

test('get count for pagination with union orders', function () {
    $builder = queryBuilderGetBuilder();
    $builder->from('posts')->select('id')->union(queryBuilderGetBuilder()->from('videos')->select('id'))->latest();

    $builder->getConnection()->shouldReceive('select')->once()->with('select count(*) as aggregate from ((select "id" from "posts") union (select "id" from "videos")) as "temp_table"', [], true)->andReturn([['aggregate' => 1]]);
    $builder->getProcessor()->shouldReceive('processSelect')->once()->andReturnUsing(function ($builder, $results) {
        return $results;
    });

    $count = $builder->getCountForPagination();
    $this->assertEquals(1, $count);
});

test('get count for pagination with union limit and offset', function () {
    $builder = queryBuilderGetBuilder();
    $builder->from('posts')->select('id')->union(queryBuilderGetBuilder()->from('videos')->select('id'))->limit(15)->offset(1);

    $builder->getConnection()->shouldReceive('select')->once()->with('select count(*) as aggregate from ((select "id" from "posts") union (select "id" from "videos")) as "temp_table"', [], true)->andReturn([['aggregate' => 1]]);
    $builder->getProcessor()->shouldReceive('processSelect')->once()->andReturnUsing(function ($builder, $results) {
        return $results;
    });

    $count = $builder->getCountForPagination();
    $this->assertEquals(1, $count);
});

test('where shortcut', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('id', 1)->orWhere('name', 'foo');
    $this->assertSame('select * from "users" where "id" = ? or "name" = ?', $builder->toSql());
    $this->assertEquals([0 => 1, 1 => 'foo'], $builder->getBindings());
});

test('or wheres have consistent results', function () {
    $queries = [];
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('xxxx', 'xxxx')->orWhere(['foo' => 1, 'bar' => 2]);
    $queries[] = $builder->toSql();

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('xxxx', 'xxxx')->orWhere([['foo', 1], ['bar', 2]]);
    $queries[] = $builder->toSql();

    $this->assertSame([
        'select * from "users" where "xxxx" = ? or ("foo" = ? or "bar" = ?)',
        'select * from "users" where "xxxx" = ? or ("foo" = ? or "bar" = ?)',
    ], $queries);

    $queries = [];
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('xxxx', 'xxxx')->orWhereColumn(['foo' => '_foo', 'bar' => '_bar']);
    $queries[] = $builder->toSql();

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('xxxx', 'xxxx')->orWhereColumn([['foo', '_foo'], ['bar', '_bar']]);
    $queries[] = $builder->toSql();

    $this->assertSame([
        'select * from "users" where "xxxx" = ? or ("foo" = "_foo" or "bar" = "_bar")',
        'select * from "users" where "xxxx" = ? or ("foo" = "_foo" or "bar" = "_bar")',
    ], $queries);
});

test('where with array conditions', function () {
    // where(key, value)

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where([['foo', 1], ['bar', 2]]);
    $this->assertSame('select * from "users" where ("foo" = ? and "bar" = ?)', $builder->toSql());
    $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where([['foo', 1], ['bar', 2]], boolean: 'or');
    $this->assertSame('select * from "users" where ("foo" = ? or "bar" = ?)', $builder->toSql());
    $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where([['foo', 1], ['bar', 2]], boolean: 'and');
    $this->assertSame('select * from "users" where ("foo" = ? and "bar" = ?)', $builder->toSql());
    $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where(['foo' => 1, 'bar' => 2]);
    $this->assertSame('select * from "users" where ("foo" = ? and "bar" = ?)', $builder->toSql());
    $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where(['foo' => 1, 'bar' => 2], boolean: 'or');
    $this->assertSame('select * from "users" where ("foo" = ? or "bar" = ?)', $builder->toSql());
    $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where(['foo' => 1, 'bar' => 2], boolean: 'and');
    $this->assertSame('select * from "users" where ("foo" = ? and "bar" = ?)', $builder->toSql());
    $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());

    // where(key, <, value)

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where([['foo', 1], ['bar', '<', 2]]);
    $this->assertSame('select * from "users" where ("foo" = ? and "bar" < ?)', $builder->toSql());
    $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where([['foo', 1], ['bar', '<', 2]], boolean: 'or');
    $this->assertSame('select * from "users" where ("foo" = ? or "bar" < ?)', $builder->toSql());
    $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where([['foo', 1], ['bar', '<', 2]], boolean: 'and');
    $this->assertSame('select * from "users" where ("foo" = ? and "bar" < ?)', $builder->toSql());
    $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());

    // whereNot(key, value)

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereNot([['foo', 1], ['bar', 2]]);
    $this->assertSame('select * from "users" where not (("foo" = ? and "bar" = ?))', $builder->toSql());
    $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereNot([['foo', 1], ['bar', 2]], boolean: 'or');
    $this->assertSame('select * from "users" where not (("foo" = ? or "bar" = ?))', $builder->toSql());
    $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereNot([['foo', 1], ['bar', 2]], boolean: 'and');
    $this->assertSame('select * from "users" where not (("foo" = ? and "bar" = ?))', $builder->toSql());
    $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereNot(['foo' => 1, 'bar' => 2]);
    $this->assertSame('select * from "users" where not (("foo" = ? and "bar" = ?))', $builder->toSql());
    $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereNot(['foo' => 1, 'bar' => 2], boolean: 'or');
    $this->assertSame('select * from "users" where not (("foo" = ? or "bar" = ?))', $builder->toSql());
    $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereNot(['foo' => 1, 'bar' => 2], boolean: 'and');
    $this->assertSame('select * from "users" where not (("foo" = ? and "bar" = ?))', $builder->toSql());
    $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());

    // whereNot(key, <, value)

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereNot([['foo', 1], ['bar', '<', 2]]);
    $this->assertSame('select * from "users" where not (("foo" = ? and "bar" < ?))', $builder->toSql());
    $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereNot([['foo', 1], ['bar', '<', 2]], boolean: 'or');
    $this->assertSame('select * from "users" where not (("foo" = ? or "bar" < ?))', $builder->toSql());
    $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereNot([['foo', 1], ['bar', '<', 2]], boolean: 'and');
    $this->assertSame('select * from "users" where not (("foo" = ? and "bar" < ?))', $builder->toSql());
    $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());

    // whereColumn(col1, col2)

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereColumn([['foo', '_foo'], ['bar', '_bar']]);
    $this->assertSame('select * from "users" where ("foo" = "_foo" and "bar" = "_bar")', $builder->toSql());
    $this->assertEquals([], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereColumn([['foo', '_foo'], ['bar', '_bar']], boolean: 'or');
    $this->assertSame('select * from "users" where ("foo" = "_foo" or "bar" = "_bar")', $builder->toSql());
    $this->assertEquals([], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereColumn([['foo', '_foo'], ['bar', '_bar']], boolean: 'and');
    $this->assertSame('select * from "users" where ("foo" = "_foo" and "bar" = "_bar")', $builder->toSql());
    $this->assertEquals([], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereColumn(['foo' => '_foo', 'bar' => '_bar']);
    $this->assertSame('select * from "users" where ("foo" = "_foo" and "bar" = "_bar")', $builder->toSql());
    $this->assertEquals([], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereColumn(['foo' => '_foo', 'bar' => '_bar'], boolean: 'or');
    $this->assertSame('select * from "users" where ("foo" = "_foo" or "bar" = "_bar")', $builder->toSql());
    $this->assertEquals([], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereColumn(['foo' => '_foo', 'bar' => '_bar'], boolean: 'and');
    $this->assertSame('select * from "users" where ("foo" = "_foo" and "bar" = "_bar")', $builder->toSql());
    $this->assertEquals([], $builder->getBindings());

    // whereColumn(col1, <, col2)

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereColumn([['foo', '_foo'], ['bar', '<', '_bar']]);
    $this->assertSame('select * from "users" where ("foo" = "_foo" and "bar" < "_bar")', $builder->toSql());
    $this->assertEquals([], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereColumn([['foo', '_foo'], ['bar', '<', '_bar']], boolean: 'or');
    $this->assertSame('select * from "users" where ("foo" = "_foo" or "bar" < "_bar")', $builder->toSql());
    $this->assertEquals([], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereColumn([['foo', '_foo'], ['bar', '<', '_bar']], boolean: 'and');
    $this->assertSame('select * from "users" where ("foo" = "_foo" and "bar" < "_bar")', $builder->toSql());
    $this->assertEquals([], $builder->getBindings());

    // whereAll([...keys], value)

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereAll(['foo', 'bar'], 2);
    $this->assertSame('select * from "users" where ("foo" = ? and "bar" = ?)', $builder->toSql());
    $this->assertEquals([0 => 2, 1 => 2], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereAll(['foo', 'bar'], 2);
    $this->assertSame('select * from "users" where ("foo" = ? and "bar" = ?)', $builder->toSql());
    $this->assertEquals([0 => 2, 1 => 2], $builder->getBindings());

    // whereAny([...keys], value)

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereAny(['foo', 'bar'], 2);
    $this->assertSame('select * from "users" where ("foo" = ? or "bar" = ?)', $builder->toSql());
    $this->assertEquals([0 => 2, 1 => 2], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereAny(['foo', 'bar'], 2);
    $this->assertSame('select * from "users" where ("foo" = ? or "bar" = ?)', $builder->toSql());
    $this->assertEquals([0 => 2, 1 => 2], $builder->getBindings());

    // whereNone([...keys], value)

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereNone(['foo', 'bar'], 2);
    $this->assertSame('select * from "users" where not ("foo" = ? or "bar" = ?)', $builder->toSql());
    $this->assertEquals([0 => 2, 1 => 2], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereNone(['foo', 'bar'], 2);
    $this->assertSame('select * from "users" where not ("foo" = ? or "bar" = ?)', $builder->toSql());
    $this->assertEquals([0 => 2, 1 => 2], $builder->getBindings());

    // where()->orWhere(key, value)

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('xxxx', 'xxxx')->orWhere([['foo', 1], ['bar', 2]]);
    $this->assertSame('select * from "users" where "xxxx" = ? or ("foo" = ? or "bar" = ?)', $builder->toSql());
    $this->assertEquals([0 => 'xxxx', 1 => 1, 2 => 2], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('xxxx', 'xxxx')->orWhere(['foo' => 1, 'bar' => 2]);
    $this->assertSame('select * from "users" where "xxxx" = ? or ("foo" = ? or "bar" = ?)', $builder->toSql());
    $this->assertEquals([0 => 'xxxx', 1 => 1, 2 => 2], $builder->getBindings());

    // where()->orWhere(key, <, value)

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('xxxx', 'xxxx')->orWhere([['foo', 1], ['bar', '<', 2]]);
    $this->assertSame('select * from "users" where "xxxx" = ? or ("foo" = ? or "bar" < ?)', $builder->toSql());
    $this->assertEquals([0 => 'xxxx', 1 => 1, 2 => 2], $builder->getBindings());

    // where()->orWhereColumn(col1, col2)

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('xxxx', 'xxxx')->orWhereColumn([['foo', '_foo'], ['bar', '_bar']]);
    $this->assertSame('select * from "users" where "xxxx" = ? or ("foo" = "_foo" or "bar" = "_bar")', $builder->toSql());
    $this->assertEquals([0 => 'xxxx'], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('xxxx', 'xxxx')->orWhereColumn(['foo' => '_foo', 'bar' => '_bar']);
    $this->assertSame('select * from "users" where "xxxx" = ? or ("foo" = "_foo" or "bar" = "_bar")', $builder->toSql());
    $this->assertEquals([0 => 'xxxx'], $builder->getBindings());

    // where()->orWhere(key, <, value)

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('xxxx', 'xxxx')->orWhere([['foo', 1], ['bar', '<', 2]]);
    $this->assertSame('select * from "users" where "xxxx" = ? or ("foo" = ? or "bar" < ?)', $builder->toSql());
    $this->assertEquals([0 => 'xxxx', 1 => 1, 2 => 2], $builder->getBindings());

    // where()->orWhereNot(key, value)

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('xxxx', 'xxxx')->orWhereNot([['foo', 1], ['bar', 2]]);
    $this->assertSame('select * from "users" where "xxxx" = ? or not (("foo" = ? or "bar" = ?))', $builder->toSql());
    $this->assertEquals([0 => 'xxxx', 1 => 1, 2 => 2], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('xxxx', 'xxxx')->orWhereNot(['foo' => 1, 'bar' => 2]);
    $this->assertSame('select * from "users" where "xxxx" = ? or not (("foo" = ? or "bar" = ?))', $builder->toSql());
    $this->assertEquals([0 => 'xxxx', 1 => 1, 2 => 2], $builder->getBindings());

    // where()->orWhereNot(key, <, value)

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('xxxx', 'xxxx')->orWhereNot([['foo', 1], ['bar', '<', 2]]);
    $this->assertSame('select * from "users" where "xxxx" = ? or not (("foo" = ? or "bar" < ?))', $builder->toSql());
    $this->assertEquals([0 => 'xxxx', 1 => 1, 2 => 2], $builder->getBindings());

    // where()->orWhereAll([...keys], value)

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('xxxx', 'xxxx')->orWhereAll(['foo', 'bar'], 2);
    $this->assertSame('select * from "users" where "xxxx" = ? or ("foo" = ? and "bar" = ?)', $builder->toSql());
    $this->assertEquals([0 => 'xxxx', 1 => 2, 2 => 2], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('xxxx', 'xxxx')->orWhereAll(['foo', 'bar'], 2);
    $this->assertSame('select * from "users" where "xxxx" = ? or ("foo" = ? and "bar" = ?)', $builder->toSql());
    $this->assertEquals([0 => 'xxxx', 1 => 2, 2 => 2], $builder->getBindings());

    // where()->orWhereAny([...keys], value)

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('xxxx', 'xxxx')->orWhereAny(['foo', 'bar'], 2);
    $this->assertSame('select * from "users" where "xxxx" = ? or ("foo" = ? or "bar" = ?)', $builder->toSql());
    $this->assertEquals([0 => 'xxxx', 1 => 2, 2 => 2], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('xxxx', 'xxxx')->orWhereAny(['foo', 'bar'], 2);
    $this->assertSame('select * from "users" where "xxxx" = ? or ("foo" = ? or "bar" = ?)', $builder->toSql());
    $this->assertEquals([0 => 'xxxx', 1 => 2, 2 => 2], $builder->getBindings());

    // where()->orWhereNone([...keys], value)

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('xxxx', 'xxxx')->orWhereNone(['foo', 'bar'], 2);
    $this->assertSame('select * from "users" where "xxxx" = ? or not ("foo" = ? or "bar" = ?)', $builder->toSql());
    $this->assertEquals([0 => 'xxxx', 1 => 2, 2 => 2], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('xxxx', 'xxxx')->orWhereNone(['foo', 'bar'], 2);
    $this->assertSame('select * from "users" where "xxxx" = ? or not ("foo" = ? or "bar" = ?)', $builder->toSql());
    $this->assertEquals([0 => 'xxxx', 1 => 2, 2 => 2], $builder->getBindings());
});

test('nested wheres', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('email', '=', 'foo')->orWhere(function ($q) {
        $q->where('name', '=', 'bar')->where('age', '=', 25);
    });
    $this->assertSame('select * from "users" where "email" = ? or ("name" = ? and "age" = ?)', $builder->toSql());
    $this->assertEquals([0 => 'foo', 1 => 'bar', 2 => 25], $builder->getBindings());
});

test('nested where bindings', function () {
    $builder = queryBuilderGetBuilder();
    $builder->where('email', '=', 'foo')->where(function ($q) {
        $q->selectRaw('?', ['ignore'])->where('name', '=', 'bar');
    });
    $this->assertEquals([0 => 'foo', 1 => 'bar'], $builder->getBindings());
});

test('where not', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereNot(function ($q) {
        $q->where('email', '=', 'foo');
    });
    $this->assertSame('select * from "users" where not ("email" = ?)', $builder->toSql());
    $this->assertEquals([0 => 'foo'], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('name', '=', 'bar')->whereNot(function ($q) {
        $q->where('email', '=', 'foo');
    });
    $this->assertSame('select * from "users" where "name" = ? and not ("email" = ?)', $builder->toSql());
    $this->assertEquals([0 => 'bar', 1 => 'foo'], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('name', '=', 'bar')->orWhereNot(function ($q) {
        $q->where('email', '=', 'foo');
    });
    $this->assertSame('select * from "users" where "name" = ? or not ("email" = ?)', $builder->toSql());
    $this->assertEquals([0 => 'bar', 1 => 'foo'], $builder->getBindings());
});

test('increment many argument validation1', function () {
    $builder = queryBuilderGetBuilder();
    $builder->from('users')->incrementEach(['col' => 'a']);
})->throws(InvalidArgumentException::class, 'Non-numeric value passed as increment amount for column: \'col\'.');

test('increment many argument validation2', function () {
    $builder = queryBuilderGetBuilder();
    $builder->from('users')->incrementEach([11 => 11]);
})->throws(InvalidArgumentException::class, 'Non-associative array passed to incrementEach method.');

test('where not with array conditions', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereNot([['foo', 1], ['bar', 2]]);
    $this->assertSame('select * from "users" where not (("foo" = ? and "bar" = ?))', $builder->toSql());
    $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereNot(['foo' => 1, 'bar' => 2]);
    $this->assertSame('select * from "users" where not (("foo" = ? and "bar" = ?))', $builder->toSql());
    $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->whereNot([['foo', 1], ['bar', '<', 2]]);
    $this->assertSame('select * from "users" where not (("foo" = ? and "bar" < ?))', $builder->toSql());
    $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());
});

test('full sub selects', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('email', '=', 'foo')->orWhere('id', '=', function ($q) {
        $q->select(new Raw('max(id)'))->from('users')->where('email', '=', 'bar');
    });

    $this->assertSame('select * from "users" where "email" = ? or "id" = (select max(id) from "users" where "email" = ?)', $builder->toSql());
    $this->assertEquals([0 => 'foo', 1 => 'bar'], $builder->getBindings());
});

test('where exists', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('orders')->whereExists(function ($q) {
        $q->select('*')->from('products')->where('products.id', '=', new Raw('"orders"."id"'));
    });
    $this->assertSame('select * from "orders" where exists (select * from "products" where "products"."id" = "orders"."id")', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('orders')->whereNotExists(function ($q) {
        $q->select('*')->from('products')->where('products.id', '=', new Raw('"orders"."id"'));
    });
    $this->assertSame('select * from "orders" where not exists (select * from "products" where "products"."id" = "orders"."id")', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('orders')->where('id', '=', 1)->orWhereExists(function ($q) {
        $q->select('*')->from('products')->where('products.id', '=', new Raw('"orders"."id"'));
    });
    $this->assertSame('select * from "orders" where "id" = ? or exists (select * from "products" where "products"."id" = "orders"."id")', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('orders')->where('id', '=', 1)->orWhereNotExists(function ($q) {
        $q->select('*')->from('products')->where('products.id', '=', new Raw('"orders"."id"'));
    });
    $this->assertSame('select * from "orders" where "id" = ? or not exists (select * from "products" where "products"."id" = "orders"."id")', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('orders')->whereExists(
        queryBuilderGetBuilder()->select('*')->from('products')->where('products.id', '=', new Raw('"orders"."id"'))
    );
    $this->assertSame('select * from "orders" where exists (select * from "products" where "products"."id" = "orders"."id")', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('orders')->whereNotExists(
        queryBuilderGetBuilder()->select('*')->from('products')->where('products.id', '=', new Raw('"orders"."id"'))
    );
    $this->assertSame('select * from "orders" where not exists (select * from "products" where "products"."id" = "orders"."id")', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('orders')->where('id', '=', 1)->orWhereExists(
        queryBuilderGetBuilder()->select('*')->from('products')->where('products.id', '=', new Raw('"orders"."id"'))
    );
    $this->assertSame('select * from "orders" where "id" = ? or exists (select * from "products" where "products"."id" = "orders"."id")', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('orders')->where('id', '=', 1)->orWhereNotExists(
        queryBuilderGetBuilder()->select('*')->from('products')->where('products.id', '=', new Raw('"orders"."id"'))
    );
    $this->assertSame('select * from "orders" where "id" = ? or not exists (select * from "products" where "products"."id" = "orders"."id")', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('orders')->whereExists(
        (new InstrumentBuilder(queryBuilderGetBuilder()))->select('*')->from('products')->where('products.id', '=', new Raw('"orders"."id"'))
    );
    $this->assertSame('select * from "orders" where exists (select * from "products" where "products"."id" = "orders"."id")', $builder->toSql());
});

test('basic joins', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->join('contacts', 'users.id', 'contacts.id');
    $this->assertSame('select * from "users" inner join "contacts" on "users"."id" = "contacts"."id"', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->join('contacts', 'users.id', '=', 'contacts.id')->leftJoin('photos', 'users.id', '=', 'photos.id');
    $this->assertSame('select * from "users" inner join "contacts" on "users"."id" = "contacts"."id" left join "photos" on "users"."id" = "photos"."id"', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->leftJoinWhere('photos', 'users.id', '=', 'bar')->joinWhere('photos', 'users.id', '=', 'foo');
    $this->assertSame('select * from "users" left join "photos" on "users"."id" = ? inner join "photos" on "users"."id" = ?', $builder->toSql());
    $this->assertEquals(['bar', 'foo'], $builder->getBindings());
});

test('cross joins', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('sizes')->crossJoin('colors');
    $this->assertSame('select * from "sizes" cross join "colors"', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('tableB')->join('tableA', 'tableA.column1', '=', 'tableB.column2', 'cross');
    $this->assertSame('select * from "tableB" cross join "tableA" on "tableA"."column1" = "tableB"."column2"', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('tableB')->crossJoin('tableA', 'tableA.column1', '=', 'tableB.column2');
    $this->assertSame('select * from "tableB" cross join "tableA" on "tableA"."column1" = "tableB"."column2"', $builder->toSql());
});

test('cross join subs', function () {
    $builder = queryBuilderGetBuilder();
    $builder->selectRaw('(sale / overall.sales) * 100 AS percent_of_total')->from('sales')->crossJoinSub(queryBuilderGetBuilder()->selectRaw('SUM(sale) AS sales')->from('sales'), 'overall');
    $this->assertSame('select (sale / overall.sales) * 100 AS percent_of_total from "sales" cross join (select SUM(sale) AS sales from "sales") as "overall"', $builder->toSql());
});

test('complex join', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->join('contacts', function ($j) {
        $j->on('users.id', '=', 'contacts.id')->orOn('users.name', '=', 'contacts.name');
    });
    $this->assertSame('select * from "users" inner join "contacts" on "users"."id" = "contacts"."id" or "users"."name" = "contacts"."name"', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->join('contacts', function ($j) {
        $j->where('users.id', '=', 'foo')->orWhere('users.name', '=', 'bar');
    });
    $this->assertSame('select * from "users" inner join "contacts" on "users"."id" = ? or "users"."name" = ?', $builder->toSql());
    $this->assertEquals(['foo', 'bar'], $builder->getBindings());

    // Run the assertions again
    $this->assertSame('select * from "users" inner join "contacts" on "users"."id" = ? or "users"."name" = ?', $builder->toSql());
    $this->assertEquals(['foo', 'bar'], $builder->getBindings());
});

test('join where null', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->join('contacts', function ($j) {
        $j->on('users.id', '=', 'contacts.id')->whereNull('contacts.deleted_at');
    });
    $this->assertSame('select * from "users" inner join "contacts" on "users"."id" = "contacts"."id" and "contacts"."deleted_at" is null', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->join('contacts', function ($j) {
        $j->on('users.id', '=', 'contacts.id')->orWhereNull('contacts.deleted_at');
    });
    $this->assertSame('select * from "users" inner join "contacts" on "users"."id" = "contacts"."id" or "contacts"."deleted_at" is null', $builder->toSql());
});

test('join where not null', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->join('contacts', function ($j) {
        $j->on('users.id', '=', 'contacts.id')->whereNotNull('contacts.deleted_at');
    });
    $this->assertSame('select * from "users" inner join "contacts" on "users"."id" = "contacts"."id" and "contacts"."deleted_at" is not null', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->join('contacts', function ($j) {
        $j->on('users.id', '=', 'contacts.id')->orWhereNotNull('contacts.deleted_at');
    });
    $this->assertSame('select * from "users" inner join "contacts" on "users"."id" = "contacts"."id" or "contacts"."deleted_at" is not null', $builder->toSql());
});

test('join where in', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->join('contacts', function ($j) {
        $j->on('users.id', '=', 'contacts.id')->whereIn('contacts.name', [48, 'baz', null]);
    });
    $this->assertSame('select * from "users" inner join "contacts" on "users"."id" = "contacts"."id" and "contacts"."name" in (?, ?, ?)', $builder->toSql());
    $this->assertEquals([48, 'baz', null], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->join('contacts', function ($j) {
        $j->on('users.id', '=', 'contacts.id')->orWhereIn('contacts.name', [48, 'baz', null]);
    });
    $this->assertSame('select * from "users" inner join "contacts" on "users"."id" = "contacts"."id" or "contacts"."name" in (?, ?, ?)', $builder->toSql());
    $this->assertEquals([48, 'baz', null], $builder->getBindings());
});

test('join where in subquery', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->join('contacts', function ($j) {
        $q = queryBuilderGetBuilder();
        $q->select('name')->from('contacts')->where('name', 'baz');
        $j->on('users.id', '=', 'contacts.id')->whereIn('contacts.name', $q);
    });
    $this->assertSame('select * from "users" inner join "contacts" on "users"."id" = "contacts"."id" and "contacts"."name" in (select "name" from "contacts" where "name" = ?)', $builder->toSql());
    $this->assertEquals(['baz'], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->join('contacts', function ($j) {
        $q = queryBuilderGetBuilder();
        $q->select('name')->from('contacts')->where('name', 'baz');
        $j->on('users.id', '=', 'contacts.id')->orWhereIn('contacts.name', $q);
    });
    $this->assertSame('select * from "users" inner join "contacts" on "users"."id" = "contacts"."id" or "contacts"."name" in (select "name" from "contacts" where "name" = ?)', $builder->toSql());
    $this->assertEquals(['baz'], $builder->getBindings());
});

test('join where not in', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->join('contacts', function ($j) {
        $j->on('users.id', '=', 'contacts.id')->whereNotIn('contacts.name', [48, 'baz', null]);
    });
    $this->assertSame('select * from "users" inner join "contacts" on "users"."id" = "contacts"."id" and "contacts"."name" not in (?, ?, ?)', $builder->toSql());
    $this->assertEquals([48, 'baz', null], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->join('contacts', function ($j) {
        $j->on('users.id', '=', 'contacts.id')->orWhereNotIn('contacts.name', [48, 'baz', null]);
    });
    $this->assertSame('select * from "users" inner join "contacts" on "users"."id" = "contacts"."id" or "contacts"."name" not in (?, ?, ?)', $builder->toSql());
    $this->assertEquals([48, 'baz', null], $builder->getBindings());
});

test('joins with nested conditions', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->leftJoin('contacts', function ($j) {
        $j->on('users.id', '=', 'contacts.id')->where(function ($j) {
            $j->where('contacts.country', '=', 'US')->orWhere('contacts.is_partner', '=', 1);
        });
    });
    $this->assertSame('select * from "users" left join "contacts" on "users"."id" = "contacts"."id" and ("contacts"."country" = ? or "contacts"."is_partner" = ?)', $builder->toSql());
    $this->assertEquals(['US', 1], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->leftJoin('contacts', function ($j) {
        $j->on('users.id', '=', 'contacts.id')->where('contacts.is_active', '=', 1)->orOn(function ($j) {
            $j->orWhere(function ($j) {
                $j->where('contacts.country', '=', 'UK')->orOn('contacts.type', '=', 'users.type');
            })->where(function ($j) {
                $j->where('contacts.country', '=', 'US')->orWhereNull('contacts.is_partner');
            });
        });
    });
    $this->assertSame('select * from "users" left join "contacts" on "users"."id" = "contacts"."id" and "contacts"."is_active" = ? or (("contacts"."country" = ? or "contacts"."type" = "users"."type") and ("contacts"."country" = ? or "contacts"."is_partner" is null))', $builder->toSql());
    $this->assertEquals([1, 'UK', 'US'], $builder->getBindings());
});

test('joins with advanced conditions', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->leftJoin('contacts', function ($j) {
        $j->on('users.id', 'contacts.id')->where(function ($j) {
            $j->whereRole('admin')
                ->orWhereNull('contacts.disabled')
                ->orWhereRaw('year(contacts.created_at) = 2016');
        });
    });
    $this->assertSame('select * from "users" left join "contacts" on "users"."id" = "contacts"."id" and ("role" = ? or "contacts"."disabled" is null or year(contacts.created_at) = 2016)', $builder->toSql());
    $this->assertEquals(['admin'], $builder->getBindings());
});

test('joins with subquery condition', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->leftJoin('contacts', function ($j) {
        $j->on('users.id', 'contacts.id')->whereIn('contact_type_id', function ($q) {
            $q->select('id')->from('contact_types')
                ->where('category_id', '1')
                ->whereNull('deleted_at');
        });
    });
    $this->assertSame('select * from "users" left join "contacts" on "users"."id" = "contacts"."id" and "contact_type_id" in (select "id" from "contact_types" where "category_id" = ? and "deleted_at" is null)', $builder->toSql());
    $this->assertEquals(['1'], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->leftJoin('contacts', function ($j) {
        $j->on('users.id', 'contacts.id')->whereExists(function ($q) {
            $q->selectRaw('1')->from('contact_types')
                ->whereRaw('contact_types.id = contacts.contact_type_id')
                ->where('category_id', '1')
                ->whereNull('deleted_at');
        });
    });
    $this->assertSame('select * from "users" left join "contacts" on "users"."id" = "contacts"."id" and exists (select 1 from "contact_types" where contact_types.id = contacts.contact_type_id and "category_id" = ? and "deleted_at" is null)', $builder->toSql());
    $this->assertEquals(['1'], $builder->getBindings());
});

test('joins with advanced subquery condition', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->leftJoin('contacts', function ($j) {
        $j->on('users.id', 'contacts.id')->whereExists(function ($q) {
            $q->selectRaw('1')->from('contact_types')
                ->whereRaw('contact_types.id = contacts.contact_type_id')
                ->where('category_id', '1')
                ->whereNull('deleted_at')
                ->whereIn('level_id', function ($q) {
                    $q->select('id')->from('levels')
                        ->where('is_active', true);
                });
        });
    });
    $this->assertSame('select * from "users" left join "contacts" on "users"."id" = "contacts"."id" and exists (select 1 from "contact_types" where contact_types.id = contacts.contact_type_id and "category_id" = ? and "deleted_at" is null and "level_id" in (select "id" from "levels" where "is_active" = ?))', $builder->toSql());
    $this->assertEquals(['1', true], $builder->getBindings());
});

test('joins with nested joins', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('users.id', 'contacts.id', 'contact_types.id')->from('users')->leftJoin('contacts', function ($j) {
        $j->on('users.id', 'contacts.id')->join('contact_types', 'contacts.contact_type_id', '=', 'contact_types.id');
    });
    $this->assertSame('select "users"."id", "contacts"."id", "contact_types"."id" from "users" left join ("contacts" inner join "contact_types" on "contacts"."contact_type_id" = "contact_types"."id") on "users"."id" = "contacts"."id"', $builder->toSql());
});

test('joins with multiple nested joins', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('users.id', 'contacts.id', 'contact_types.id', 'countries.id', 'planets.id')->from('users')->leftJoin('contacts', function ($j) {
        $j->on('users.id', 'contacts.id')
            ->join('contact_types', 'contacts.contact_type_id', '=', 'contact_types.id')
            ->leftJoin('countries', function ($q) {
                $q->on('contacts.country', '=', 'countries.country')
                    ->join('planets', function ($q) {
                        $q->on('countries.planet_id', '=', 'planet.id')
                            ->where('planet.is_settled', '=', 1)
                            ->where('planet.population', '>=', 10000);
                    });
            });
    });
    $this->assertSame('select "users"."id", "contacts"."id", "contact_types"."id", "countries"."id", "planets"."id" from "users" left join ("contacts" inner join "contact_types" on "contacts"."contact_type_id" = "contact_types"."id" left join ("countries" inner join "planets" on "countries"."planet_id" = "planet"."id" and "planet"."is_settled" = ? and "planet"."population" >= ?) on "contacts"."country" = "countries"."country") on "users"."id" = "contacts"."id"', $builder->toSql());
    $this->assertEquals(['1', 10000], $builder->getBindings());
});

test('joins with nested join with advanced subquery condition', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('users.id', 'contacts.id', 'contact_types.id')->from('users')->leftJoin('contacts', function ($j) {
        $j->on('users.id', 'contacts.id')
            ->join('contact_types', 'contacts.contact_type_id', '=', 'contact_types.id')
            ->whereExists(function ($q) {
                $q->select('*')->from('countries')
                    ->whereColumn('contacts.country', '=', 'countries.country')
                    ->join('planets', function ($q) {
                        $q->on('countries.planet_id', '=', 'planet.id')
                            ->where('planet.is_settled', '=', 1);
                    })
                    ->where('planet.population', '>=', 10000);
            });
    });
    $this->assertSame('select "users"."id", "contacts"."id", "contact_types"."id" from "users" left join ("contacts" inner join "contact_types" on "contacts"."contact_type_id" = "contact_types"."id") on "users"."id" = "contacts"."id" and exists (select * from "countries" inner join "planets" on "countries"."planet_id" = "planet"."id" and "planet"."is_settled" = ? where "contacts"."country" = "countries"."country" and "planet"."population" >= ?)', $builder->toSql());
    $this->assertEquals(['1', 10000], $builder->getBindings());
});

test('join with nested on condition', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('users.id')->from('users')->join('contacts', function (JoinClause $j) {
        return $j
            ->on('users.id', 'contacts.id')
            ->addNestedWhereQuery(queryBuilderGetBuilder()->where('contacts.id', 1));
    });
    $this->assertSame('select "users"."id" from "users" inner join "contacts" on "users"."id" = "contacts"."id" and ("contacts"."id" = ?)', $builder->toSql());
    $this->assertEquals([1], $builder->getBindings());
});

test('join sub', function () {
    $builder = queryBuilderGetBuilder();
    $builder->from('users')->joinSub('select * from "contacts"', 'sub', 'users.id', '=', 'sub.id');
    $this->assertSame('select * from "users" inner join (select * from "contacts") as "sub" on "users"."id" = "sub"."id"', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->from('users')->joinSub(function ($q) {
        $q->from('contacts');
    }, 'sub', 'users.id', '=', 'sub.id');
    $this->assertSame('select * from "users" inner join (select * from "contacts") as "sub" on "users"."id" = "sub"."id"', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $instrumentBuilder = new InstrumentBuilder(queryBuilderGetBuilder()->from('contacts'));
    $builder->from('users')->joinSub($instrumentBuilder, 'sub', 'users.id', '=', 'sub.id');
    $this->assertSame('select * from "users" inner join (select * from "contacts") as "sub" on "users"."id" = "sub"."id"', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $sub1 = queryBuilderGetBuilder()->from('contacts')->where('name', 'foo');
    $sub2 = queryBuilderGetBuilder()->from('contacts')->where('name', 'bar');
    $builder->from('users')
        ->joinSub($sub1, 'sub1', 'users.id', '=', 1, 'inner', true)
        ->joinSub($sub2, 'sub2', 'users.id', '=', 'sub2.user_id');
    $expected = 'select * from "users" ';
    $expected .= 'inner join (select * from "contacts" where "name" = ?) as "sub1" on "users"."id" = ? ';
    $expected .= 'inner join (select * from "contacts" where "name" = ?) as "sub2" on "users"."id" = "sub2"."user_id"';
    $this->assertEquals($expected, $builder->toSql());
    $this->assertEquals(['foo', 1, 'bar'], $builder->getRawBindings()['join']);

    $builder = queryBuilderGetBuilder();
    $builder->from('users')->joinSub(['foo'], 'sub', 'users.id', '=', 'sub.id');
})->throws(InvalidArgumentException::class);

test('join sub with prefix', function () {
    $builder = queryBuilderGetBuilder(prefix: 'prefix_');
    $builder->from('users')->joinSub('select * from "contacts"', 'sub', 'users.id', '=', 'sub.id');
    $this->assertSame('select * from "prefix_users" inner join (select * from "contacts") as "prefix_sub" on "prefix_users"."id" = "prefix_sub"."id"', $builder->toSql());
});

test('left join sub', function () {
    $builder = queryBuilderGetBuilder();
    $builder->from('users')->leftJoinSub(queryBuilderGetBuilder()->from('contacts'), 'sub', 'users.id', '=', 'sub.id');
    $this->assertSame('select * from "users" left join (select * from "contacts") as "sub" on "users"."id" = "sub"."id"', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->from('users')->leftJoinSub(['foo'], 'sub', 'users.id', '=', 'sub.id');
})->throws(InvalidArgumentException::class);

test('right join sub', function () {
    $builder = queryBuilderGetBuilder();
    $builder->from('users')->rightJoinSub(queryBuilderGetBuilder()->from('contacts'), 'sub', 'users.id', '=', 'sub.id');
    $this->assertSame('select * from "users" right join (select * from "contacts") as "sub" on "users"."id" = "sub"."id"', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->from('users')->rightJoinSub(['foo'], 'sub', 'users.id', '=', 'sub.id');
})->throws(InvalidArgumentException::class);

test('join lateral', function () {
    $builder = queryBuilderGetMySqlBuilder();
    $builder->getConnection()->shouldReceive('getDatabaseName');
    $builder->from('users')->joinLateral('select * from `contacts` where `contracts`.`user_id` = `users`.`id`', 'sub');
    $this->assertSame('select * from `users` inner join lateral (select * from `contacts` where `contracts`.`user_id` = `users`.`id`) as `sub` on true', $builder->toSql());

    $builder = queryBuilderGetMySqlBuilder();
    $builder->getConnection()->shouldReceive('getDatabaseName');
    $builder->from('users')->joinLateral(function ($q) {
        $q->from('contacts')->whereColumn('contracts.user_id', 'users.id');
    }, 'sub');
    $this->assertSame('select * from `users` inner join lateral (select * from `contacts` where `contracts`.`user_id` = `users`.`id`) as `sub` on true', $builder->toSql());

    $builder = queryBuilderGetMySqlBuilder();
    $builder->getConnection()->shouldReceive('getDatabaseName');
    $sub = queryBuilderGetMySqlBuilder();
    $sub->getConnection()->shouldReceive('getDatabaseName');
    $instrumentBuilder = new InstrumentBuilder($sub->from('contacts')->whereColumn('contracts.user_id', 'users.id'));
    $builder->from('users')->joinLateral($instrumentBuilder, 'sub');
    $this->assertSame('select * from `users` inner join lateral (select * from `contacts` where `contracts`.`user_id` = `users`.`id`) as `sub` on true', $builder->toSql());

    $sub1 = queryBuilderGetMySqlBuilder();
    $sub1->getConnection()->shouldReceive('getDatabaseName');
    $sub1 = $sub1->from('contacts')->whereColumn('contracts.user_id', 'users.id')->where('name', 'foo');

    $sub2 = queryBuilderGetMySqlBuilder();
    $sub2->getConnection()->shouldReceive('getDatabaseName');
    $sub2 = $sub2->from('contacts')->whereColumn('contracts.user_id', 'users.id')->where('name', 'bar');

    $builder = queryBuilderGetMySqlBuilder();
    $builder->getConnection()->shouldReceive('getDatabaseName');
    $builder->from('users')->joinLateral($sub1, 'sub1')->joinLateral($sub2, 'sub2');

    $expected = 'select * from `users` ';
    $expected .= 'inner join lateral (select * from `contacts` where `contracts`.`user_id` = `users`.`id` and `name` = ?) as `sub1` on true ';
    $expected .= 'inner join lateral (select * from `contacts` where `contracts`.`user_id` = `users`.`id` and `name` = ?) as `sub2` on true';

    $this->assertEquals($expected, $builder->toSql());
    $this->assertEquals(['foo', 'bar'], $builder->getRawBindings()['join']);

    $builder = queryBuilderGetMySqlBuilder();
    $builder->from('users')->joinLateral(['foo'], 'sub');
})->throws(InvalidArgumentException::class);

test('join lateral maria db', function () {
    $builder = queryBuilderGetMariaDbBuilder();
    $builder->getConnection()->shouldReceive('getDatabaseName');
    $builder->from('users')->joinLateral(function ($q) {
        $q->from('contacts')->whereColumn('contracts.user_id', 'users.id');
    }, 'sub')->toSql();
})->throws(RuntimeException::class);

test('join lateral s q lite', function () {
    $builder = queryBuilderGetSQLiteBuilder();
    $builder->getConnection()->shouldReceive('getDatabaseName');
    $builder->from('users')->joinLateral(function ($q) {
        $q->from('contacts')->whereColumn('contracts.user_id', 'users.id');
    }, 'sub')->toSql();
})->throws(RuntimeException::class);

test('join lateral postgres', function () {
    $builder = queryBuilderGetPostgresBuilder();
    $builder->getConnection()->shouldReceive('getDatabaseName');
    $builder->from('users')->joinLateral(function ($q) {
        $q->from('contacts')->whereColumn('contracts.user_id', 'users.id');
    }, 'sub');
    $this->assertSame('select * from "users" inner join lateral (select * from "contacts" where "contracts"."user_id" = "users"."id") as "sub" on true', $builder->toSql());
});

test('join lateral sql server', function () {
    $builder = queryBuilderGetSqlServerBuilder();
    $builder->getConnection()->shouldReceive('getDatabaseName');
    $builder->from('users')->joinLateral(function ($q) {
        $q->from('contacts')->whereColumn('contracts.user_id', 'users.id');
    }, 'sub');
    $this->assertSame('select * from [users] cross apply (select * from [contacts] where [contracts].[user_id] = [users].[id]) as [sub]', $builder->toSql());
});

test('join lateral with prefix', function () {
    $builder = queryBuilderGetMySqlBuilder(prefix: 'prefix_');
    $builder->from('users')->joinLateral('select * from `contacts` where `contracts`.`user_id` = `users`.`id`', 'sub');
    $this->assertSame('select * from `prefix_users` inner join lateral (select * from `contacts` where `contracts`.`user_id` = `users`.`id`) as `prefix_sub` on true', $builder->toSql());
});

test('left join lateral', function () {
    $builder = queryBuilderGetMySqlBuilder();
    $builder->getConnection()->shouldReceive('getDatabaseName');

    $sub = queryBuilderGetMySqlBuilder();
    $sub->getConnection()->shouldReceive('getDatabaseName');

    $builder->from('users')->leftJoinLateral($sub->from('contacts')->whereColumn('contracts.user_id', 'users.id'), 'sub');
    $this->assertSame('select * from `users` left join lateral (select * from `contacts` where `contracts`.`user_id` = `users`.`id`) as `sub` on true', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->from('users')->leftJoinLateral(['foo'], 'sub');
})->throws(InvalidArgumentException::class);

test('left join lateral sql server', function () {
    $builder = queryBuilderGetSqlServerBuilder();
    $builder->getConnection()->shouldReceive('getDatabaseName');
    $builder->from('users')->leftJoinLateral(function ($q) {
        $q->from('contacts')->whereColumn('contracts.user_id', 'users.id');
    }, 'sub');
    $this->assertSame('select * from [users] outer apply (select * from [contacts] where [contracts].[user_id] = [users].[id]) as [sub]', $builder->toSql());
});

test('raw expressions in select', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select(new Raw('substr(foo, 6)'))->from('users');
    $this->assertSame('select substr(foo, 6) from "users"', $builder->toSql());
});

test('find returns first result by i d', function () {
    $builder = queryBuilderGetBuilder();
    $builder->getConnection()->shouldReceive('select')->once()->with('select * from "users" where "id" = ? limit 1', [1], true)->andReturn([['foo' => 'bar']]);
    $builder->getProcessor()->shouldReceive('processSelect')->once()->with($builder, [['foo' => 'bar']])->andReturnUsing(function ($query, $results) {
        return $results;
    });
    $results = $builder->from('users')->find(1);
    $this->assertEquals(['foo' => 'bar'], $results);
});

test('find or returns first result by i d', function () {
    $builder = queryBuilderGetMockQueryBuilder();
    $data = m::mock(stdClass::class);
    $builder->shouldReceive('first')->andReturn($data)->once();
    $builder->shouldReceive('first')->with(['column'])->andReturn($data)->once();
    $builder->shouldReceive('first')->andReturn(null)->once();

    $this->assertSame($data, $builder->findOr(1, fn () => 'callback result'));
    $this->assertSame($data, $builder->findOr(1, ['column'], fn () => 'callback result'));
    $this->assertSame('callback result', $builder->findOr(1, fn () => 'callback result'));
});

test('first method returns first result', function () {
    $builder = queryBuilderGetBuilder();
    $builder->getConnection()->shouldReceive('select')->once()->with('select * from "users" where "id" = ? limit 1', [1], true)->andReturn([['foo' => 'bar']]);
    $builder->getProcessor()->shouldReceive('processSelect')->once()->with($builder, [['foo' => 'bar']])->andReturnUsing(function ($query, $results) {
        return $results;
    });
    $results = $builder->from('users')->where('id', '=', 1)->first();
    $this->assertEquals(['foo' => 'bar'], $results);
});

test('first or fail method returns first result', function () {
    $builder = queryBuilderGetBuilder();
    $builder->getConnection()->shouldReceive('select')->once()->with('select * from "users" where "id" = ? limit 1', [1], true)->andReturn([['foo' => 'bar']]);
    $builder->getProcessor()->shouldReceive('processSelect')->once()->with($builder, [['foo' => 'bar']])->andReturnUsing(function ($query, $results) {
        return $results;
    });
    $results = $builder->from('users')->where('id', '=', 1)->firstOrFail();
    $this->assertEquals(['foo' => 'bar'], $results);
});

test('first or fail method throws record not found exception', function () {
    $builder = queryBuilderGetBuilder();
    $builder->getConnection()->shouldReceive('select')->once()->with('select * from "users" where "id" = ? limit 1', [1], true)->andReturn([]);

    $builder->getProcessor()->shouldReceive('processSelect')->once()->with($builder, [])->andReturn([]);


    $builder->from('users')->where('id', '=', 1)->firstOrFail();
})->throws(RecordNotFoundException::class, 'No record found for the given query.');

test('pluck method gets collection of column values', function () {
    $builder = queryBuilderGetBuilder();
    $builder->getConnection()->shouldReceive('select')->once()->andReturn([['foo' => 'bar'], ['foo' => 'baz']]);
    $builder->getProcessor()->shouldReceive('processSelect')->once()->with($builder, [['foo' => 'bar'], ['foo' => 'baz']])->andReturnUsing(function ($query, $results) {
        return $results;
    });
    $results = $builder->from('users')->where('id', '=', 1)->pluck('foo');
    $this->assertEquals(['bar', 'baz'], $results->all());

    $builder = queryBuilderGetBuilder();
    $builder->getConnection()->shouldReceive('select')->once()->andReturn([['id' => 1, 'foo' => 'bar'], ['id' => 10, 'foo' => 'baz']]);
    $builder->getProcessor()->shouldReceive('processSelect')->once()->with($builder, [['id' => 1, 'foo' => 'bar'], ['id' => 10, 'foo' => 'baz']])->andReturnUsing(function ($query, $results) {
        return $results;
    });
    $results = $builder->from('users')->where('id', '=', 1)->pluck('foo', 'id');
    $this->assertEquals([1 => 'bar', 10 => 'baz'], $results->all());
});

test('pluck avoids duplicate column selection', function () {
    $builder = queryBuilderGetBuilder();
    $builder->getConnection()->shouldReceive('select')->once()->with('select "foo" from "users" where "id" = ?', [1], true)->andReturn([['foo' => 'bar']]);
    $builder->getProcessor()->shouldReceive('processSelect')->once()->with($builder, [['foo' => 'bar']])->andReturnUsing(function ($query, $results) {
        return $results;
    });
    $results = $builder->from('users')->where('id', '=', 1)->pluck('foo', 'foo');
    $this->assertEquals(['bar' => 'bar'], $results->all());
});

test('implode', function () {
    // Test without glue.
    $builder = queryBuilderGetBuilder();
    $builder->getConnection()->shouldReceive('select')->once()->andReturn([['foo' => 'bar'], ['foo' => 'baz']]);
    $builder->getProcessor()->shouldReceive('processSelect')->once()->with($builder, [['foo' => 'bar'], ['foo' => 'baz']])->andReturnUsing(function ($query, $results) {
        return $results;
    });
    $results = $builder->from('users')->where('id', '=', 1)->implode('foo');
    $this->assertSame('barbaz', $results);

    // Test with glue.
    $builder = queryBuilderGetBuilder();
    $builder->getConnection()->shouldReceive('select')->once()->andReturn([['foo' => 'bar'], ['foo' => 'baz']]);
    $builder->getProcessor()->shouldReceive('processSelect')->once()->with($builder, [['foo' => 'bar'], ['foo' => 'baz']])->andReturnUsing(function ($query, $results) {
        return $results;
    });
    $results = $builder->from('users')->where('id', '=', 1)->implode('foo', ',');
    $this->assertSame('bar,baz', $results);
});

test('value method returns single column', function () {
    $builder = queryBuilderGetBuilder();
    $builder->getConnection()->shouldReceive('select')->once()->with('select "foo" from "users" where "id" = ? limit 1', [1], true)->andReturn([['foo' => 'bar']]);
    $builder->getProcessor()->shouldReceive('processSelect')->once()->with($builder, [['foo' => 'bar']])->andReturn([['foo' => 'bar']]);
    $results = $builder->from('users')->where('id', '=', 1)->value('foo');
    $this->assertSame('bar', $results);
});

test('raw value method returns single column', function () {
    $builder = queryBuilderGetBuilder();
    $builder->getConnection()->shouldReceive('select')->once()->with('select UPPER("foo") from "users" where "id" = ? limit 1', [1], true)->andReturn([['UPPER("foo")' => 'BAR']]);
    $builder->getProcessor()->shouldReceive('processSelect')->once()->with($builder, [['UPPER("foo")' => 'BAR']])->andReturn([['UPPER("foo")' => 'BAR']]);
    $results = $builder->from('users')->where('id', '=', 1)->rawValue('UPPER("foo")');
    $this->assertSame('BAR', $results);
});

test('aggregate functions', function () {
    $builder = queryBuilderGetBuilder();
    $builder->getConnection()->shouldReceive('select')->once()->with('select count(*) as aggregate from "users"', [], true)->andReturn([['aggregate' => 1]]);
    $builder->getProcessor()->shouldReceive('processSelect')->once()->andReturnUsing(function ($builder, $results) {
        return $results;
    });
    $results = $builder->from('users')->count();
    $this->assertEquals(1, $results);

    $builder = queryBuilderGetBuilder();
    $builder->getConnection()->shouldReceive('select')->once()->with('select exists(select * from "users") as "exists"', [], true)->andReturn([['exists' => 1]]);
    $results = $builder->from('users')->exists();
    $this->assertTrue($results);

    $builder = queryBuilderGetBuilder();
    $builder->getConnection()->shouldReceive('select')->once()->with('select exists(select * from "users") as "exists"', [], true)->andReturn([['exists' => 0]]);
    $results = $builder->from('users')->doesntExist();
    $this->assertTrue($results);

    $builder = queryBuilderGetBuilder();
    $builder->getConnection()->shouldReceive('select')->once()->with('select max("id") as aggregate from "users"', [], true)->andReturn([['aggregate' => 1]]);
    $builder->getProcessor()->shouldReceive('processSelect')->once()->andReturnUsing(function ($builder, $results) {
        return $results;
    });
    $results = $builder->from('users')->max('id');
    $this->assertEquals(1, $results);

    $builder = queryBuilderGetBuilder();
    $builder->getConnection()->shouldReceive('select')->once()->with('select min("id") as aggregate from "users"', [], true)->andReturn([['aggregate' => 1]]);
    $builder->getProcessor()->shouldReceive('processSelect')->once()->andReturnUsing(function ($builder, $results) {
        return $results;
    });
    $results = $builder->from('users')->min('id');
    $this->assertEquals(1, $results);

    $builder = queryBuilderGetBuilder();
    $builder->getConnection()->shouldReceive('select')->once()->with('select sum("id") as aggregate from "users"', [], true)->andReturn([['aggregate' => 1]]);
    $builder->getProcessor()->shouldReceive('processSelect')->once()->andReturnUsing(function ($builder, $results) {
        return $results;
    });
    $results = $builder->from('users')->sum('id');
    $this->assertEquals(1, $results);

    $builder = queryBuilderGetBuilder();
    $builder->getConnection()->shouldReceive('select')->once()->with('select avg("id") as aggregate from "users"', [], true)->andReturn([['aggregate' => 1]]);
    $builder->getProcessor()->shouldReceive('processSelect')->once()->andReturnUsing(function ($builder, $results) {
        return $results;
    });
    $results = $builder->from('users')->avg('id');
    $this->assertEquals(1, $results);

    $builder = queryBuilderGetBuilder();
    $builder->getConnection()->shouldReceive('select')->once()->with('select avg("id") as aggregate from "users"', [], true)->andReturn([['aggregate' => 1]]);
    $builder->getProcessor()->shouldReceive('processSelect')->once()->andReturnUsing(function ($builder, $results) {
        return $results;
    });
    $results = $builder->from('users')->average('id');
    $this->assertEquals(1, $results);
});

test('sql server exists', function () {
    $builder = queryBuilderGetSqlServerBuilder();
    $builder->getConnection()->shouldReceive('select')->once()->with('select top 1 1 [exists] from [users]', [], true)->andReturn([['exists' => 1]]);
    $results = $builder->from('users')->exists();
    $this->assertTrue($results);
});

test('exists or', function () {
    $builder = queryBuilderGetBuilder();
    $builder->getConnection()->shouldReceive('select')->andReturn([['exists' => 1]]);
    $results = $builder->from('users')->doesntExistOr(function () {
        return 123;
    });
    $this->assertSame(123, $results);
    $builder = queryBuilderGetBuilder();
    $builder->getConnection()->shouldReceive('select')->andReturn([['exists' => 0]]);
    $results = $builder->from('users')->doesntExistOr(function () {
        throw new RuntimeException;
    });
    $this->assertTrue($results);
});

test('doesnt exists or', function () {
    $builder = queryBuilderGetBuilder();
    $builder->getConnection()->shouldReceive('select')->andReturn([['exists' => 0]]);
    $results = $builder->from('users')->existsOr(function () {
        return 123;
    });
    $this->assertSame(123, $results);
    $builder = queryBuilderGetBuilder();
    $builder->getConnection()->shouldReceive('select')->andReturn([['exists' => 1]]);
    $results = $builder->from('users')->existsOr(function () {
        throw new RuntimeException;
    });
    $this->assertTrue($results);
});

test('aggregate reset followed by get', function () {
    $builder = queryBuilderGetBuilder();
    $builder->getConnection()->shouldReceive('select')->once()->with('select count(*) as aggregate from "users"', [], true)->andReturn([['aggregate' => 1]]);
    $builder->getConnection()->shouldReceive('select')->once()->with('select sum("id") as aggregate from "users"', [], true)->andReturn([['aggregate' => 2]]);
    $builder->getConnection()->shouldReceive('select')->once()->with('select "column1", "column2" from "users"', [], true)->andReturn([['column1' => 'foo', 'column2' => 'bar']]);
    $builder->getProcessor()->shouldReceive('processSelect')->andReturnUsing(function ($builder, $results) {
        return $results;
    });
    $builder->from('users')->select('column1', 'column2');
    $count = $builder->count();
    $this->assertEquals(1, $count);
    $sum = $builder->sum('id');
    $this->assertEquals(2, $sum);
    $result = $builder->get();
    $this->assertEquals([['column1' => 'foo', 'column2' => 'bar']], $result->all());
});

test('aggregate reset followed by select get', function () {
    $builder = queryBuilderGetBuilder();
    $builder->getConnection()->shouldReceive('select')->once()->with('select count("column1") as aggregate from "users"', [], true)->andReturn([['aggregate' => 1]]);
    $builder->getConnection()->shouldReceive('select')->once()->with('select "column2", "column3" from "users"', [], true)->andReturn([['column2' => 'foo', 'column3' => 'bar']]);
    $builder->getProcessor()->shouldReceive('processSelect')->andReturnUsing(function ($builder, $results) {
        return $results;
    });
    $builder->from('users');
    $count = $builder->count('column1');
    $this->assertEquals(1, $count);
    $result = $builder->select('column2', 'column3')->get();
    $this->assertEquals([['column2' => 'foo', 'column3' => 'bar']], $result->all());
});

test('aggregate reset followed by get with columns', function () {
    $builder = queryBuilderGetBuilder();
    $builder->getConnection()->shouldReceive('select')->once()->with('select count("column1") as aggregate from "users"', [], true)->andReturn([['aggregate' => 1]]);
    $builder->getConnection()->shouldReceive('select')->once()->with('select "column2", "column3" from "users"', [], true)->andReturn([['column2' => 'foo', 'column3' => 'bar']]);
    $builder->getProcessor()->shouldReceive('processSelect')->andReturnUsing(function ($builder, $results) {
        return $results;
    });
    $builder->from('users');
    $count = $builder->count('column1');
    $this->assertEquals(1, $count);
    $result = $builder->get(['column2', 'column3']);
    $this->assertEquals([['column2' => 'foo', 'column3' => 'bar']], $result->all());
});

test('aggregate with sub select', function () {
    $builder = queryBuilderGetBuilder();
    $builder->getConnection()->shouldReceive('select')->once()->with('select count(*) as aggregate from "users"', [], true)->andReturn([['aggregate' => 1]]);
    $builder->getProcessor()->shouldReceive('processSelect')->once()->andReturnUsing(function ($builder, $results) {
        return $results;
    });
    $builder->from('users')->selectSub(function ($query) {
        $query->from('posts')->select('foo', 'bar')->where('title', 'foo');
    }, 'post');
    $count = $builder->count();
    $this->assertEquals(1, $count);
    $this->assertSame('(select "foo", "bar" from "posts" where "title" = ?) as "post"', $builder->getGrammar()->getValue($builder->columns[0]));
    $this->assertEquals(['foo'], $builder->getBindings());
});

test('subqueries bindings', function () {
    $builder = queryBuilderGetBuilder();
    $second = queryBuilderGetBuilder()->select('*')->from('users')->orderByRaw('id = ?', 2);
    $third = queryBuilderGetBuilder()->select('*')->from('users')->where('id', 3)->groupBy('id')->having('id', '!=', 4);
    $builder->groupBy('a')->having('a', '=', 1)->union($second)->union($third);
    $this->assertEquals([0 => 1, 1 => 2, 2 => 3, 3 => 4], $builder->getBindings());

    $builder = queryBuilderGetBuilder()->select('*')->from('users')->where('email', '=', function ($q) {
        $q->select(new Raw('max(id)'))
            ->from('users')->where('email', '=', 'bar')
            ->orderByRaw('email like ?', '%.com')
            ->groupBy('id')->having('id', '=', 4);
    })->orWhere('id', '=', 'foo')->groupBy('id')->having('id', '=', 5);
    $this->assertEquals([0 => 'bar', 1 => 4, 2 => '%.com', 3 => 'foo', 4 => 5], $builder->getBindings());
});

test('insert method', function () {
    $builder = queryBuilderGetBuilder();
    $builder->getConnection()->shouldReceive('insert')->once()->with('insert into "users" ("email") values (?)', ['foo'])->andReturn(true);
    $result = $builder->from('users')->insert(['email' => 'foo']);
    $this->assertTrue($result);
});

test('insert using method', function () {
    $builder = queryBuilderGetBuilder();
    $builder->getConnection()->shouldReceive('affectingStatement')->once()->with('insert into "table1" ("foo") select "bar" from "table2" where "foreign_id" = ?', [5])->andReturn(1);

    $result = $builder->from('table1')->insertUsing(
        ['foo'],
        function (Builder $query) {
            $query->select(['bar'])->from('table2')->where('foreign_id', '=', 5);
        }
    );

    $this->assertEquals(1, $result);
});

test('insert using with empty columns', function () {
    $builder = queryBuilderGetBuilder();
    $builder->getConnection()->shouldReceive('affectingStatement')->once()->with('insert into "table1" select * from "table2" where "foreign_id" = ?', [5])->andReturn(1);

    $result = $builder->from('table1')->insertUsing(
        [],
        function (Builder $query) {
            $query->from('table2')->where('foreign_id', '=', 5);
        }
    );

    $this->assertEquals(1, $result);
});

test('insert using invalid subquery', function () {
    $builder = queryBuilderGetBuilder();
    $builder->from('table1')->insertUsing(['foo'], ['bar']);
})->throws(InvalidArgumentException::class);

test('insert or ignore method', function () {
    $builder = queryBuilderGetBuilder();
    $builder->from('users')->insertOrIgnore(['email' => 'foo']);
})->throws(RuntimeException::class, 'does not support');

test('my sql insert or ignore method', function () {
    $builder = queryBuilderGetMySqlBuilder();
    $builder->getConnection()->shouldReceive('affectingStatement')->once()->with('insert ignore into `users` (`email`) values (?)', ['foo'])->andReturn(1);
    $result = $builder->from('users')->insertOrIgnore(['email' => 'foo']);
    $this->assertEquals(1, $result);
});

test('postgres insert or ignore method', function () {
    $builder = queryBuilderGetPostgresBuilder();
    $builder->getConnection()->shouldReceive('affectingStatement')->once()->with('insert into "users" ("email") values (?) on conflict do nothing', ['foo'])->andReturn(1);
    $result = $builder->from('users')->insertOrIgnore(['email' => 'foo']);
    $this->assertEquals(1, $result);
});

test('s q lite insert or ignore method', function () {
    $builder = queryBuilderGetSQLiteBuilder();
    $builder->getConnection()->shouldReceive('affectingStatement')->once()->with('insert or ignore into "users" ("email") values (?)', ['foo'])->andReturn(1);
    $result = $builder->from('users')->insertOrIgnore(['email' => 'foo']);
    $this->assertEquals(1, $result);
});

test('sql server insert or ignore method', function () {
    $builder = queryBuilderGetSqlServerBuilder();
    $builder->from('users')->insertOrIgnore(['email' => 'foo']);
})->throws(RuntimeException::class, 'does not support');

test('insert or ignore using method', function () {
    $builder = queryBuilderGetBuilder();
    $builder->from('users')->insertOrIgnoreUsing(['email' => 'foo'], 'bar');
})->throws(RuntimeException::class, 'does not support');

test('sql server insert or ignore using method', function () {
    $builder = queryBuilderGetSqlServerBuilder();
    $builder->from('users')->insertOrIgnoreUsing(['email' => 'foo'], 'bar');
})->throws(RuntimeException::class, 'does not support');

test('my sql insert or ignore using method', function () {
    $builder = queryBuilderGetMySqlBuilder();
    $builder->getConnection()->shouldReceive('getDatabaseName');
    $builder->getConnection()->shouldReceive('affectingStatement')->once()->with('insert ignore into `table1` (`foo`) select `bar` from `table2` where `foreign_id` = ?', [0 => 5])->andReturn(1);

    $result = $builder->from('table1')->insertOrIgnoreUsing(
        ['foo'],
        function (Builder $query) {
            $query->select(['bar'])->from('table2')->where('foreign_id', '=', 5);
        }
    );

    $this->assertEquals(1, $result);
});

test('my sql insert or ignore using with empty columns', function () {
    $builder = queryBuilderGetMySqlBuilder();
    $builder->getConnection()->shouldReceive('getDatabaseName');
    $builder->getConnection()->shouldReceive('affectingStatement')->once()->with('insert ignore into `table1` select * from `table2` where `foreign_id` = ?', [0 => 5])->andReturn(1);

    $result = $builder->from('table1')->insertOrIgnoreUsing(
        [],
        function (Builder $query) {
            $query->from('table2')->where('foreign_id', '=', 5);
        }
    );

    $this->assertEquals(1, $result);
});

test('my sql insert or ignore using invalid subquery', function () {
    $builder = queryBuilderGetMySqlBuilder();
    $builder->from('table1')->insertOrIgnoreUsing(['foo'], ['bar']);
})->throws(InvalidArgumentException::class);

test('postgres insert or ignore using method', function () {
    $builder = queryBuilderGetPostgresBuilder();
    $builder->getConnection()->shouldReceive('affectingStatement')->once()->with('insert into "table1" ("foo") select "bar" from "table2" where "foreign_id" = ? on conflict do nothing', [5])->andReturn(1);

    $result = $builder->from('table1')->insertOrIgnoreUsing(
        ['foo'],
        function (Builder $query) {
            $query->select(['bar'])->from('table2')->where('foreign_id', '=', 5);
        }
    );

    $this->assertEquals(1, $result);
});

test('postgres insert or ignore using with empty columns', function () {
    $builder = queryBuilderGetPostgresBuilder();
    $builder->getConnection()->shouldReceive('affectingStatement')->once()->with('insert into "table1" select * from "table2" where "foreign_id" = ? on conflict do nothing', [5])->andReturn(1);

    $result = $builder->from('table1')->insertOrIgnoreUsing(
        [],
        function (Builder $query) {
            $query->from('table2')->where('foreign_id', '=', 5);
        }
    );

    $this->assertEquals(1, $result);
});

test('postgres insert or ignore using invalid subquery', function () {
    $builder = queryBuilderGetPostgresBuilder();
    $builder->from('table1')->insertOrIgnoreUsing(['foo'], ['bar']);
})->throws(InvalidArgumentException::class);

test('s q lite insert or ignore using method', function () {
    $builder = queryBuilderGetSQLiteBuilder();
    $builder->getConnection()->shouldReceive('getDatabaseName');
    $builder->getConnection()->shouldReceive('affectingStatement')->once()->with('insert or ignore into "table1" ("foo") select "bar" from "table2" where "foreign_id" = ?', [5])->andReturn(1);

    $result = $builder->from('table1')->insertOrIgnoreUsing(
        ['foo'],
        function (Builder $query) {
            $query->select(['bar'])->from('table2')->where('foreign_id', '=', 5);
        }
    );

    $this->assertEquals(1, $result);
});

test('s q lite insert or ignore using with empty columns', function () {
    $builder = queryBuilderGetSQLiteBuilder();
    $builder->getConnection()->shouldReceive('getDatabaseName');
    $builder->getConnection()->shouldReceive('affectingStatement')->once()->with('insert or ignore into "table1" select * from "table2" where "foreign_id" = ?', [5])->andReturn(1);

    $result = $builder->from('table1')->insertOrIgnoreUsing(
        [],
        function (Builder $query) {
            $query->from('table2')->where('foreign_id', '=', 5);
        }
    );

    $this->assertEquals(1, $result);
});

test('s q lite insert or ignore using invalid subquery', function () {
    $builder = queryBuilderGetSQLiteBuilder();
    $builder->from('table1')->insertOrIgnoreUsing(['foo'], ['bar']);
})->throws(InvalidArgumentException::class);

test('insert get id method', function () {
    $builder = queryBuilderGetBuilder();
    $builder->getProcessor()->shouldReceive('processInsertGetId')->once()->with($builder, 'insert into "users" ("email") values (?)', ['foo'], 'id')->andReturn(1);
    $result = $builder->from('users')->insertGetId(['email' => 'foo'], 'id');
    $this->assertEquals(1, $result);
});

test('insert get id method removes expressions', function () {
    $builder = queryBuilderGetBuilder();
    $builder->getProcessor()->shouldReceive('processInsertGetId')->once()->with($builder, 'insert into "users" ("email", "bar") values (?, bar)', ['foo'], 'id')->andReturn(1);
    $result = $builder->from('users')->insertGetId(['email' => 'foo', 'bar' => new Raw('bar')], 'id');
    $this->assertEquals(1, $result);
});

test('insert get id with empty values', function () {
    $builder = queryBuilderGetMySqlBuilder();
    $builder->getProcessor()->shouldReceive('processInsertGetId')->once()->with($builder, 'insert into `users` () values ()', [], null);
    $builder->from('users')->insertGetId([]);

    $builder = queryBuilderGetPostgresBuilder();
    $builder->getProcessor()->shouldReceive('processInsertGetId')->once()->with($builder, 'insert into "users" default values returning "id"', [], null);
    $builder->from('users')->insertGetId([]);

    $builder = queryBuilderGetSQLiteBuilder();
    $builder->getProcessor()->shouldReceive('processInsertGetId')->once()->with($builder, 'insert into "users" default values', [], null);
    $builder->from('users')->insertGetId([]);

    $builder = queryBuilderGetSqlServerBuilder();
    $builder->getProcessor()->shouldReceive('processInsertGetId')->once()->with($builder, 'insert into [users] default values', [], null);
    $builder->from('users')->insertGetId([]);
});

test('insert method respects raw bindings', function () {
    $builder = queryBuilderGetBuilder();
    $builder->getConnection()->shouldReceive('insert')->once()->with('insert into "users" ("email") values (CURRENT TIMESTAMP)', [])->andReturn(true);
    $result = $builder->from('users')->insert(['email' => new Raw('CURRENT TIMESTAMP')]);
    $this->assertTrue($result);
});

test('multiple inserts with expression values', function () {
    $builder = queryBuilderGetBuilder();
    $builder->getConnection()->shouldReceive('insert')->once()->with('insert into "users" ("email") values (UPPER(\'Foo\')), (LOWER(\'Foo\'))', [])->andReturn(true);
    $result = $builder->from('users')->insert([['email' => new Raw("UPPER('Foo')")], ['email' => new Raw("LOWER('Foo')")]]);
    $this->assertTrue($result);
});

test('update method', function () {
    $builder = queryBuilderGetBuilder();
    $builder->getConnection()->shouldReceive('update')->once()->with('update "users" set "email" = ?, "name" = ? where "id" = ?', ['foo', 'bar', 1])->andReturn(1);
    $result = $builder->from('users')->where('id', '=', 1)->update(['email' => 'foo', 'name' => 'bar']);
    $this->assertEquals(1, $result);

    $builder = queryBuilderGetMySqlBuilder();
    $builder->getConnection()->shouldReceive('update')->once()->with('update `users` set `email` = ?, `name` = ? where `id` = ? order by `foo` desc limit 5', ['foo', 'bar', 1])->andReturn(1);
    $result = $builder->from('users')->where('id', '=', 1)->orderBy('foo', 'desc')->limit(5)->update(['email' => 'foo', 'name' => 'bar']);
    $this->assertEquals(1, $result);
});

test('upsert method', function () {
    $builder = queryBuilderGetMySqlBuilder();
    $builder->getConnection()
        ->shouldReceive('getConfig')->with('use_upsert_alias')->andReturn(false)
        ->shouldReceive('affectingStatement')->once()->with('insert into `users` (`email`, `name`) values (?, ?), (?, ?) on duplicate key update `email` = values(`email`), `name` = values(`name`)', ['foo', 'bar', 'foo2', 'bar2'])->andReturn(2);
    $result = $builder->from('users')->upsert([['email' => 'foo', 'name' => 'bar'], ['name' => 'bar2', 'email' => 'foo2']], 'email');
    $this->assertEquals(2, $result);

    $builder = queryBuilderGetMySqlBuilder();
    $builder->getConnection()
        ->shouldReceive('getConfig')->with('use_upsert_alias')->andReturn(true)
        ->shouldReceive('affectingStatement')->once()->with('insert into `users` (`email`, `name`) values (?, ?), (?, ?) as laravel_upsert_alias on duplicate key update `email` = `laravel_upsert_alias`.`email`, `name` = `laravel_upsert_alias`.`name`', ['foo', 'bar', 'foo2', 'bar2'])->andReturn(2);
    $result = $builder->from('users')->upsert([['email' => 'foo', 'name' => 'bar'], ['name' => 'bar2', 'email' => 'foo2']], 'email');
    $this->assertEquals(2, $result);

    $builder = queryBuilderGetPostgresBuilder();
    $builder->getConnection()->shouldReceive('affectingStatement')->once()->with('insert into "users" ("email", "name") values (?, ?), (?, ?) on conflict ("email") do update set "email" = "excluded"."email", "name" = "excluded"."name"', ['foo', 'bar', 'foo2', 'bar2'])->andReturn(2);
    $result = $builder->from('users')->upsert([['email' => 'foo', 'name' => 'bar'], ['name' => 'bar2', 'email' => 'foo2']], 'email');
    $this->assertEquals(2, $result);

    $builder = queryBuilderGetSQLiteBuilder();
    $builder->getConnection()->shouldReceive('affectingStatement')->once()->with('insert into "users" ("email", "name") values (?, ?), (?, ?) on conflict ("email") do update set "email" = "excluded"."email", "name" = "excluded"."name"', ['foo', 'bar', 'foo2', 'bar2'])->andReturn(2);
    $result = $builder->from('users')->upsert([['email' => 'foo', 'name' => 'bar'], ['name' => 'bar2', 'email' => 'foo2']], 'email');
    $this->assertEquals(2, $result);

    $builder = queryBuilderGetSqlServerBuilder();
    $builder->getConnection()->shouldReceive('affectingStatement')->once()->with('merge [users] using (values (?, ?), (?, ?)) [laravel_source] ([email], [name]) on [laravel_source].[email] = [users].[email] when matched then update set [email] = [laravel_source].[email], [name] = [laravel_source].[name] when not matched then insert ([email], [name]) values ([email], [name]);', ['foo', 'bar', 'foo2', 'bar2'])->andReturn(2);
    $result = $builder->from('users')->upsert([['email' => 'foo', 'name' => 'bar'], ['name' => 'bar2', 'email' => 'foo2']], 'email');
    $this->assertEquals(2, $result);
});

test('upsert method with update columns', function () {
    $builder = queryBuilderGetMySqlBuilder();
    $builder->getConnection()
        ->shouldReceive('getConfig')->with('use_upsert_alias')->andReturn(false)
        ->shouldReceive('affectingStatement')->once()->with('insert into `users` (`email`, `name`) values (?, ?), (?, ?) on duplicate key update `name` = values(`name`)', ['foo', 'bar', 'foo2', 'bar2'])->andReturn(2);
    $result = $builder->from('users')->upsert([['email' => 'foo', 'name' => 'bar'], ['name' => 'bar2', 'email' => 'foo2']], 'email', ['name']);
    $this->assertEquals(2, $result);

    $builder = queryBuilderGetMySqlBuilder();
    $builder->getConnection()
        ->shouldReceive('getConfig')->with('use_upsert_alias')->andReturn(true)
        ->shouldReceive('affectingStatement')->once()->with('insert into `users` (`email`, `name`) values (?, ?), (?, ?) as laravel_upsert_alias on duplicate key update `name` = `laravel_upsert_alias`.`name`', ['foo', 'bar', 'foo2', 'bar2'])->andReturn(2);
    $result = $builder->from('users')->upsert([['email' => 'foo', 'name' => 'bar'], ['name' => 'bar2', 'email' => 'foo2']], 'email', ['name']);
    $this->assertEquals(2, $result);

    $builder = queryBuilderGetPostgresBuilder();
    $builder->getConnection()->shouldReceive('affectingStatement')->once()->with('insert into "users" ("email", "name") values (?, ?), (?, ?) on conflict ("email") do update set "name" = "excluded"."name"', ['foo', 'bar', 'foo2', 'bar2'])->andReturn(2);
    $result = $builder->from('users')->upsert([['email' => 'foo', 'name' => 'bar'], ['name' => 'bar2', 'email' => 'foo2']], 'email', ['name']);
    $this->assertEquals(2, $result);

    $builder = queryBuilderGetSQLiteBuilder();
    $builder->getConnection()->shouldReceive('affectingStatement')->once()->with('insert into "users" ("email", "name") values (?, ?), (?, ?) on conflict ("email") do update set "name" = "excluded"."name"', ['foo', 'bar', 'foo2', 'bar2'])->andReturn(2);
    $result = $builder->from('users')->upsert([['email' => 'foo', 'name' => 'bar'], ['name' => 'bar2', 'email' => 'foo2']], 'email', ['name']);
    $this->assertEquals(2, $result);

    $builder = queryBuilderGetSqlServerBuilder();
    $builder->getConnection()->shouldReceive('affectingStatement')->once()->with('merge [users] using (values (?, ?), (?, ?)) [laravel_source] ([email], [name]) on [laravel_source].[email] = [users].[email] when matched then update set [name] = [laravel_source].[name] when not matched then insert ([email], [name]) values ([email], [name]);', ['foo', 'bar', 'foo2', 'bar2'])->andReturn(2);
    $result = $builder->from('users')->upsert([['email' => 'foo', 'name' => 'bar'], ['name' => 'bar2', 'email' => 'foo2']], 'email', ['name']);
    $this->assertEquals(2, $result);
});

test('update method with joins', function () {
    $builder = queryBuilderGetBuilder();
    $builder->getConnection()->shouldReceive('update')->once()->with('update "users" inner join "orders" on "users"."id" = "orders"."user_id" set "email" = ?, "name" = ? where "users"."id" = ?', ['foo', 'bar', 1])->andReturn(1);
    $result = $builder->from('users')->join('orders', 'users.id', '=', 'orders.user_id')->where('users.id', '=', 1)->update(['email' => 'foo', 'name' => 'bar']);
    $this->assertEquals(1, $result);

    $builder = queryBuilderGetBuilder();
    $builder->getConnection()->shouldReceive('update')->once()->with('update "users" inner join "orders" on "users"."id" = "orders"."user_id" and "users"."id" = ? set "email" = ?, "name" = ?', [1, 'foo', 'bar'])->andReturn(1);
    $result = $builder->from('users')->join('orders', function ($join) {
        $join->on('users.id', '=', 'orders.user_id')
            ->where('users.id', '=', 1);
    })->update(['email' => 'foo', 'name' => 'bar']);
    $this->assertEquals(1, $result);
});

test('update method with joins on sql server', function () {
    $builder = queryBuilderGetSqlServerBuilder();
    $builder->getConnection()->shouldReceive('update')->once()->with('update [users] set [email] = ?, [name] = ? from [users] inner join [orders] on [users].[id] = [orders].[user_id] where [users].[id] = ?', ['foo', 'bar', 1])->andReturn(1);
    $result = $builder->from('users')->join('orders', 'users.id', '=', 'orders.user_id')->where('users.id', '=', 1)->update(['email' => 'foo', 'name' => 'bar']);
    $this->assertEquals(1, $result);

    $builder = queryBuilderGetSqlServerBuilder();
    $builder->getConnection()->shouldReceive('update')->once()->with('update [users] set [email] = ?, [name] = ? from [users] inner join [orders] on [users].[id] = [orders].[user_id] and [users].[id] = ?', ['foo', 'bar', 1])->andReturn(1);
    $result = $builder->from('users')->join('orders', function ($join) {
        $join->on('users.id', '=', 'orders.user_id')
            ->where('users.id', '=', 1);
    })->update(['email' => 'foo', 'name' => 'bar']);
    $this->assertEquals(1, $result);
});

test('update method with joins on my sql', function () {
    $builder = queryBuilderGetMySqlBuilder();
    $builder->getConnection()->shouldReceive('update')->once()->with('update `users` inner join `orders` on `users`.`id` = `orders`.`user_id` set `email` = ?, `name` = ? where `users`.`id` = ?', ['foo', 'bar', 1])->andReturn(1);
    $result = $builder->from('users')->join('orders', 'users.id', '=', 'orders.user_id')->where('users.id', '=', 1)->update(['email' => 'foo', 'name' => 'bar']);
    $this->assertEquals(1, $result);

    $builder = queryBuilderGetMySqlBuilder();
    $builder->getConnection()->shouldReceive('update')->once()->with('update `users` inner join `orders` on `users`.`id` = `orders`.`user_id` and `users`.`id` = ? set `email` = ?, `name` = ?', [1, 'foo', 'bar'])->andReturn(1);
    $result = $builder->from('users')->join('orders', function ($join) {
        $join->on('users.id', '=', 'orders.user_id')
            ->where('users.id', '=', 1);
    })->update(['email' => 'foo', 'name' => 'bar']);
    $this->assertEquals(1, $result);
});

test('update method with joins on s q lite', function () {
    $builder = queryBuilderGetSQLiteBuilder();
    $builder->getConnection()->shouldReceive('update')->once()->with('update "users" set "email" = ?, "name" = ? where "rowid" in (select "users"."rowid" from "users" where "users"."id" > ? order by "id" asc limit 3)', ['foo', 'bar', 1])->andReturn(1);
    $result = $builder->from('users')->where('users.id', '>', 1)->limit(3)->oldest('id')->update(['email' => 'foo', 'name' => 'bar']);
    $this->assertEquals(1, $result);

    $builder = queryBuilderGetSQLiteBuilder();
    $builder->getConnection()->shouldReceive('update')->once()->with('update "users" set "email" = ?, "name" = ? where "rowid" in (select "users"."rowid" from "users" inner join "orders" on "users"."id" = "orders"."user_id" where "users"."id" = ?)', ['foo', 'bar', 1])->andReturn(1);
    $result = $builder->from('users')->join('orders', 'users.id', '=', 'orders.user_id')->where('users.id', '=', 1)->update(['email' => 'foo', 'name' => 'bar']);
    $this->assertEquals(1, $result);

    $builder = queryBuilderGetSQLiteBuilder();
    $builder->getConnection()->shouldReceive('update')->once()->with('update "users" set "email" = ?, "name" = ? where "rowid" in (select "users"."rowid" from "users" inner join "orders" on "users"."id" = "orders"."user_id" and "users"."id" = ?)', ['foo', 'bar', 1])->andReturn(1);
    $result = $builder->from('users')->join('orders', function ($join) {
        $join->on('users.id', '=', 'orders.user_id')
            ->where('users.id', '=', 1);
    })->update(['email' => 'foo', 'name' => 'bar']);
    $this->assertEquals(1, $result);

    $builder = queryBuilderGetSQLiteBuilder();
    $builder->getConnection()->shouldReceive('update')->once()->with('update "users" as "u" set "email" = ?, "name" = ? where "rowid" in (select "u"."rowid" from "users" as "u" inner join "orders" as "o" on "u"."id" = "o"."user_id")', ['foo', 'bar'])->andReturn(1);
    $result = $builder->from('users as u')->join('orders as o', 'u.id', '=', 'o.user_id')->update(['email' => 'foo', 'name' => 'bar']);
    $this->assertEquals(1, $result);
});

test('update method with joins and aliases on sql server', function () {
    $builder = queryBuilderGetSqlServerBuilder();
    $builder->getConnection()->shouldReceive('update')->once()->with('update [u] set [email] = ?, [name] = ? from [users] as [u] inner join [orders] on [u].[id] = [orders].[user_id] where [u].[id] = ?', ['foo', 'bar', 1])->andReturn(1);
    $result = $builder->from('users as u')->join('orders', 'u.id', '=', 'orders.user_id')->where('u.id', '=', 1)->update(['email' => 'foo', 'name' => 'bar']);
    $this->assertEquals(1, $result);
});

test('update method without joins on postgres', function () {
    $builder = queryBuilderGetPostgresBuilder();
    $builder->getConnection()->shouldReceive('update')->once()->with('update "users" set "email" = ?, "name" = ? where "id" = ?', ['foo', 'bar', 1])->andReturn(1);
    $result = $builder->from('users')->where('id', '=', 1)->update(['users.email' => 'foo', 'name' => 'bar']);
    $this->assertEquals(1, $result);

    $builder = queryBuilderGetPostgresBuilder();
    $builder->getConnection()->shouldReceive('update')->once()->with('update "users" set "email" = ?, "name" = ? where "id" = ?', ['foo', 'bar', 1])->andReturn(1);
    $result = $builder->from('users')->where('id', '=', 1)->selectRaw('?', ['ignore'])->update(['users.email' => 'foo', 'name' => 'bar']);
    $this->assertEquals(1, $result);

    $builder = queryBuilderGetPostgresBuilder();
    $builder->getConnection()->shouldReceive('update')->once()->with('update "users"."users" set "email" = ?, "name" = ? where "id" = ?', ['foo', 'bar', 1])->andReturn(1);
    $result = $builder->from('users.users')->where('id', '=', 1)->selectRaw('?', ['ignore'])->update(['users.users.email' => 'foo', 'name' => 'bar']);
    $this->assertEquals(1, $result);
});

test('update method with joins on postgres', function () {
    $builder = queryBuilderGetPostgresBuilder();
    $builder->getConnection()->shouldReceive('update')->once()->with('update "users" set "email" = ?, "name" = ? where "ctid" in (select "users"."ctid" from "users" inner join "orders" on "users"."id" = "orders"."user_id" where "users"."id" = ?)', ['foo', 'bar', 1])->andReturn(1);
    $result = $builder->from('users')->join('orders', 'users.id', '=', 'orders.user_id')->where('users.id', '=', 1)->update(['email' => 'foo', 'name' => 'bar']);
    $this->assertEquals(1, $result);

    $builder = queryBuilderGetPostgresBuilder();
    $builder->getConnection()->shouldReceive('update')->once()->with('update "users" set "email" = ?, "name" = ? where "ctid" in (select "users"."ctid" from "users" inner join "orders" on "users"."id" = "orders"."user_id" and "users"."id" = ?)', ['foo', 'bar', 1])->andReturn(1);
    $result = $builder->from('users')->join('orders', function ($join) {
        $join->on('users.id', '=', 'orders.user_id')
            ->where('users.id', '=', 1);
    })->update(['email' => 'foo', 'name' => 'bar']);
    $this->assertEquals(1, $result);

    $builder = queryBuilderGetPostgresBuilder();
    $builder->getConnection()->shouldReceive('update')->once()->with('update "users" set "email" = ?, "name" = ? where "ctid" in (select "users"."ctid" from "users" inner join "orders" on "users"."id" = "orders"."user_id" and "users"."id" = ? where "name" = ?)', ['foo', 'bar', 1, 'baz'])->andReturn(1);
    $result = $builder->from('users')
        ->join('orders', function ($join) {
            $join->on('users.id', '=', 'orders.user_id')
                ->where('users.id', '=', 1);
        })->where('name', 'baz')
        ->update(['email' => 'foo', 'name' => 'bar']);
    $this->assertEquals(1, $result);
});

test('update from method with joins on postgres', function () {
    $builder = queryBuilderGetPostgresBuilder();
    $builder->getConnection()->shouldReceive('update')->once()->with('update "users" set "email" = ?, "name" = ? from "orders" where "users"."id" = ? and "users"."id" = "orders"."user_id"', ['foo', 'bar', 1])->andReturn(1);
    $result = $builder->from('users')->join('orders', 'users.id', '=', 'orders.user_id')->where('users.id', '=', 1)->updateFrom(['email' => 'foo', 'name' => 'bar']);
    $this->assertEquals(1, $result);

    $builder = queryBuilderGetPostgresBuilder();
    $builder->getConnection()->shouldReceive('update')->once()->with('update "users" set "email" = ?, "name" = ? from "orders" where "users"."id" = "orders"."user_id" and "users"."id" = ?', ['foo', 'bar', 1])->andReturn(1);
    $result = $builder->from('users')->join('orders', function ($join) {
        $join->on('users.id', '=', 'orders.user_id')
            ->where('users.id', '=', 1);
    })->updateFrom(['email' => 'foo', 'name' => 'bar']);
    $this->assertEquals(1, $result);

    $builder = queryBuilderGetPostgresBuilder();
    $builder->getConnection()->shouldReceive('update')->once()->with('update "users" set "email" = ?, "name" = ? from "orders" where "name" = ? and "users"."id" = "orders"."user_id" and "users"."id" = ?', ['foo', 'bar', 'baz', 1])->andReturn(1);
    $result = $builder->from('users')
        ->join('orders', function ($join) {
            $join->on('users.id', '=', 'orders.user_id')
                ->where('users.id', '=', 1);
        })->where('name', 'baz')
        ->updateFrom(['email' => 'foo', 'name' => 'bar']);
    $this->assertEquals(1, $result);
});

test('update method respects raw', function () {
    $builder = queryBuilderGetBuilder();
    $builder->getConnection()->shouldReceive('update')->once()->with('update "users" set "email" = foo, "name" = ? where "id" = ?', ['bar', 1])->andReturn(1);
    $result = $builder->from('users')->where('id', '=', 1)->update(['email' => new Raw('foo'), 'name' => 'bar']);
    $this->assertEquals(1, $result);
});

test('update method works with query as value', function () {
    $builder = queryBuilderGetBuilder();
    $subQueryBuilder = queryBuilderGetBuilder();
    $builder->getConnection()->shouldReceive('update')->once()->with('update "users" set "credits" = (select sum(credits) from "transactions" where "transactions"."user_id" = "users"."id" and "type" = ?) where "id" = ?', ['foo', 1])->andReturn(1);
    $result = $builder->from('users')->where('id', '=', 1)->update(['credits' => $subQueryBuilder->from('transactions')->selectRaw('sum(credits)')->whereColumn('transactions.user_id', 'users.id')->where('type', 'foo')]);
    $this->assertEquals(1, $result);

    $builder = queryBuilderGetBuilder();
    $subQueryBuilder = new InstrumentBuilder(queryBuilderGetBuilder());
    $builder->getConnection()->shouldReceive('update')->once()->with('update "users" set "credits" = (select sum(credits) from "transactions" where "transactions"."user_id" = "users"."id" and "type" = ?) where "id" = ?', ['foo', 1])->andReturn(1);
    $result = $builder->from('users')->where('id', '=', 1)->update(['credits' => $subQueryBuilder->from('transactions')->selectRaw('sum(credits)')->whereColumn('transactions.user_id', 'users.id')->where('type', 'foo')]);
    $this->assertEquals(1, $result);
});

test('update or insert method', function () {
    $builder = m::mock(Builder::class.'[where,exists,insert]', [
        $connection = m::mock(Connection::class),
        new Grammar($connection),
        m::mock(Processor::class),
    ]);

    $builder->shouldReceive('where')->once()->with(['email' => 'foo'])->andReturn(m::self());
    $builder->shouldReceive('exists')->once()->andReturn(false);
    $builder->shouldReceive('insert')->once()->with(['email' => 'foo', 'name' => 'bar'])->andReturn(true);

    $this->assertTrue($builder->updateOrInsert(['email' => 'foo'], ['name' => 'bar']));

    $builder = m::mock(Builder::class.'[where,exists,update]', [
        $connection = m::mock(Connection::class),
        new Grammar($connection),
        m::mock(Processor::class),
    ]);

    $builder->shouldReceive('where')->once()->with(['email' => 'foo'])->andReturn(m::self());
    $builder->shouldReceive('exists')->once()->andReturn(true);
    $builder->shouldReceive('take')->andReturnSelf();
    $builder->shouldReceive('update')->once()->with(['name' => 'bar'])->andReturn(1);

    $this->assertTrue($builder->updateOrInsert(['email' => 'foo'], ['name' => 'bar']));
});

test('update or insert method works with empty update values', function () {
    $builder = m::spy(Builder::class.'[where,exists,update]', [
        $connection = m::mock(Connection::class),
        new Grammar($connection),
        m::mock(Processor::class),
    ]);

    $builder->shouldReceive('where')->once()->with(['email' => 'foo'])->andReturn(m::self());
    $builder->shouldReceive('exists')->once()->andReturn(true);

    $this->assertTrue($builder->updateOrInsert(['email' => 'foo']));
    $builder->shouldNotHaveReceived('update');
});

test('delete method', function () {
    $builder = queryBuilderGetBuilder();
    $builder->getConnection()->shouldReceive('delete')->once()->with('delete from "users" where "email" = ?', ['foo'])->andReturn(1);
    $result = $builder->from('users')->where('email', '=', 'foo')->delete();
    $this->assertEquals(1, $result);

    $builder = queryBuilderGetBuilder();
    $builder->getConnection()->shouldReceive('delete')->once()->with('delete from "users" where "users"."id" = ?', [1])->andReturn(1);
    $result = $builder->from('users')->delete(1);
    $this->assertEquals(1, $result);

    $builder = queryBuilderGetBuilder();
    $builder->getConnection()->shouldReceive('delete')->once()->with('delete from "users" where "users"."id" = ?', [1])->andReturn(1);
    $result = $builder->from('users')->selectRaw('?', ['ignore'])->delete(1);
    $this->assertEquals(1, $result);

    $builder = queryBuilderGetSQLiteBuilder();
    $builder->getConnection()->shouldReceive('delete')->once()->with('delete from "users" where "rowid" in (select "users"."rowid" from "users" where "email" = ? order by "id" asc limit 1)', ['foo'])->andReturn(1);
    $result = $builder->from('users')->where('email', '=', 'foo')->orderBy('id')->limit(1)->delete();
    $this->assertEquals(1, $result);

    $builder = queryBuilderGetMySqlBuilder();
    $builder->getConnection()->shouldReceive('delete')->once()->with('delete from `users` where `email` = ? order by `id` asc limit 1', ['foo'])->andReturn(1);
    $result = $builder->from('users')->where('email', '=', 'foo')->orderBy('id')->limit(1)->delete();
    $this->assertEquals(1, $result);

    $builder = queryBuilderGetSqlServerBuilder();
    $builder->getConnection()->shouldReceive('delete')->once()->with('delete from [users] where [email] = ?', ['foo'])->andReturn(1);
    $result = $builder->from('users')->where('email', '=', 'foo')->delete();
    $this->assertEquals(1, $result);

    $builder = queryBuilderGetSqlServerBuilder();
    $builder->getConnection()->shouldReceive('delete')->once()->with('delete top (1) from [users] where [email] = ?', ['foo'])->andReturn(1);
    $result = $builder->from('users')->where('email', '=', 'foo')->orderBy('id')->limit(1)->delete();
    $this->assertEquals(1, $result);
});

test('delete with join method', function () {
    $builder = queryBuilderGetSQLiteBuilder();
    $builder->getConnection()->shouldReceive('delete')->once()->with('delete from "users" where "rowid" in (select "users"."rowid" from "users" inner join "contacts" on "users"."id" = "contacts"."id" where "users"."email" = ? order by "users"."id" asc limit 1)', ['foo'])->andReturn(1);
    $result = $builder->from('users')->join('contacts', 'users.id', '=', 'contacts.id')->where('users.email', '=', 'foo')->orderBy('users.id')->limit(1)->delete();
    $this->assertEquals(1, $result);

    $builder = queryBuilderGetSQLiteBuilder();
    $builder->getConnection()->shouldReceive('delete')->once()->with('delete from "users" as "u" where "rowid" in (select "u"."rowid" from "users" as "u" inner join "contacts" as "c" on "u"."id" = "c"."id")', [])->andReturn(1);
    $result = $builder->from('users as u')->join('contacts as c', 'u.id', '=', 'c.id')->delete();
    $this->assertEquals(1, $result);

    $builder = queryBuilderGetMySqlBuilder();
    $builder->getConnection()->shouldReceive('delete')->once()->with('delete `users` from `users` inner join `contacts` on `users`.`id` = `contacts`.`id` where `email` = ?', ['foo'])->andReturn(1);
    $result = $builder->from('users')->join('contacts', 'users.id', '=', 'contacts.id')->where('email', '=', 'foo')->orderBy('id')->limit(1)->delete();
    $this->assertEquals(1, $result);

    $builder = queryBuilderGetMySqlBuilder();
    $builder->getConnection()->shouldReceive('delete')->once()->with('delete `a` from `users` as `a` inner join `users` as `b` on `a`.`id` = `b`.`user_id` where `email` = ?', ['foo'])->andReturn(1);
    $result = $builder->from('users AS a')->join('users AS b', 'a.id', '=', 'b.user_id')->where('email', '=', 'foo')->orderBy('id')->limit(1)->delete();
    $this->assertEquals(1, $result);

    $builder = queryBuilderGetMySqlBuilder();
    $builder->getConnection()->shouldReceive('delete')->once()->with('delete `users` from `users` inner join `contacts` on `users`.`id` = `contacts`.`id` where `users`.`id` = ?', [1])->andReturn(1);
    $result = $builder->from('users')->join('contacts', 'users.id', '=', 'contacts.id')->orderBy('id')->limit(1)->delete(1);
    $this->assertEquals(1, $result);

    $builder = queryBuilderGetSqlServerBuilder();
    $builder->getConnection()->shouldReceive('delete')->once()->with('delete [users] from [users] inner join [contacts] on [users].[id] = [contacts].[id] where [email] = ?', ['foo'])->andReturn(1);
    $result = $builder->from('users')->join('contacts', 'users.id', '=', 'contacts.id')->where('email', '=', 'foo')->delete();
    $this->assertEquals(1, $result);

    $builder = queryBuilderGetSqlServerBuilder();
    $builder->getConnection()->shouldReceive('delete')->once()->with('delete [a] from [users] as [a] inner join [users] as [b] on [a].[id] = [b].[user_id] where [email] = ?', ['foo'])->andReturn(1);
    $result = $builder->from('users AS a')->join('users AS b', 'a.id', '=', 'b.user_id')->where('email', '=', 'foo')->orderBy('id')->limit(1)->delete();
    $this->assertEquals(1, $result);

    $builder = queryBuilderGetSqlServerBuilder();
    $builder->getConnection()->shouldReceive('delete')->once()->with('delete [users] from [users] inner join [contacts] on [users].[id] = [contacts].[id] where [users].[id] = ?', [1])->andReturn(1);
    $result = $builder->from('users')->join('contacts', 'users.id', '=', 'contacts.id')->delete(1);
    $this->assertEquals(1, $result);

    $builder = queryBuilderGetPostgresBuilder();
    $builder->getConnection()->shouldReceive('delete')->once()->with('delete from "users" where "ctid" in (select "users"."ctid" from "users" inner join "contacts" on "users"."id" = "contacts"."id" where "users"."email" = ?)', ['foo'])->andReturn(1);
    $result = $builder->from('users')->join('contacts', 'users.id', '=', 'contacts.id')->where('users.email', '=', 'foo')->delete();
    $this->assertEquals(1, $result);

    $builder = queryBuilderGetPostgresBuilder();
    $builder->getConnection()->shouldReceive('delete')->once()->with('delete from "users" as "a" where "ctid" in (select "a"."ctid" from "users" as "a" inner join "users" as "b" on "a"."id" = "b"."user_id" where "email" = ? order by "id" asc limit 1)', ['foo'])->andReturn(1);
    $result = $builder->from('users AS a')->join('users AS b', 'a.id', '=', 'b.user_id')->where('email', '=', 'foo')->orderBy('id')->limit(1)->delete();
    $this->assertEquals(1, $result);

    $builder = queryBuilderGetPostgresBuilder();
    $builder->getConnection()->shouldReceive('delete')->once()->with('delete from "users" where "ctid" in (select "users"."ctid" from "users" inner join "contacts" on "users"."id" = "contacts"."id" where "users"."id" = ? order by "id" asc limit 1)', [1])->andReturn(1);
    $result = $builder->from('users')->join('contacts', 'users.id', '=', 'contacts.id')->orderBy('id')->limit(1)->delete(1);
    $this->assertEquals(1, $result);

    $builder = queryBuilderGetPostgresBuilder();
    $builder->getConnection()->shouldReceive('delete')->once()->with('delete from "users" where "ctid" in (select "users"."ctid" from "users" inner join "contacts" on "users"."id" = "contacts"."user_id" and "users"."id" = ? where "name" = ?)', [1, 'baz'])->andReturn(1);
    $result = $builder->from('users')
        ->join('contacts', function ($join) {
            $join->on('users.id', '=', 'contacts.user_id')
                ->where('users.id', '=', 1);
        })->where('name', 'baz')
        ->delete();
    $this->assertEquals(1, $result);

    $builder = queryBuilderGetPostgresBuilder();
    $builder->getConnection()->shouldReceive('delete')->once()->with('delete from "users" where "ctid" in (select "users"."ctid" from "users" inner join "contacts" on "users"."id" = "contacts"."id")', [])->andReturn(1);
    $result = $builder->from('users')->join('contacts', 'users.id', '=', 'contacts.id')->delete();
    $this->assertEquals(1, $result);
});

test('truncate method', function () {
    $builder = queryBuilderGetBuilder();
    $connection = $builder->getConnection();
    $connection->shouldReceive('statement')->once()->with('truncate table "users"', []);
    $builder->from('users')->truncate();

    $builder = queryBuilderGetSQLiteBuilder();
    $builder->getConnection()->shouldReceive('getSchemaBuilder->parseSchemaAndTable')->andReturn([null, 'users']);
    $builder->from('users');
    $this->assertEquals([
        'delete from sqlite_sequence where name = ?' => ['users'],
        'delete from "users"' => [],
    ], $builder->getGrammar()->compileTruncate($builder));
});

test('truncate method with prefix', function () {
    $builder = queryBuilderGetBuilder(prefix: 'prefix_');
    $connection = $builder->getConnection();
    $connection->shouldReceive('statement')->once()->with('truncate table "prefix_users"', []);
    $builder->from('users')->truncate();

    $builder = queryBuilderGetSQLiteBuilder(prefix: 'prefix_');
    $builder->getConnection()->shouldReceive('getSchemaBuilder->parseSchemaAndTable')->andReturn([null, 'users']);
    $builder->from('users');
    $this->assertEquals([
        'delete from sqlite_sequence where name = ?' => ['prefix_users'],
        'delete from "prefix_users"' => [],
    ], $builder->getGrammar()->compileTruncate($builder));
});

test('truncate method with prefix and schema', function () {
    $builder = queryBuilderGetBuilder(prefix: 'prefix_');
    $connection = $builder->getConnection();
    $connection->shouldReceive('statement')->once()->with('truncate table "my_schema"."prefix_users"', []);
    $builder->from('my_schema.users')->truncate();

    $builder = queryBuilderGetSQLiteBuilder(prefix: 'prefix_');
    $builder->getConnection()->shouldReceive('getSchemaBuilder->parseSchemaAndTable')->andReturn(['my_schema', 'users']);
    $builder->from('my_schema.users');
    $this->assertEquals([
        'delete from "my_schema".sqlite_sequence where name = ?' => ['prefix_users'],
        'delete from "my_schema"."prefix_users"' => [],
    ], $builder->getGrammar()->compileTruncate($builder));
});

test('preserve adds closure to array', function () {
    $builder = queryBuilderGetBuilder();
    $builder->beforeQuery(function () {
    });
    $this->assertCount(1, $builder->beforeQueryCallbacks);
    $this->assertInstanceOf(Closure::class, $builder->beforeQueryCallbacks[0]);
});

test('apply preserve cleans array', function () {
    $builder = queryBuilderGetBuilder();
    $builder->beforeQuery(function () {
    });
    $this->assertCount(1, $builder->beforeQueryCallbacks);
    $builder->applyBeforeQueryCallbacks();
    $this->assertCount(0, $builder->beforeQueryCallbacks);
});

test('preserved are applied by to sql', function () {
    $builder = queryBuilderGetBuilder();
    $builder->beforeQuery(function ($builder) {
        $builder->where('foo', 'bar');
    });
    $this->assertSame('select * where "foo" = ?', $builder->toSql());
    $this->assertEquals(['bar'], $builder->getBindings());
});

test('preserved are applied by insert', function () {
    $builder = queryBuilderGetBuilder();
    $builder->getConnection()->shouldReceive('insert')->once()->with('insert into "users" ("email") values (?)', ['foo']);
    $builder->beforeQuery(function ($builder) {
        $builder->from('users');
    });
    $builder->insert(['email' => 'foo']);
});

test('preserved are applied by insert get id', function () {
    $this->called = false;
    $builder = queryBuilderGetBuilder();
    $builder->getProcessor()->shouldReceive('processInsertGetId')->once()->with($builder, 'insert into "users" ("email") values (?)', ['foo'], 'id');
    $builder->beforeQuery(function ($builder) {
        $builder->from('users');
    });
    $builder->insertGetId(['email' => 'foo'], 'id');
});

test('preserved are applied by insert using', function () {
    $builder = queryBuilderGetBuilder();
    $builder->getConnection()->shouldReceive('affectingStatement')->once()->with('insert into "users" ("email") select *', []);
    $builder->beforeQuery(function ($builder) {
        $builder->from('users');
    });
    $builder->insertUsing(['email'], queryBuilderGetBuilder());
});

test('preserved are applied by upsert', function () {
    $builder = queryBuilderGetMySqlBuilder();
    $builder->getConnection()
        ->shouldReceive('getConfig')->with('use_upsert_alias')->andReturn(false)
        ->shouldReceive('affectingStatement')->once()->with('insert into `users` (`email`) values (?) on duplicate key update `email` = values(`email`)', ['foo']);
    $builder->beforeQuery(function ($builder) {
        $builder->from('users');
    });
    $builder->upsert(['email' => 'foo'], 'id');

    $builder = queryBuilderGetMySqlBuilder();
    $builder->getConnection()
        ->shouldReceive('getConfig')->with('use_upsert_alias')->andReturn(true)
        ->shouldReceive('affectingStatement')->once()->with('insert into `users` (`email`) values (?) as laravel_upsert_alias on duplicate key update `email` = `laravel_upsert_alias`.`email`', ['foo']);
    $builder->beforeQuery(function ($builder) {
        $builder->from('users');
    });
    $builder->upsert(['email' => 'foo'], 'id');
});

test('preserved are applied by update', function () {
    $builder = queryBuilderGetBuilder();
    $builder->getConnection()->shouldReceive('update')->once()->with('update "users" set "email" = ? where "id" = ?', ['foo', 1]);
    $builder->from('users')->beforeQuery(function ($builder) {
        $builder->where('id', 1);
    });
    $builder->update(['email' => 'foo']);
});

test('preserved are applied by delete', function () {
    $builder = queryBuilderGetBuilder();
    $builder->getConnection()->shouldReceive('delete')->once()->with('delete from "users"', []);
    $builder->beforeQuery(function ($builder) {
        $builder->from('users');
    });
    $builder->delete();
});

test('preserved are applied by truncate', function () {
    $builder = queryBuilderGetBuilder();
    $builder->getConnection()->shouldReceive('statement')->once()->with('truncate table "users"', []);
    $builder->beforeQuery(function ($builder) {
        $builder->from('users');
    });
    $builder->truncate();
});

test('preserved are applied by exists', function () {
    $builder = queryBuilderGetBuilder();
    $builder->getConnection()->shouldReceive('select')->once()->with('select exists(select * from "users") as "exists"', [], true);
    $builder->beforeQuery(function ($builder) {
        $builder->from('users');
    });
    $builder->exists();
});

test('postgres insert get id', function () {
    $builder = queryBuilderGetPostgresBuilder();
    $builder->getProcessor()->shouldReceive('processInsertGetId')->once()->with($builder, 'insert into "users" ("email") values (?) returning "id"', ['foo'], 'id')->andReturn(1);
    $result = $builder->from('users')->insertGetId(['email' => 'foo'], 'id');
    $this->assertEquals(1, $result);
});

test('my sql wrapping', function () {
    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users');
    $this->assertSame('select * from `users`', $builder->toSql());
});

test('my sql update wrapping json', function () {
    $connection = $this->createMock(Connection::class);
    $grammar = new MySqlGrammar($connection);
    $processor = m::mock(Processor::class);

    $connection->expects($this->once())
        ->method('update')
        ->with(
            'update `users` set `name` = json_set(`name`, \'$."first_name"\', ?), `name` = json_set(`name`, \'$."last_name"\', ?) where `active` = ?',
            ['John', 'Doe', 1]
        );

    $builder = new Builder($connection, $grammar, $processor);

    $builder->from('users')->where('active', '=', 1)->update(['name->first_name' => 'John', 'name->last_name' => 'Doe']);
});

test('my sql update wrapping nested json', function () {
    $connection = $this->createMock(Connection::class);
    $grammar = new MySqlGrammar($connection);
    $processor = m::mock(Processor::class);

    $connection->expects($this->once())
        ->method('update')
        ->with(
            'update `users` set `meta` = json_set(`meta`, \'$."name"."first_name"\', ?), `meta` = json_set(`meta`, \'$."name"."last_name"\', ?) where `active` = ?',
            ['John', 'Doe', 1]
        );

    $builder = new Builder($connection, $grammar, $processor);

    $builder->from('users')->where('active', '=', 1)->update(['meta->name->first_name' => 'John', 'meta->name->last_name' => 'Doe']);
});

test('my sql update wrapping json array', function () {
    $connection = $this->createMock(Connection::class);
    $grammar = new MySqlGrammar($connection);
    $processor = m::mock(Processor::class);

    $connection->expects($this->once())
        ->method('update')
        ->with(
            'update `users` set `options` = ?, `meta` = json_set(`meta`, \'$."tags"\', cast(? as json)), `group_id` = 45, `created_at` = ? where `active` = ?',
            [
                json_encode(['2fa' => false, 'presets' => ['laravel', 'vue']]),
                json_encode(['white', 'large']),
                new DateTime('2019-08-06'),
                1,
            ]
        );

    $builder = new Builder($connection, $grammar, $processor);
    $builder->from('users')->where('active', 1)->update([
        'options' => ['2fa' => false, 'presets' => ['laravel', 'vue']],
        'meta->tags' => ['white', 'large'],
        'group_id' => new Raw('45'),
        'created_at' => new DateTime('2019-08-06'),
    ]);
});

test('my sql update wrapping json path array index', function () {
    $connection = $this->createMock(Connection::class);
    $grammar = new MySqlGrammar($connection);
    $processor = m::mock(Processor::class);

    $connection->expects($this->once())
        ->method('update')
        ->with(
            'update `users` set `options` = json_set(`options`, \'$[1]."2fa"\', false), `meta` = json_set(`meta`, \'$."tags"[0][2]\', ?) where `active` = ?',
            [
                'large',
                1,
            ]
        );

    $builder = new Builder($connection, $grammar, $processor);
    $builder->from('users')->where('active', 1)->update([
        'options->[1]->2fa' => false,
        'meta->tags[0][2]' => 'large',
    ]);
});

test('my sql update with json prepares bindings correctly', function () {
    $connection = queryBuilderGetConnection();
    $grammar = new MySqlGrammar($connection);
    $processor = m::mock(Processor::class);

    $connection->shouldReceive('update')
        ->once()
        ->with(
            'update `users` set `options` = json_set(`options`, \'$."enable"\', false), `updated_at` = ? where `id` = ?',
            ['2015-05-26 22:02:06', 0]
        );
    $builder = new Builder($connection, $grammar, $processor);
    $builder->from('users')->where('id', '=', 0)->update(['options->enable' => false, 'updated_at' => '2015-05-26 22:02:06']);

    $connection->shouldReceive('update')
        ->once()
        ->with(
            'update `users` set `options` = json_set(`options`, \'$."size"\', ?), `updated_at` = ? where `id` = ?',
            [45, '2015-05-26 22:02:06', 0]
        );
    $builder = new Builder($connection, $grammar, $processor);
    $builder->from('users')->where('id', '=', 0)->update(['options->size' => 45, 'updated_at' => '2015-05-26 22:02:06']);

    $builder = queryBuilderGetMySqlBuilder();
    $builder->getConnection()->shouldReceive('update')->once()->with('update `users` set `options` = json_set(`options`, \'$."size"\', ?)', [null]);
    $builder->from('users')->update(['options->size' => null]);

    $builder = queryBuilderGetMySqlBuilder();
    $builder->getConnection()->shouldReceive('update')->once()->with('update `users` set `options` = json_set(`options`, \'$."size"\', 45)', []);
    $builder->from('users')->update(['options->size' => new Raw('45')]);
});

test('postgres update wrapping json', function () {
    $builder = queryBuilderGetPostgresBuilder();
    $builder->getConnection()->shouldReceive('update')
        ->once()
        ->with('update "users" set "options" = jsonb_set("options"::jsonb, \'{"name","first_name"}\', ?)', ['"John"']);
    $builder->from('users')->update(['users.options->name->first_name' => 'John']);

    $builder = queryBuilderGetPostgresBuilder();
    $builder->getConnection()->shouldReceive('update')
        ->once()
        ->with('update "users" set "options" = jsonb_set("options"::jsonb, \'{"language"}\', \'null\')', []);
    $builder->from('users')->update(['options->language' => new Raw("'null'")]);
});

test('postgres update wrapping json array', function () {
    $builder = queryBuilderGetPostgresBuilder();
    $builder->getConnection()->shouldReceive('update')
        ->once()
        ->with('update "users" set "options" = ?, "meta" = jsonb_set("meta"::jsonb, \'{"tags"}\', ?), "group_id" = 45, "created_at" = ?', [
            json_encode(['2fa' => false, 'presets' => ['laravel', 'vue']]),
            json_encode(['white', 'large']),
            new DateTime('2019-08-06'),
        ]);

    $builder->from('users')->update([
        'options' => ['2fa' => false, 'presets' => ['laravel', 'vue']],
        'meta->tags' => ['white', 'large'],
        'group_id' => new Raw('45'),
        'created_at' => new DateTime('2019-08-06'),
    ]);
});

test('postgres update wrapping json path array index', function () {
    $builder = queryBuilderGetPostgresBuilder();
    $builder->getConnection()->shouldReceive('update')
        ->once()
        ->with('update "users" set "options" = jsonb_set("options"::jsonb, \'{1,"2fa"}\', ?), "meta" = jsonb_set("meta"::jsonb, \'{"tags",0,2}\', ?) where ("options"->1->\'2fa\')::jsonb = \'true\'::jsonb', [
            'false',
            '"large"',
        ]);

    $builder->from('users')->where('options->[1]->2fa', true)->update([
        'options->[1]->2fa' => false,
        'meta->tags[0][2]' => 'large',
    ]);
});

test('s q lite update wrapping json array', function () {
    $builder = queryBuilderGetSQLiteBuilder();

    $builder->getConnection()->shouldReceive('update')
        ->once()
        ->with('update "users" set "options" = ?, "group_id" = 45, "created_at" = ?', [
            json_encode(['2fa' => false, 'presets' => ['laravel', 'vue']]),
            new DateTime('2019-08-06'),
        ]);

    $builder->from('users')->update([
        'options' => ['2fa' => false, 'presets' => ['laravel', 'vue']],
        'group_id' => new Raw('45'),
        'created_at' => new DateTime('2019-08-06'),
    ]);
});

test('s q lite update wrapping nested json array', function () {
    $builder = queryBuilderGetSQLiteBuilder();
    $builder->getConnection()->shouldReceive('update')
        ->once()
        ->with('update "users" set "group_id" = 45, "created_at" = ?, "options" = json_patch(ifnull("options", json(\'{}\')), json(?))', [
            new DateTime('2019-08-06'),
            json_encode(['name' => 'Taylor', 'security' => ['2fa' => false, 'presets' => ['laravel', 'vue']], 'sharing' => ['twitter' => 'username']]),
        ]);

    $builder->from('users')->update([
        'options->name' => 'Taylor',
        'group_id' => new Raw('45'),
        'options->security' => ['2fa' => false, 'presets' => ['laravel', 'vue']],
        'options->sharing->twitter' => 'username',
        'created_at' => new DateTime('2019-08-06'),
    ]);
});

test('s q lite update wrapping json path array index', function () {
    $builder = queryBuilderGetSQLiteBuilder();
    $builder->getConnection()->shouldReceive('update')
        ->once()
        ->with('update "users" set "options" = json_patch(ifnull("options", json(\'{}\')), json(?)), "meta" = json_patch(ifnull("meta", json(\'{}\')), json(?)) where json_extract("options", \'$[1]."2fa"\') = true', [
            '{"[1]":{"2fa":false}}',
            '{"tags[0][2]":"large"}',
        ]);

    $builder->from('users')->where('options->[1]->2fa', true)->update([
        'options->[1]->2fa' => false,
        'meta->tags[0][2]' => 'large',
    ]);
});

test('my sql wrapping json with string', function () {
    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users')->where('items->sku', '=', 'foo-bar');
    $this->assertSame('select * from `users` where json_unquote(json_extract(`items`, \'$."sku"\')) = ?', $builder->toSql());
    $this->assertCount(1, $builder->getRawBindings()['where']);
    $this->assertSame('foo-bar', $builder->getRawBindings()['where'][0]);
});

test('my sql wrapping json with integer', function () {
    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users')->where('items->price', '=', 1);
    $this->assertSame('select * from `users` where json_unquote(json_extract(`items`, \'$."price"\')) = ?', $builder->toSql());
});

test('my sql wrapping json with double', function () {
    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users')->where('items->price', '=', 1.5);
    $this->assertSame('select * from `users` where json_unquote(json_extract(`items`, \'$."price"\')) = ?', $builder->toSql());
});

test('my sql wrapping json with boolean', function () {
    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users')->where('items->available', '=', true);
    $this->assertSame('select * from `users` where json_extract(`items`, \'$."available"\') = true', $builder->toSql());

    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users')->where(new Raw("items->'$.available'"), '=', true);
    $this->assertSame("select * from `users` where items->'$.available' = true", $builder->toSql());
});

test('my sql wrapping json with boolean and integer that looks like one', function () {
    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users')->where('items->available', '=', true)->where('items->active', '=', false)->where('items->number_available', '=', 0);
    $this->assertSame('select * from `users` where json_extract(`items`, \'$."available"\') = true and json_extract(`items`, \'$."active"\') = false and json_unquote(json_extract(`items`, \'$."number_available"\')) = ?', $builder->toSql());
});

test('json path escaping', function () {
    $expectedWithJsonEscaped = <<<'SQL'
select json_unquote(json_extract(`json`, '$."''))#"'))
SQL;

    $builder = queryBuilderGetMySqlBuilder();
    $builder->select("json->'))#");
    $this->assertEquals($expectedWithJsonEscaped, $builder->toSql());

    $builder = queryBuilderGetMySqlBuilder();
    $builder->select("json->\'))#");
    $this->assertEquals($expectedWithJsonEscaped, $builder->toSql());

    $builder = queryBuilderGetMySqlBuilder();
    $builder->select("json->\\'))#");
    $this->assertEquals($expectedWithJsonEscaped, $builder->toSql());

    $builder = queryBuilderGetMySqlBuilder();
    $builder->select("json->\\\'))#");
    $this->assertEquals($expectedWithJsonEscaped, $builder->toSql());
});

test('postgres json path escaping', function () {
    $builder = queryBuilderGetPostgresBuilder();
    $builder->select("json->'))#");
    $this->assertSame('select "json"->>\'\'\'))#\'', $builder->toSql());

    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('*')->from('users')->where("json->'))#", '=', 1);
    $this->assertSame('select * from "users" where "json"->>\'\'\'))#\' = ?', $builder->toSql());

    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('*')->from('users')->orderBy("json->'))#");
    $this->assertSame('select * from "users" order by "json"->>\'\'\'))#\' asc', $builder->toSql());

    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('*')->from('users')->whereJsonLength("json->'))#", 1);
    $this->assertSame('select * from "users" where jsonb_array_length(("json"->\'\'\'))#\')::jsonb) = ?', $builder->toSql());
});

test('postgres update json path escaping', function () {
    // The update path delimits its attributes with double quotes, but the
    // resulting path is still nested within a single quoted string literal,
    // so single quotes must be escaped there as well...
    $builder = queryBuilderGetPostgresBuilder();
    $builder->getConnection()->shouldReceive('update')
        ->once()
        ->with('update "users" set "options" = jsonb_set("options"::jsonb, \'{"\'\'))#"}\', ?)', ['"John"']);
    $builder->from('users')->update(["options->'))#" => 'John']);
});

test('my sql wrapping json', function () {
    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users')->whereRaw('items->\'$."price"\' = 1');
    $this->assertSame('select * from `users` where items->\'$."price"\' = 1', $builder->toSql());

    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('items->price')->from('users')->where('users.items->price', '=', 1)->orderBy('items->price');
    $this->assertSame('select json_unquote(json_extract(`items`, \'$."price"\')) from `users` where json_unquote(json_extract(`users`.`items`, \'$."price"\')) = ? order by json_unquote(json_extract(`items`, \'$."price"\')) asc', $builder->toSql());

    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users')->where('items->price->in_usd', '=', 1);
    $this->assertSame('select * from `users` where json_unquote(json_extract(`items`, \'$."price"."in_usd"\')) = ?', $builder->toSql());

    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users')->where('items->price->in_usd', '=', 1)->where('items->age', '=', 2);
    $this->assertSame('select * from `users` where json_unquote(json_extract(`items`, \'$."price"."in_usd"\')) = ? and json_unquote(json_extract(`items`, \'$."age"\')) = ?', $builder->toSql());
});

test('postgres wrapping json', function () {
    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('items->price')->from('users')->where('users.items->price', '=', 1)->orderBy('items->price');
    $this->assertSame('select "items"->>\'price\' from "users" where "users"."items"->>\'price\' = ? order by "items"->>\'price\' asc', $builder->toSql());

    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('*')->from('users')->where('items->price->in_usd', '=', 1);
    $this->assertSame('select * from "users" where "items"->\'price\'->>\'in_usd\' = ?', $builder->toSql());

    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('*')->from('users')->where('items->price->in_usd', '=', 1)->where('items->age', '=', 2);
    $this->assertSame('select * from "users" where "items"->\'price\'->>\'in_usd\' = ? and "items"->>\'age\' = ?', $builder->toSql());

    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('*')->from('users')->where('items->prices->0', '=', 1)->where('items->age', '=', 2);
    $this->assertSame('select * from "users" where "items"->\'prices\'->>0 = ? and "items"->>\'age\' = ?', $builder->toSql());

    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('*')->from('users')->where('items->available', '=', true);
    $this->assertSame('select * from "users" where ("items"->\'available\')::jsonb = \'true\'::jsonb', $builder->toSql());
});

test('sql server wrapping json', function () {
    $builder = queryBuilderGetSqlServerBuilder();
    $builder->select('items->price')->from('users')->where('users.items->price', '=', 1)->orderBy('items->price');
    $this->assertSame('select json_value([items], \'$."price"\') from [users] where json_value([users].[items], \'$."price"\') = ? order by json_value([items], \'$."price"\') asc', $builder->toSql());

    $builder = queryBuilderGetSqlServerBuilder();
    $builder->select('*')->from('users')->where('items->price->in_usd', '=', 1);
    $this->assertSame('select * from [users] where json_value([items], \'$."price"."in_usd"\') = ?', $builder->toSql());

    $builder = queryBuilderGetSqlServerBuilder();
    $builder->select('*')->from('users')->where('items->price->in_usd', '=', 1)->where('items->age', '=', 2);
    $this->assertSame('select * from [users] where json_value([items], \'$."price"."in_usd"\') = ? and json_value([items], \'$."age"\') = ?', $builder->toSql());

    $builder = queryBuilderGetSqlServerBuilder();
    $builder->select('*')->from('users')->where('items->available', '=', true);
    $this->assertSame('select * from [users] where json_value([items], \'$."available"\') = \'true\'', $builder->toSql());
});

test('sqlite wrapping json', function () {
    $builder = queryBuilderGetSQLiteBuilder();
    $builder->select('items->price')->from('users')->where('users.items->price', '=', 1)->orderBy('items->price');
    $this->assertSame('select json_extract("items", \'$."price"\') from "users" where json_extract("users"."items", \'$."price"\') = ? order by json_extract("items", \'$."price"\') asc', $builder->toSql());

    $builder = queryBuilderGetSQLiteBuilder();
    $builder->select('*')->from('users')->where('items->price->in_usd', '=', 1);
    $this->assertSame('select * from "users" where json_extract("items", \'$."price"."in_usd"\') = ?', $builder->toSql());

    $builder = queryBuilderGetSQLiteBuilder();
    $builder->select('*')->from('users')->where('items->price->in_usd', '=', 1)->where('items->age', '=', 2);
    $this->assertSame('select * from "users" where json_extract("items", \'$."price"."in_usd"\') = ? and json_extract("items", \'$."age"\') = ?', $builder->toSql());

    $builder = queryBuilderGetSQLiteBuilder();
    $builder->select('*')->from('users')->where('items->available', '=', true);
    $this->assertSame('select * from "users" where json_extract("items", \'$."available"\') = true', $builder->toSql());
});

test('s q lite order by', function () {
    $builder = queryBuilderGetSQLiteBuilder();
    $builder->select('*')->from('users')->orderBy('email', 'desc');
    $this->assertSame('select * from "users" order by "email" desc', $builder->toSql());
});

test('sql server limits and offsets', function () {
    $builder = queryBuilderGetSqlServerBuilder();
    $builder->select('*')->from('users')->limit(10);
    $this->assertSame('select top 10 * from [users]', $builder->toSql());

    $builder = queryBuilderGetSqlServerBuilder();
    $builder->select('*')->from('users')->offset(10)->orderBy('email', 'desc');
    $this->assertSame('select * from [users] order by [email] desc offset 10 rows', $builder->toSql());

    $builder = queryBuilderGetSqlServerBuilder();
    $builder->select('*')->from('users')->offset(10)->limit(10);
    $this->assertSame('select * from [users] order by (SELECT 0) offset 10 rows fetch next 10 rows only', $builder->toSql());

    $builder = queryBuilderGetSqlServerBuilder();
    $builder->select('*')->from('users')->offset(11)->limit(10)->orderBy('email', 'desc');
    $this->assertSame('select * from [users] order by [email] desc offset 11 rows fetch next 10 rows only', $builder->toSql());

    $builder = queryBuilderGetSqlServerBuilder();
    $subQuery = function ($query) {
        return $query->select('created_at')->from('logins')->where('users.name', 'nameBinding')->whereColumn('user_id', 'users.id')->limit(1);
    };
    $builder->select('*')->from('users')->where('email', 'emailBinding')->orderBy($subQuery)->offset(10)->limit(10);
    $this->assertSame('select * from [users] where [email] = ? order by (select top 1 [created_at] from [logins] where [users].[name] = ? and [user_id] = [users].[id]) asc offset 10 rows fetch next 10 rows only', $builder->toSql());
    $this->assertEquals(['emailBinding', 'nameBinding'], $builder->getBindings());

    $builder = queryBuilderGetSqlServerBuilder();
    $builder->select('*')->from('users')->limit('foo');
    $this->assertSame('select * from [users]', $builder->toSql());

    $builder = queryBuilderGetSqlServerBuilder();
    $builder->select('*')->from('users')->limit('foo')->offset('bar');
    $this->assertSame('select * from [users]', $builder->toSql());

    $builder = queryBuilderGetSqlServerBuilder();
    $builder->select('*')->from('users')->offset('bar');
    $this->assertSame('select * from [users]', $builder->toSql());
});

test('my sql sounds like operator', function () {
    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users')->where('name', 'sounds like', 'John Doe');
    $this->assertSame('select * from `users` where `name` sounds like ?', $builder->toSql());
    $this->assertEquals(['John Doe'], $builder->getBindings());
});

test('bitwise operators', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('bar', '&', 1);
    $this->assertSame('select * from "users" where "bar" & ?', $builder->toSql());

    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('*')->from('users')->where('bar', '#', 1);
    $this->assertSame('select * from "users" where ("bar" # ?)::bool', $builder->toSql());

    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('*')->from('users')->where('range', '>>', '[2022-01-08 00:00:00,2022-01-09 00:00:00)');
    $this->assertSame('select * from "users" where ("range" >> ?)::bool', $builder->toSql());

    $builder = queryBuilderGetSqlServerBuilder();
    $builder->select('*')->from('users')->where('bar', '&', 1);
    $this->assertSame('select * from [users] where ([bar] & ?) != 0', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->having('bar', '&', 1);
    $this->assertSame('select * from "users" having "bar" & ?', $builder->toSql());

    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('*')->from('users')->having('bar', '#', 1);
    $this->assertSame('select * from "users" having ("bar" # ?)::bool', $builder->toSql());

    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('*')->from('users')->having('range', '>>', '[2022-01-08 00:00:00,2022-01-09 00:00:00)');
    $this->assertSame('select * from "users" having ("range" >> ?)::bool', $builder->toSql());

    $builder = queryBuilderGetSqlServerBuilder();
    $builder->select('*')->from('users')->having('bar', '&', 1);
    $this->assertSame('select * from [users] having ([bar] & ?) != 0', $builder->toSql());
});

test('merge wheres can merge wheres and bindings', function () {
    $builder = queryBuilderGetBuilder();
    $builder->wheres = ['foo'];
    $builder->mergeWheres(['wheres'], [12 => 'foo', 13 => 'bar']);
    $this->assertEquals(['foo', 'wheres'], $builder->wheres);
    $this->assertEquals(['foo', 'bar'], $builder->getBindings());
});

test('prepare value and operator', function () {
    $builder = queryBuilderGetBuilder();
    [$value, $operator] = $builder->prepareValueAndOperator('>', '20');
    $this->assertSame('>', $value);
    $this->assertSame('20', $operator);

    $builder = queryBuilderGetBuilder();
    [$value, $operator] = $builder->prepareValueAndOperator('>', '20', true);
    $this->assertSame('20', $value);
    $this->assertSame('=', $operator);
});

test('prepare value and operator expect exception', function () {

    $builder = queryBuilderGetBuilder();
    $builder->prepareValueAndOperator(null, 'like');
})->throws(InvalidArgumentException::class, 'Illegal operator and value combination.');

test('providing null with operators builds correctly', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('foo', null);
    $this->assertSame('select * from "users" where "foo" is null', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('foo', '=', null);
    $this->assertSame('select * from "users" where "foo" is null', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('foo', '!=', null);
    $this->assertSame('select * from "users" where "foo" is not null', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('foo', '<>', null);
    $this->assertSame('select * from "users" where "foo" is not null', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('foo', '<=>', null);
    $this->assertSame('select * from "users" where "foo" is null', $builder->toSql());
});

test('dynamic where', function () {
    $method = 'whereFooBarAndBazOrQux';
    $parameters = ['corge', 'waldo', 'fred'];
    $builder = m::mock(Builder::class)->makePartial();

    $builder->shouldReceive('where')->with('foo_bar', '=', $parameters[0], 'and')->once()->andReturnSelf();
    $builder->shouldReceive('where')->with('baz', '=', $parameters[1], 'and')->once()->andReturnSelf();
    $builder->shouldReceive('where')->with('qux', '=', $parameters[2], 'or')->once()->andReturnSelf();

    $this->assertEquals($builder, $builder->dynamicWhere($method, $parameters));
});

test('dynamic where is not greedy', function () {
    $method = 'whereIosVersionAndAndroidVersionOrOrientation';
    $parameters = ['6.1', '4.2', 'Vertical'];
    $builder = m::mock(Builder::class)->makePartial();

    $builder->shouldReceive('where')->with('ios_version', '=', '6.1', 'and')->once()->andReturnSelf();
    $builder->shouldReceive('where')->with('android_version', '=', '4.2', 'and')->once()->andReturnSelf();
    $builder->shouldReceive('where')->with('orientation', '=', 'Vertical', 'or')->once()->andReturnSelf();

    $builder->dynamicWhere($method, $parameters);
});

test('call triggers dynamic where', function () {
    $builder = queryBuilderGetBuilder();

    $this->assertEquals($builder, $builder->whereFooAndBar('baz', 'qux'));
    $this->assertCount(2, $builder->wheres);
});

test('builder throws expected exception with undefined method', function () {

    $builder = queryBuilderGetBuilder();
    $builder->getConnection()->shouldReceive('select');
    $builder->getProcessor()->shouldReceive('processSelect')->andReturn([]);

    $builder->noValidMethodHere();
})->throws(BadMethodCallException::class);

test('my sql lock', function () {
    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('foo')->where('bar', '=', 'baz')->lock();
    $this->assertSame('select * from `foo` where `bar` = ? for update', $builder->toSql());
    $this->assertEquals(['baz'], $builder->getBindings());

    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('foo')->where('bar', '=', 'baz')->lock(false);
    $this->assertSame('select * from `foo` where `bar` = ? lock in share mode', $builder->toSql());
    $this->assertEquals(['baz'], $builder->getBindings());

    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('foo')->where('bar', '=', 'baz')->lock('lock in share mode');
    $this->assertSame('select * from `foo` where `bar` = ? lock in share mode', $builder->toSql());
    $this->assertEquals(['baz'], $builder->getBindings());
});

test('postgres lock', function () {
    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('*')->from('foo')->where('bar', '=', 'baz')->lock();
    $this->assertSame('select * from "foo" where "bar" = ? for update', $builder->toSql());
    $this->assertEquals(['baz'], $builder->getBindings());

    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('*')->from('foo')->where('bar', '=', 'baz')->lock(false);
    $this->assertSame('select * from "foo" where "bar" = ? for share', $builder->toSql());
    $this->assertEquals(['baz'], $builder->getBindings());

    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('*')->from('foo')->where('bar', '=', 'baz')->lock('for key share');
    $this->assertSame('select * from "foo" where "bar" = ? for key share', $builder->toSql());
    $this->assertEquals(['baz'], $builder->getBindings());
});

test('sql server lock', function () {
    $builder = queryBuilderGetSqlServerBuilder();
    $builder->select('*')->from('foo')->where('bar', '=', 'baz')->lock();
    $this->assertSame('select * from [foo] with(rowlock,updlock,holdlock) where [bar] = ?', $builder->toSql());
    $this->assertEquals(['baz'], $builder->getBindings());

    $builder = queryBuilderGetSqlServerBuilder();
    $builder->select('*')->from('foo')->where('bar', '=', 'baz')->lock(false);
    $this->assertSame('select * from [foo] with(rowlock,holdlock) where [bar] = ?', $builder->toSql());
    $this->assertEquals(['baz'], $builder->getBindings());

    $builder = queryBuilderGetSqlServerBuilder();
    $builder->select('*')->from('foo')->where('bar', '=', 'baz')->lock('with(holdlock)');
    $this->assertSame('select * from [foo] with(holdlock) where [bar] = ?', $builder->toSql());
    $this->assertEquals(['baz'], $builder->getBindings());
});

test('select with lock uses write pdo', function () {
    $builder = queryBuilderGetMySqlBuilderWithProcessor();
    $builder->getConnection()->shouldReceive('select')->once()
        ->with(m::any(), m::any(), false);
    $builder->select('*')->from('foo')->where('bar', '=', 'baz')->lock()->get();

    $builder = queryBuilderGetMySqlBuilderWithProcessor();
    $builder->getConnection()->shouldReceive('select')->once()
        ->with(m::any(), m::any(), false);
    $builder->select('*')->from('foo')->where('bar', '=', 'baz')->lock(false)->get();
});

test('binding order', function () {
    $expectedSql = 'select * from "users" inner join "othertable" on "bar" = ? where "registered" = ? group by "city" having "population" > ? order by match ("foo") against(?)';
    $expectedBindings = ['foo', 1, 3, 'bar'];

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->join('othertable', function ($join) {
        $join->where('bar', '=', 'foo');
    })->where('registered', 1)->groupBy('city')->having('population', '>', 3)->orderByRaw('match ("foo") against(?)', ['bar']);
    $this->assertEquals($expectedSql, $builder->toSql());
    $this->assertEquals($expectedBindings, $builder->getBindings());

    // order of statements reversed
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->orderByRaw('match ("foo") against(?)', ['bar'])->having('population', '>', 3)->groupBy('city')->where('registered', 1)->join('othertable', function ($join) {
        $join->where('bar', '=', 'foo');
    });
    $this->assertEquals($expectedSql, $builder->toSql());
    $this->assertEquals($expectedBindings, $builder->getBindings());
});

test('add binding with array merges bindings', function () {
    $builder = queryBuilderGetBuilder();
    $builder->addBinding(['foo', 'bar']);
    $builder->addBinding(['baz']);
    $this->assertEquals(['foo', 'bar', 'baz'], $builder->getBindings());
});

test('add binding with array merges bindings in correct order', function () {
    $builder = queryBuilderGetBuilder();
    $builder->addBinding(['bar', 'baz'], 'having');
    $builder->addBinding(['foo'], 'where');
    $this->assertEquals(['foo', 'bar', 'baz'], $builder->getBindings());
});

test('add binding with enum', function () {
    $builder = queryBuilderGetBuilder();
    $builder->addBinding(IntegerStatus::done);
    $builder->addBinding([NonBackedStatus::done]);
    $this->assertEquals([2, 'done'], $builder->getBindings());
});

test('merge builders', function () {
    $builder = queryBuilderGetBuilder();
    $builder->addBinding(['foo', 'bar']);
    $otherBuilder = queryBuilderGetBuilder();
    $otherBuilder->addBinding(['baz']);
    $builder->mergeBindings($otherBuilder);
    $this->assertEquals(['foo', 'bar', 'baz'], $builder->getBindings());
});

test('merge builders binding order', function () {
    $builder = queryBuilderGetBuilder();
    $builder->addBinding('foo', 'where');
    $builder->addBinding('baz', 'having');
    $otherBuilder = queryBuilderGetBuilder();
    $otherBuilder->addBinding('bar', 'where');
    $builder->mergeBindings($otherBuilder);
    $this->assertEquals(['foo', 'bar', 'baz'], $builder->getBindings());
});

test('sub select', function () {
    $expectedSql = 'select "foo", "bar", (select "baz" from "two" where "subkey" = ?) as "sub" from "one" where "key" = ?';
    $expectedBindings = ['subval', 'val'];

    $builder = queryBuilderGetPostgresBuilder();
    $builder->from('one')->select(['foo', 'bar'])->where('key', '=', 'val');
    $builder->selectSub(function ($query) {
        $query->from('two')->select('baz')->where('subkey', '=', 'subval');
    }, 'sub');
    $this->assertEquals($expectedSql, $builder->toSql());
    $this->assertEquals($expectedBindings, $builder->getBindings());

    $builder = queryBuilderGetPostgresBuilder();
    $builder->from('one')->select(['foo', 'bar'])->where('key', '=', 'val');
    $subBuilder = queryBuilderGetPostgresBuilder();
    $subBuilder->from('two')->select('baz')->where('subkey', '=', 'subval');
    $builder->selectSub($subBuilder, 'sub');
    $this->assertEquals($expectedSql, $builder->toSql());
    $this->assertEquals($expectedBindings, $builder->getBindings());

    $builder = queryBuilderGetPostgresBuilder();
    $builder->selectSub(['foo'], 'sub');
})->throws(InvalidArgumentException::class);

test('sub select reset bindings', function () {
    $builder = queryBuilderGetPostgresBuilder();
    $builder->from('one')->selectSub(function ($query) {
        $query->from('two')->select('baz')->where('subkey', '=', 'subval');
    }, 'sub');

    $this->assertSame('select (select "baz" from "two" where "subkey" = ?) as "sub" from "one"', $builder->toSql());
    $this->assertEquals(['subval'], $builder->getBindings());

    $builder->select('*');

    $this->assertSame('select * from "one"', $builder->toSql());
    $this->assertEquals([], $builder->getBindings());
});

test('select expression', function () {
    $builder = queryBuilderGetBuilder();
    $builder->from('one')
        ->selectExpression(new Raw('1 + 1'), 'expr')
        ->selectExpression('2 + 2', 'expr2');

    $this->assertSame('select (1 + 1) as "expr", (2 + 2) as "expr2" from "one"', $builder->toSql());
});

test('select', function () {
    $builder = queryBuilderGetBuilder();
    $builder->from('one')->select([
        'two',
        'three' => 'threee as threeee',
        'four' => queryBuilderGetBuilder()->from('tbl')->select('col'),
        'five' => new Raw('1 + 1'),
    ]);

    $this->assertSame('select "two", "threee" as "threeee", (select "col" from "tbl") as "four", 1 + 1 from "one"', $builder->toSql());
});

test('sql server where date', function () {
    $builder = queryBuilderGetSqlServerBuilder();
    $builder->select('*')->from('users')->whereDate('created_at', '=', '2015-09-23');
    $this->assertSame('select * from [users] where cast([created_at] as date) = ?', $builder->toSql());
    $this->assertEquals([0 => '2015-09-23'], $builder->getBindings());
});

test('uppercase leading booleans are removed', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('name', '=', 'Taylor', 'AND');
    $this->assertSame('select * from "users" where "name" = ?', $builder->toSql());
});

test('lowercase leading booleans are removed', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('name', '=', 'Taylor', 'and');
    $this->assertSame('select * from "users" where "name" = ?', $builder->toSql());
});

test('case insensitive leading booleans are removed', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('name', '=', 'Taylor', 'And');
    $this->assertSame('select * from "users" where "name" = ?', $builder->toSql());
});

test('table valued function as table in sql server', function () {
    $builder = queryBuilderGetSqlServerBuilder();
    $builder->select('*')->from('users()');
    $this->assertSame('select * from [users]()', $builder->toSql());

    $builder = queryBuilderGetSqlServerBuilder();
    $builder->select('*')->from('users(1,2)');
    $this->assertSame('select * from [users](1,2)', $builder->toSql());
});

test('chunk with last chunk complete', function () {
    $builder = queryBuilderGetMockQueryBuilder();
    $builder->orders[] = ['column' => 'foobar', 'direction' => 'asc'];

    $chunk1 = collect(['foo1', 'foo2']);
    $chunk2 = collect(['foo3', 'foo4']);
    $chunk3 = collect([]);

    $builder->shouldReceive('getOffset')->once()->andReturnNull();
    $builder->shouldReceive('getLimit')->once()->andReturnNull();
    $builder->shouldReceive('offset')->once()->with(0)->andReturnSelf();
    $builder->shouldReceive('offset')->once()->with(2)->andReturnSelf();
    $builder->shouldReceive('offset')->once()->with(4)->andReturnSelf();
    $builder->shouldReceive('limit')->times(3)->with(2)->andReturnSelf();
    $builder->shouldReceive('get')->times(3)->andReturn($chunk1, $chunk2, $chunk3);

    $callbackAssertor = m::mock(stdClass::class);
    $callbackAssertor->shouldReceive('doSomething')->once()->with($chunk1);
    $callbackAssertor->shouldReceive('doSomething')->once()->with($chunk2);
    $callbackAssertor->shouldReceive('doSomething')->never()->with($chunk3);

    $builder->chunk(2, function ($results) use ($callbackAssertor) {
        $callbackAssertor->doSomething($results);
    });
});

test('chunk with last chunk partial', function () {
    $builder = queryBuilderGetMockQueryBuilder();
    $builder->orders[] = ['column' => 'foobar', 'direction' => 'asc'];

    $chunk1 = collect(['foo1', 'foo2']);
    $chunk2 = collect(['foo3']);

    $builder->shouldReceive('getOffset')->once()->andReturnNull();
    $builder->shouldReceive('getLimit')->once()->andReturnNull();
    $builder->shouldReceive('offset')->once()->with(0)->andReturnSelf();
    $builder->shouldReceive('offset')->once()->with(2)->andReturnSelf();
    $builder->shouldReceive('limit')->twice()->with(2)->andReturnSelf();
    $builder->shouldReceive('get')->times(2)->andReturn($chunk1, $chunk2);

    $callbackAssertor = m::mock(stdClass::class);
    $callbackAssertor->shouldReceive('doSomething')->once()->with($chunk1);
    $callbackAssertor->shouldReceive('doSomething')->once()->with($chunk2);

    $builder->chunk(2, function ($results) use ($callbackAssertor) {
        $callbackAssertor->doSomething($results);
    });
});

test('chunk can be stopped by returning false', function () {
    $builder = queryBuilderGetMockQueryBuilder();
    $builder->orders[] = ['column' => 'foobar', 'direction' => 'asc'];

    $chunk1 = collect(['foo1', 'foo2']);
    $chunk2 = collect(['foo3']);
    $builder->shouldReceive('getOffset')->once()->andReturnNull();
    $builder->shouldReceive('getLimit')->once()->andReturnNull();
    $builder->shouldReceive('offset')->once()->with(0)->andReturnSelf();
    $builder->shouldReceive('limit')->once()->with(2)->andReturnSelf();
    $builder->shouldReceive('get')->times(1)->andReturn($chunk1);

    $callbackAssertor = m::mock(stdClass::class);
    $callbackAssertor->shouldReceive('doSomething')->once()->with($chunk1);
    $callbackAssertor->shouldReceive('doSomething')->never()->with($chunk2);

    $builder->chunk(2, function ($results) use ($callbackAssertor) {
        $callbackAssertor->doSomething($results);

        return false;
    });
});

test('chunk with count zero', function () {
    $builder = queryBuilderGetMockQueryBuilder();
    $builder->orders[] = ['column' => 'foobar', 'direction' => 'asc'];

    $builder->shouldReceive('getOffset')->once()->andReturnNull();
    $builder->shouldReceive('getLimit')->once()->andReturnNull();
    $builder->shouldReceive('offset')->never();
    $builder->shouldReceive('limit')->never();
    $builder->shouldReceive('get')->never();

    $builder->chunk(0, function () {
        $this->fail('Should never be called.');
    });
});

test('chunk by id on arrays', function () {
    $builder = queryBuilderGetMockQueryBuilder();
    $builder->orders[] = ['column' => 'foobar', 'direction' => 'asc'];

    $chunk1 = collect([['someIdField' => 1], ['someIdField' => 2]]);
    $chunk2 = collect([['someIdField' => 10], ['someIdField' => 11]]);
    $chunk3 = collect([]);
    $builder->shouldReceive('forPageAfterId')->once()->with(2, 0, 'someIdField')->andReturnSelf();
    $builder->shouldReceive('forPageAfterId')->once()->with(2, 2, 'someIdField')->andReturnSelf();
    $builder->shouldReceive('forPageAfterId')->once()->with(2, 11, 'someIdField')->andReturnSelf();
    $builder->shouldReceive('get')->times(3)->andReturn($chunk1, $chunk2, $chunk3);

    $callbackAssertor = m::mock(stdClass::class);
    $callbackAssertor->shouldReceive('doSomething')->once()->with($chunk1);
    $callbackAssertor->shouldReceive('doSomething')->once()->with($chunk2);
    $callbackAssertor->shouldReceive('doSomething')->never()->with($chunk3);

    $builder->chunkById(2, function ($results) use ($callbackAssertor) {
        $callbackAssertor->doSomething($results);
    }, 'someIdField');
});

test('chunk paginates using id with last chunk complete', function () {
    $builder = queryBuilderGetMockQueryBuilder();
    $builder->orders[] = ['column' => 'foobar', 'direction' => 'asc'];

    $chunk1 = collect([(object) ['someIdField' => 1], (object) ['someIdField' => 2]]);
    $chunk2 = collect([(object) ['someIdField' => 10], (object) ['someIdField' => 11]]);
    $chunk3 = collect([]);
    $builder->shouldReceive('forPageAfterId')->once()->with(2, 0, 'someIdField')->andReturnSelf();
    $builder->shouldReceive('forPageAfterId')->once()->with(2, 2, 'someIdField')->andReturnSelf();
    $builder->shouldReceive('forPageAfterId')->once()->with(2, 11, 'someIdField')->andReturnSelf();
    $builder->shouldReceive('get')->times(3)->andReturn($chunk1, $chunk2, $chunk3);

    $callbackAssertor = m::mock(stdClass::class);
    $callbackAssertor->shouldReceive('doSomething')->once()->with($chunk1);
    $callbackAssertor->shouldReceive('doSomething')->once()->with($chunk2);
    $callbackAssertor->shouldReceive('doSomething')->never()->with($chunk3);

    $builder->chunkById(2, function ($results) use ($callbackAssertor) {
        $callbackAssertor->doSomething($results);
    }, 'someIdField');
});

test('chunk paginates using id with last chunk partial', function () {
    $builder = queryBuilderGetMockQueryBuilder();
    $builder->orders[] = ['column' => 'foobar', 'direction' => 'asc'];

    $chunk1 = collect([(object) ['someIdField' => 1], (object) ['someIdField' => 2]]);
    $chunk2 = collect([(object) ['someIdField' => 10]]);
    $builder->shouldReceive('forPageAfterId')->once()->with(2, 0, 'someIdField')->andReturnSelf();
    $builder->shouldReceive('forPageAfterId')->once()->with(2, 2, 'someIdField')->andReturnSelf();
    $builder->shouldReceive('get')->times(2)->andReturn($chunk1, $chunk2);

    $callbackAssertor = m::mock(stdClass::class);
    $callbackAssertor->shouldReceive('doSomething')->once()->with($chunk1);
    $callbackAssertor->shouldReceive('doSomething')->once()->with($chunk2);

    $builder->chunkById(2, function ($results) use ($callbackAssertor) {
        $callbackAssertor->doSomething($results);
    }, 'someIdField');
});

test('chunk paginates using id with count zero', function () {
    $builder = queryBuilderGetMockQueryBuilder();
    $builder->orders[] = ['column' => 'foobar', 'direction' => 'asc'];

    $builder->shouldReceive('forPageAfterId')->never();
    $builder->shouldReceive('get')->never();

    $builder->chunkById(0, function () {
        $this->fail('Should never be called.');
    }, 'someIdField');
});

test('chunk paginates using id with alias', function () {
    $builder = queryBuilderGetMockQueryBuilder();
    $builder->orders[] = ['column' => 'foobar', 'direction' => 'asc'];

    $chunk1 = collect([(object) ['table_id' => 1], (object) ['table_id' => 10]]);
    $chunk2 = collect([]);
    $builder->shouldReceive('forPageAfterId')->once()->with(2, 0, 'table.id')->andReturnSelf();
    $builder->shouldReceive('forPageAfterId')->once()->with(2, 10, 'table.id')->andReturnSelf();
    $builder->shouldReceive('get')->times(2)->andReturn($chunk1, $chunk2);

    $callbackAssertor = m::mock(stdClass::class);
    $callbackAssertor->shouldReceive('doSomething')->once()->with($chunk1);
    $callbackAssertor->shouldReceive('doSomething')->never()->with($chunk2);

    $builder->chunkById(2, function ($results) use ($callbackAssertor) {
        $callbackAssertor->doSomething($results);
    }, 'table.id', 'table_id');
});

test('chunk paginates using id desc', function () {
    $builder = queryBuilderGetMockQueryBuilder();
    $builder->orders[] = ['column' => 'foobar', 'direction' => 'desc'];

    $chunk1 = collect([(object) ['someIdField' => 10], (object) ['someIdField' => 1]]);
    $chunk2 = collect([]);
    $builder->shouldReceive('forPageBeforeId')->once()->with(2, 0, 'someIdField')->andReturnSelf();
    $builder->shouldReceive('forPageBeforeId')->once()->with(2, 1, 'someIdField')->andReturnSelf();
    $builder->shouldReceive('get')->times(2)->andReturn($chunk1, $chunk2);

    $callbackAssertor = m::mock(stdClass::class);
    $callbackAssertor->shouldReceive('doSomething')->once()->with($chunk1);
    $callbackAssertor->shouldReceive('doSomething')->never()->with($chunk2);

    $builder->chunkByIdDesc(2, function ($results) use ($callbackAssertor) {
        $callbackAssertor->doSomething($results);
    }, 'someIdField');
});

test('paginate', function () {
    $perPage = 16;
    $columns = ['test'];
    $pageName = 'page-name';
    $page = 1;
    $builder = queryBuilderGetMockQueryBuilder();
    $path = 'http://foo.bar?page=3';

    $results = collect([['test' => 'foo'], ['test' => 'bar']]);

    $builder->shouldReceive('getCountForPagination')->once()->andReturn(2);
    $builder->shouldReceive('forPage')->once()->with($page, $perPage)->andReturnSelf();
    $builder->shouldReceive('get')->once()->andReturn($results);

    Paginator::currentPathResolver(function () use ($path) {
        return $path;
    });

    $result = $builder->paginate($perPage, $columns, $pageName, $page);

    $this->assertEquals(new LengthAwarePaginator($results, 2, $perPage, $page, [
        'path' => $path,
        'pageName' => $pageName,
    ]), $result);
});

test('paginate with default arguments', function () {
    $perPage = 15;
    $pageName = 'page';
    $page = 1;
    $builder = queryBuilderGetMockQueryBuilder();
    $path = 'http://foo.bar?page=3';

    $results = collect([['test' => 'foo'], ['test' => 'bar']]);

    $builder->shouldReceive('getCountForPagination')->once()->andReturn(2);
    $builder->shouldReceive('forPage')->once()->with($page, $perPage)->andReturnSelf();
    $builder->shouldReceive('get')->once()->andReturn($results);

    Paginator::currentPageResolver(function () {
        return 1;
    });

    Paginator::currentPathResolver(function () use ($path) {
        return $path;
    });

    $result = $builder->paginate();

    $this->assertEquals(new LengthAwarePaginator($results, 2, $perPage, $page, [
        'path' => $path,
        'pageName' => $pageName,
    ]), $result);
});

test('paginate when no results', function () {
    $perPage = 15;
    $pageName = 'page';
    $page = 1;
    $builder = queryBuilderGetMockQueryBuilder();
    $path = 'http://foo.bar?page=3';

    $results = [];

    $builder->shouldReceive('getCountForPagination')->once()->andReturn(0);
    $builder->shouldNotReceive('forPage');
    $builder->shouldNotReceive('get');

    Paginator::currentPageResolver(function () {
        return 1;
    });

    Paginator::currentPathResolver(function () use ($path) {
        return $path;
    });

    $result = $builder->paginate();

    $this->assertEquals(new LengthAwarePaginator($results, 0, $perPage, $page, [
        'path' => $path,
        'pageName' => $pageName,
    ]), $result);
});

test('paginate with specific columns', function () {
    $perPage = 16;
    $columns = ['id', 'name'];
    $pageName = 'page-name';
    $page = 1;
    $builder = queryBuilderGetMockQueryBuilder();
    $path = 'http://foo.bar?page=3';

    $results = collect([['id' => 3, 'name' => 'Taylor'], ['id' => 5, 'name' => 'Mohamed']]);

    $builder->shouldReceive('getCountForPagination')->once()->andReturn(2);
    $builder->shouldReceive('forPage')->once()->with($page, $perPage)->andReturnSelf();
    $builder->shouldReceive('get')->once()->andReturn($results);

    Paginator::currentPathResolver(function () use ($path) {
        return $path;
    });

    $result = $builder->paginate($perPage, $columns, $pageName, $page);

    $this->assertEquals(new LengthAwarePaginator($results, 2, $perPage, $page, [
        'path' => $path,
        'pageName' => $pageName,
    ]), $result);
});

test('paginate with total override', function () {
    $perPage = 16;
    $columns = ['id', 'name'];
    $pageName = 'page-name';
    $page = 1;
    $builder = queryBuilderGetMockQueryBuilder();
    $path = 'http://foo.bar?page=3';

    $results = collect([['id' => 3, 'name' => 'Taylor'], ['id' => 5, 'name' => 'Mohamed']]);

    $builder->shouldReceive('getCountForPagination')->never();
    $builder->shouldReceive('forPage')->once()->with($page, $perPage)->andReturnSelf();
    $builder->shouldReceive('get')->once()->andReturn($results);

    Paginator::currentPathResolver(function () use ($path) {
        return $path;
    });

    $result = $builder->paginate($perPage, $columns, $pageName, $page, 10);

    $this->assertEquals(10, $result->total());
});

test('cursor paginate', function () {
    $perPage = 16;
    $columns = ['test'];
    $cursorName = 'cursor-name';
    $cursor = new Cursor(['test' => 'bar']);
    $builder = queryBuilderGetMockQueryBuilder();
    $builder->from('foobar')->orderBy('test');
    $builder->shouldReceive('newQuery')->andReturnUsing(function () use ($builder) {
        return new Builder($builder->connection, $builder->grammar, $builder->processor);
    });

    $path = 'http://foo.bar?cursor='.$cursor->encode();

    $results = collect([['test' => 'foo'], ['test' => 'bar']]);

    $builder->shouldReceive('get')->once()->andReturnUsing(function () use ($builder, $results) {
        $this->assertEquals(
            'select * from "foobar" where ("test" > ?) order by "test" asc limit 17',
            $builder->toSql());
        $this->assertEquals(['bar'], $builder->bindings['where']);

        return $results;
    });

    Paginator::currentPathResolver(function () use ($path) {
        return $path;
    });

    $result = $builder->cursorPaginate($perPage, $columns, $cursorName, $cursor);

    $this->assertEquals(new CursorPaginator($results, $perPage, $cursor, [
        'path' => $path,
        'cursorName' => $cursorName,
        'parameters' => ['test'],
    ]), $result);
});

test('cursor paginate multiple order columns', function () {
    $perPage = 16;
    $columns = ['test', 'another'];
    $cursorName = 'cursor-name';
    $cursor = new Cursor(['test' => 'bar', 'another' => 'foo']);
    $builder = queryBuilderGetMockQueryBuilder();
    $builder->from('foobar')->orderBy('test')->orderBy('another');
    $builder->shouldReceive('newQuery')->andReturnUsing(function () use ($builder) {
        return new Builder($builder->connection, $builder->grammar, $builder->processor);
    });

    $path = 'http://foo.bar?cursor='.$cursor->encode();

    $results = collect([['test' => 'foo', 'another' => 1], ['test' => 'bar', 'another' => 2]]);

    $builder->shouldReceive('get')->once()->andReturnUsing(function () use ($builder, $results) {
        $this->assertEquals(
            'select * from "foobar" where ("test" > ? or ("test" = ? and ("another" > ?))) order by "test" asc, "another" asc limit 17',
            $builder->toSql()
        );
        $this->assertEquals(['bar', 'bar', 'foo'], $builder->bindings['where']);

        return $results;
    });

    Paginator::currentPathResolver(function () use ($path) {
        return $path;
    });

    $result = $builder->cursorPaginate($perPage, $columns, $cursorName, $cursor);

    $this->assertEquals(new CursorPaginator($results, $perPage, $cursor, [
        'path' => $path,
        'cursorName' => $cursorName,
        'parameters' => ['test', 'another'],
    ]), $result);
});

test('cursor paginate with default arguments', function () {
    $perPage = 15;
    $cursorName = 'cursor';
    $cursor = new Cursor(['test' => 'bar']);
    $builder = queryBuilderGetMockQueryBuilder();
    $builder->from('foobar')->orderBy('test');
    $builder->shouldReceive('newQuery')->andReturnUsing(function () use ($builder) {
        return new Builder($builder->connection, $builder->grammar, $builder->processor);
    });

    $path = 'http://foo.bar?cursor='.$cursor->encode();

    $results = collect([['test' => 'foo'], ['test' => 'bar']]);

    $builder->shouldReceive('get')->once()->andReturnUsing(function () use ($builder, $results) {
        $this->assertEquals(
            'select * from "foobar" where ("test" > ?) order by "test" asc limit 16',
            $builder->toSql());
        $this->assertEquals(['bar'], $builder->bindings['where']);

        return $results;
    });

    CursorPaginator::currentCursorResolver(function () use ($cursor) {
        return $cursor;
    });

    Paginator::currentPathResolver(function () use ($path) {
        return $path;
    });

    $result = $builder->cursorPaginate();

    $this->assertEquals(new CursorPaginator($results, $perPage, $cursor, [
        'path' => $path,
        'cursorName' => $cursorName,
        'parameters' => ['test'],
    ]), $result);
});

test('cursor paginate when no results', function () {
    $perPage = 15;
    $cursorName = 'cursor';
    $builder = queryBuilderGetMockQueryBuilder()->orderBy('test');
    $path = 'http://foo.bar?cursor=3';

    $results = [];

    $builder->shouldReceive('get')->once()->andReturn($results);

    CursorPaginator::currentCursorResolver(function () {
        return null;
    });

    Paginator::currentPathResolver(function () use ($path) {
        return $path;
    });

    $result = $builder->cursorPaginate();

    $this->assertEquals(new CursorPaginator($results, $perPage, null, [
        'path' => $path,
        'cursorName' => $cursorName,
        'parameters' => ['test'],
    ]), $result);
});

test('cursor paginate with specific columns', function () {
    $perPage = 16;
    $columns = ['id', 'name'];
    $cursorName = 'cursor-name';
    $cursor = new Cursor(['id' => 2]);
    $builder = queryBuilderGetMockQueryBuilder();
    $builder->from('foobar')->orderBy('id');
    $builder->shouldReceive('newQuery')->andReturnUsing(function () use ($builder) {
        return new Builder($builder->connection, $builder->grammar, $builder->processor);
    });

    $path = 'http://foo.bar?cursor=3';

    $results = collect([['id' => 3, 'name' => 'Taylor'], ['id' => 5, 'name' => 'Mohamed']]);

    $builder->shouldReceive('get')->once()->andReturnUsing(function () use ($builder, $results) {
        $this->assertEquals(
            'select * from "foobar" where ("id" > ?) order by "id" asc limit 17',
            $builder->toSql());
        $this->assertEquals([2], $builder->bindings['where']);

        return $results;
    });

    Paginator::currentPathResolver(function () use ($path) {
        return $path;
    });

    $result = $builder->cursorPaginate($perPage, $columns, $cursorName, $cursor);

    $this->assertEquals(new CursorPaginator($results, $perPage, $cursor, [
        'path' => $path,
        'cursorName' => $cursorName,
        'parameters' => ['id'],
    ]), $result);
});

test('cursor paginate with mixed orders', function () {
    $perPage = 16;
    $columns = ['foo', 'bar', 'baz'];
    $cursorName = 'cursor-name';
    $cursor = new Cursor(['foo' => 1, 'bar' => 2, 'baz' => 3]);
    $builder = queryBuilderGetMockQueryBuilder();
    $builder->from('foobar')->orderBy('foo')->orderByDesc('bar')->orderBy('baz');
    $builder->shouldReceive('newQuery')->andReturnUsing(function () use ($builder) {
        return new Builder($builder->connection, $builder->grammar, $builder->processor);
    });

    $path = 'http://foo.bar?cursor='.$cursor->encode();

    $results = collect([['foo' => 1, 'bar' => 2, 'baz' => 4], ['foo' => 1, 'bar' => 1, 'baz' => 1]]);

    $builder->shouldReceive('get')->once()->andReturnUsing(function () use ($builder, $results) {
        $this->assertEquals(
            'select * from "foobar" where ("foo" > ? or ("foo" = ? and ("bar" < ? or ("bar" = ? and ("baz" > ?))))) order by "foo" asc, "bar" desc, "baz" asc limit 17',
            $builder->toSql()
        );
        $this->assertEquals([1, 1, 2, 2, 3], $builder->bindings['where']);

        return $results;
    });

    Paginator::currentPathResolver(function () use ($path) {
        return $path;
    });

    $result = $builder->cursorPaginate($perPage, $columns, $cursorName, $cursor);

    $this->assertEquals(new CursorPaginator($results, $perPage, $cursor, [
        'path' => $path,
        'cursorName' => $cursorName,
        'parameters' => ['foo', 'bar', 'baz'],
    ]), $result);
});

test('cursor paginate with dynamic column in select raw', function () {
    $perPage = 15;
    $cursorName = 'cursor';
    $cursor = new Cursor(['test' => 'bar']);
    $builder = queryBuilderGetMockQueryBuilder();
    $builder->from('foobar')->select('*')->selectRaw('(CONCAT(firstname, \' \', lastname)) as test')->orderBy('test');
    $builder->shouldReceive('newQuery')->andReturnUsing(function () use ($builder) {
        return new Builder($builder->connection, $builder->grammar, $builder->processor);
    });

    $path = 'http://foo.bar?cursor='.$cursor->encode();

    $results = collect([['test' => 'foo'], ['test' => 'bar']]);

    $builder->shouldReceive('get')->once()->andReturnUsing(function () use ($builder, $results) {
        $this->assertEquals(
            'select *, (CONCAT(firstname, \' \', lastname)) as test from "foobar" where ((CONCAT(firstname, \' \', lastname)) > ?) order by "test" asc limit 16',
            $builder->toSql());
        $this->assertEquals(['bar'], $builder->bindings['where']);

        return $results;
    });

    CursorPaginator::currentCursorResolver(function () use ($cursor) {
        return $cursor;
    });

    Paginator::currentPathResolver(function () use ($path) {
        return $path;
    });

    $result = $builder->cursorPaginate();

    $this->assertEquals(new CursorPaginator($results, $perPage, $cursor, [
        'path' => $path,
        'cursorName' => $cursorName,
        'parameters' => ['test'],
    ]), $result);
});

test('cursor paginate with dynamic column with cast in select raw', function () {
    $perPage = 15;
    $cursorName = 'cursor';
    $cursor = new Cursor(['test' => 'bar']);
    $builder = queryBuilderGetMockQueryBuilder();
    $builder->from('foobar')->select('*')->selectRaw('(CAST(CONCAT(firstname, \' \', lastname) as VARCHAR)) as test')->orderBy('test');
    $builder->shouldReceive('newQuery')->andReturnUsing(function () use ($builder) {
        return new Builder($builder->connection, $builder->grammar, $builder->processor);
    });

    $path = 'http://foo.bar?cursor='.$cursor->encode();

    $results = collect([['test' => 'foo'], ['test' => 'bar']]);

    $builder->shouldReceive('get')->once()->andReturnUsing(function () use ($builder, $results) {
        $this->assertEquals(
            'select *, (CAST(CONCAT(firstname, \' \', lastname) as VARCHAR)) as test from "foobar" where ((CAST(CONCAT(firstname, \' \', lastname) as VARCHAR)) > ?) order by "test" asc limit 16',
            $builder->toSql());
        $this->assertEquals(['bar'], $builder->bindings['where']);

        return $results;
    });

    CursorPaginator::currentCursorResolver(function () use ($cursor) {
        return $cursor;
    });

    Paginator::currentPathResolver(function () use ($path) {
        return $path;
    });

    $result = $builder->cursorPaginate();

    $this->assertEquals(new CursorPaginator($results, $perPage, $cursor, [
        'path' => $path,
        'cursorName' => $cursorName,
        'parameters' => ['test'],
    ]), $result);
});

test('cursor paginate with dynamic column in select sub', function () {
    $perPage = 15;
    $cursorName = 'cursor';
    $cursor = new Cursor(['test' => 'bar']);
    $builder = queryBuilderGetMockQueryBuilder();
    $builder->from('foobar')->select('*')->selectSub('CONCAT(firstname, \' \', lastname)', 'test')->orderBy('test');
    $builder->shouldReceive('newQuery')->andReturnUsing(function () use ($builder) {
        return new Builder($builder->connection, $builder->grammar, $builder->processor);
    });

    $path = 'http://foo.bar?cursor='.$cursor->encode();

    $results = collect([['test' => 'foo'], ['test' => 'bar']]);

    $builder->shouldReceive('get')->once()->andReturnUsing(function () use ($builder, $results) {
        $this->assertEquals(
            'select *, (CONCAT(firstname, \' \', lastname)) as "test" from "foobar" where ((CONCAT(firstname, \' \', lastname)) > ?) order by "test" asc limit 16',
            $builder->toSql());
        $this->assertEquals(['bar'], $builder->bindings['where']);

        return $results;
    });

    CursorPaginator::currentCursorResolver(function () use ($cursor) {
        return $cursor;
    });

    Paginator::currentPathResolver(function () use ($path) {
        return $path;
    });

    $result = $builder->cursorPaginate();

    $this->assertEquals(new CursorPaginator($results, $perPage, $cursor, [
        'path' => $path,
        'cursorName' => $cursorName,
        'parameters' => ['test'],
    ]), $result);
});

test('cursor paginate with union wheres', function () {
    $ts = now()->toDateTimeString();

    $perPage = 16;
    $columns = ['test'];
    $cursorName = 'cursor-name';
    $cursor = new Cursor(['created_at' => $ts]);
    $builder = queryBuilderGetMockQueryBuilder();
    $builder->select('id', 'start_time as created_at')->selectRaw("'video' as type")->from('videos');
    $builder->union(queryBuilderGetBuilder()->select('id', 'created_at')->selectRaw("'news' as type")->from('news'));
    $builder->orderBy('created_at');

    $builder->shouldReceive('newQuery')->andReturnUsing(function () use ($builder) {
        return new Builder($builder->connection, $builder->grammar, $builder->processor);
    });

    $path = 'http://foo.bar?cursor='.$cursor->encode();

    $results = collect([
        ['id' => 1, 'created_at' => now(), 'type' => 'video'],
        ['id' => 2, 'created_at' => now(), 'type' => 'news'],
    ]);

    $builder->shouldReceive('get')->once()->andReturnUsing(function () use ($builder, $results, $ts) {
        $this->assertEquals(
            '(select "id", "start_time" as "created_at", \'video\' as type from "videos" where ("start_time" > ?)) union (select "id", "created_at", \'news\' as type from "news" where ("created_at" > ?)) order by "created_at" asc limit 17',
            $builder->toSql());
        $this->assertEquals([$ts], $builder->bindings['where']);
        $this->assertEquals([$ts], $builder->bindings['union']);

        return $results;
    });

    Paginator::currentPathResolver(function () use ($path) {
        return $path;
    });

    $result = $builder->cursorPaginate($perPage, $columns, $cursorName, $cursor);

    $this->assertEquals(new CursorPaginator($results, $perPage, $cursor, [
        'path' => $path,
        'cursorName' => $cursorName,
        'parameters' => ['created_at'],
    ]), $result);
});

test('cursor paginate with multiple unions and multiple wheres', function () {
    $ts = now()->toDateTimeString();

    $perPage = 16;
    $columns = ['test'];
    $cursorName = 'cursor-name';
    $cursor = new Cursor(['created_at' => $ts]);
    $builder = queryBuilderGetMockQueryBuilder();
    $builder->select('id', 'start_time as created_at')->selectRaw("'video' as type")->from('videos');
    $builder->union(queryBuilderGetBuilder()->select('id', 'created_at')->selectRaw("'news' as type")->from('news')->where('extra', 'first'));
    $builder->union(queryBuilderGetBuilder()->select('id', 'created_at')->selectRaw("'podcast' as type")->from('podcasts')->where('extra', 'second'));
    $builder->orderBy('created_at');

    $builder->shouldReceive('newQuery')->andReturnUsing(function () use ($builder) {
        return new Builder($builder->connection, $builder->grammar, $builder->processor);
    });

    $path = 'http://foo.bar?cursor='.$cursor->encode();

    $results = collect([
        ['id' => 1, 'created_at' => now(), 'type' => 'video'],
        ['id' => 2, 'created_at' => now(), 'type' => 'news'],
        ['id' => 3, 'created_at' => now(), 'type' => 'podcasts'],
    ]);

    $builder->shouldReceive('get')->once()->andReturnUsing(function () use ($builder, $results, $ts) {
        $this->assertEquals(
            '(select "id", "start_time" as "created_at", \'video\' as type from "videos" where ("start_time" > ?)) union (select "id", "created_at", \'news\' as type from "news" where "extra" = ? and ("created_at" > ?)) union (select "id", "created_at", \'podcast\' as type from "podcasts" where "extra" = ? and ("created_at" > ?)) order by "created_at" asc limit 17',
            $builder->toSql());
        $this->assertEquals([$ts], $builder->bindings['where']);
        $this->assertEquals(['first', $ts, 'second', $ts], $builder->bindings['union']);

        return $results;
    });

    Paginator::currentPathResolver(function () use ($path) {
        return $path;
    });

    $result = $builder->cursorPaginate($perPage, $columns, $cursorName, $cursor);

    $this->assertEquals(new CursorPaginator($results, $perPage, $cursor, [
        'path' => $path,
        'cursorName' => $cursorName,
        'parameters' => ['created_at'],
    ]), $result);
});

test('cursor paginate with union multiple wheres multiple orders', function () {
    $ts = now()->toDateTimeString();

    $perPage = 16;
    $columns = ['id', 'created_at', 'type'];
    $cursorName = 'cursor-name';
    $cursor = new Cursor(['id' => 1, 'created_at' => $ts, 'type' => 'news']);
    $builder = queryBuilderGetMockQueryBuilder();
    $builder->select('id', 'start_time as created_at', 'type')->from('videos')->where('extra', 'first');
    $builder->union(queryBuilderGetBuilder()->select('id', 'created_at', 'type')->from('news')->where('extra', 'second'));
    $builder->union(queryBuilderGetBuilder()->select('id', 'created_at', 'type')->from('podcasts')->where('extra', 'third'));
    $builder->orderBy('id')->orderByDesc('created_at')->orderBy('type');

    $builder->shouldReceive('newQuery')->andReturnUsing(function () use ($builder) {
        return new Builder($builder->connection, $builder->grammar, $builder->processor);
    });

    $path = 'http://foo.bar?cursor='.$cursor->encode();

    $results = collect([
        ['id' => 1, 'created_at' => now()->addDay(), 'type' => 'video'],
        ['id' => 1, 'created_at' => now(), 'type' => 'news'],
        ['id' => 1, 'created_at' => now(), 'type' => 'podcast'],
        ['id' => 2, 'created_at' => now(), 'type' => 'podcast'],
    ]);

    $builder->shouldReceive('get')->once()->andReturnUsing(function () use ($builder, $results, $ts) {
        $this->assertEquals(
            '(select "id", "start_time" as "created_at", "type" from "videos" where "extra" = ? and ("id" > ? or ("id" = ? and ("start_time" < ? or ("start_time" = ? and ("type" > ?)))))) union (select "id", "created_at", "type" from "news" where "extra" = ? and ("id" > ? or ("id" = ? and ("start_time" < ? or ("start_time" = ? and ("type" > ?)))))) union (select "id", "created_at", "type" from "podcasts" where "extra" = ? and ("id" > ? or ("id" = ? and ("start_time" < ? or ("start_time" = ? and ("type" > ?)))))) order by "id" asc, "created_at" desc, "type" asc limit 17',
            $builder->toSql());
        $this->assertEquals(['first', 1, 1, $ts, $ts, 'news'], $builder->bindings['where']);
        $this->assertEquals(['second', 1, 1, $ts, $ts, 'news', 'third', 1, 1, $ts, $ts, 'news'], $builder->bindings['union']);

        return $results;
    });

    Paginator::currentPathResolver(function () use ($path) {
        return $path;
    });

    $result = $builder->cursorPaginate($perPage, $columns, $cursorName, $cursor);

    $this->assertEquals(new CursorPaginator($results, $perPage, $cursor, [
        'path' => $path,
        'cursorName' => $cursorName,
        'parameters' => ['id', 'created_at', 'type'],
    ]), $result);
});

test('cursor paginate with union wheres with raw order expression', function () {
    $ts = now()->toDateTimeString();

    $perPage = 16;
    $columns = ['test'];
    $cursorName = 'cursor-name';
    $cursor = new Cursor(['created_at' => $ts]);
    $builder = queryBuilderGetMockQueryBuilder();
    $builder->select('id', 'is_published', 'start_time as created_at')->selectRaw("'video' as type")->where('is_published', true)->from('videos');
    $builder->union(queryBuilderGetBuilder()->select('id', 'is_published', 'created_at')->selectRaw("'news' as type")->where('is_published', true)->from('news'));
    $builder->orderByRaw('case when (id = 3 and type="news" then 0 else 1 end)')->orderBy('created_at');

    $builder->shouldReceive('newQuery')->andReturnUsing(function () use ($builder) {
        return new Builder($builder->connection, $builder->grammar, $builder->processor);
    });

    $path = 'http://foo.bar?cursor='.$cursor->encode();

    $results = collect([
        ['id' => 1, 'created_at' => now(), 'type' => 'video', 'is_published' => true],
        ['id' => 2, 'created_at' => now(), 'type' => 'news', 'is_published' => true],
    ]);

    $builder->shouldReceive('get')->once()->andReturnUsing(function () use ($builder, $results, $ts) {
        $this->assertEquals(
            '(select "id", "is_published", "start_time" as "created_at", \'video\' as type from "videos" where "is_published" = ? and ("start_time" > ?)) union (select "id", "is_published", "created_at", \'news\' as type from "news" where "is_published" = ? and ("created_at" > ?)) order by case when (id = 3 and type="news" then 0 else 1 end), "created_at" asc limit 17',
            $builder->toSql());
        $this->assertEquals([true, $ts], $builder->bindings['where']);
        $this->assertEquals([true, $ts], $builder->bindings['union']);

        return $results;
    });

    Paginator::currentPathResolver(function () use ($path) {
        return $path;
    });

    $result = $builder->cursorPaginate($perPage, $columns, $cursorName, $cursor);

    $this->assertEquals(new CursorPaginator($results, $perPage, $cursor, [
        'path' => $path,
        'cursorName' => $cursorName,
        'parameters' => ['created_at'],
    ]), $result);
});

test('cursor paginate with union wheres reverse order', function () {
    $ts = now()->toDateTimeString();

    $perPage = 16;
    $columns = ['test'];
    $cursorName = 'cursor-name';
    $cursor = new Cursor(['created_at' => $ts], false);
    $builder = queryBuilderGetMockQueryBuilder();
    $builder->select('id', 'start_time as created_at')->selectRaw("'video' as type")->from('videos');
    $builder->union(queryBuilderGetBuilder()->select('id', 'created_at')->selectRaw("'news' as type")->from('news'));
    $builder->orderBy('created_at');

    $builder->shouldReceive('newQuery')->andReturnUsing(function () use ($builder) {
        return new Builder($builder->connection, $builder->grammar, $builder->processor);
    });

    $path = 'http://foo.bar?cursor='.$cursor->encode();

    $results = collect([
        ['id' => 1, 'created_at' => now(), 'type' => 'video'],
        ['id' => 2, 'created_at' => now(), 'type' => 'news'],
    ]);

    $builder->shouldReceive('get')->once()->andReturnUsing(function () use ($builder, $results, $ts) {
        $this->assertEquals(
            '(select "id", "start_time" as "created_at", \'video\' as type from "videos" where ("start_time" < ?)) union (select "id", "created_at", \'news\' as type from "news" where ("created_at" < ?)) order by "created_at" desc limit 17',
            $builder->toSql());
        $this->assertEquals([$ts], $builder->bindings['where']);
        $this->assertEquals([$ts], $builder->bindings['union']);

        return $results;
    });

    Paginator::currentPathResolver(function () use ($path) {
        return $path;
    });

    $result = $builder->cursorPaginate($perPage, $columns, $cursorName, $cursor);

    $this->assertEquals(new CursorPaginator($results, $perPage, $cursor, [
        'path' => $path,
        'cursorName' => $cursorName,
        'parameters' => ['created_at'],
    ]), $result);
});

test('cursor paginate with union wheres multiple orders', function () {
    $ts = now()->toDateTimeString();

    $perPage = 16;
    $columns = ['test'];
    $cursorName = 'cursor-name';
    $cursor = new Cursor(['created_at' => $ts, 'id' => 1]);
    $builder = queryBuilderGetMockQueryBuilder();
    $builder->select('id', 'start_time as created_at')->selectRaw("'video' as type")->from('videos');
    $builder->union(queryBuilderGetBuilder()->select('id', 'created_at')->selectRaw("'news' as type")->from('news'));
    $builder->orderByDesc('created_at')->orderBy('id');

    $builder->shouldReceive('newQuery')->andReturnUsing(function () use ($builder) {
        return new Builder($builder->connection, $builder->grammar, $builder->processor);
    });

    $path = 'http://foo.bar?cursor='.$cursor->encode();

    $results = collect([
        ['id' => 1, 'created_at' => now(), 'type' => 'video'],
        ['id' => 2, 'created_at' => now(), 'type' => 'news'],
    ]);

    $builder->shouldReceive('get')->once()->andReturnUsing(function () use ($builder, $results, $ts) {
        $this->assertEquals(
            '(select "id", "start_time" as "created_at", \'video\' as type from "videos" where ("start_time" < ? or ("start_time" = ? and ("id" > ?)))) union (select "id", "created_at", \'news\' as type from "news" where ("created_at" < ? or ("created_at" = ? and ("id" > ?)))) order by "created_at" desc, "id" asc limit 17',
            $builder->toSql());
        $this->assertEquals([$ts, $ts, 1], $builder->bindings['where']);
        $this->assertEquals([$ts, $ts, 1], $builder->bindings['union']);

        return $results;
    });

    Paginator::currentPathResolver(function () use ($path) {
        return $path;
    });

    $result = $builder->cursorPaginate($perPage, $columns, $cursorName, $cursor);

    $this->assertEquals(new CursorPaginator($results, $perPage, $cursor, [
        'path' => $path,
        'cursorName' => $cursorName,
        'parameters' => ['created_at', 'id'],
    ]), $result);
});

test('cursor paginate with union wheres and aliassed order columns', function () {
    $ts = now()->toDateTimeString();

    $perPage = 16;
    $columns = ['test'];
    $cursorName = 'cursor-name';
    $cursor = new Cursor(['created_at' => $ts]);
    $builder = queryBuilderGetMockQueryBuilder();
    $builder->select('id', 'start_time as created_at')->selectRaw("'video' as type")->from('videos');
    $builder->union(queryBuilderGetBuilder()->select('id', 'created_at')->selectRaw("'news' as type")->from('news'));
    $builder->union(queryBuilderGetBuilder()->select('id', 'init_at as created_at')->selectRaw("'podcast' as type")->from('podcasts'));
    $builder->orderBy('created_at');

    $builder->shouldReceive('newQuery')->andReturnUsing(function () use ($builder) {
        return new Builder($builder->connection, $builder->grammar, $builder->processor);
    });

    $path = 'http://foo.bar?cursor='.$cursor->encode();

    $results = collect([
        ['id' => 1, 'created_at' => now(), 'type' => 'video'],
        ['id' => 2, 'created_at' => now(), 'type' => 'news'],
        ['id' => 3, 'created_at' => now(), 'type' => 'podcast'],
    ]);

    $builder->shouldReceive('get')->once()->andReturnUsing(function () use ($builder, $results, $ts) {
        $this->assertEquals(
            '(select "id", "start_time" as "created_at", \'video\' as type from "videos" where ("start_time" > ?)) union (select "id", "created_at", \'news\' as type from "news" where ("created_at" > ?)) union (select "id", "init_at" as "created_at", \'podcast\' as type from "podcasts" where ("init_at" > ?)) order by "created_at" asc limit 17',
            $builder->toSql());
        $this->assertEquals([$ts], $builder->bindings['where']);
        $this->assertEquals([$ts, $ts], $builder->bindings['union']);

        return $results;
    });

    Paginator::currentPathResolver(function () use ($path) {
        return $path;
    });

    $result = $builder->cursorPaginate($perPage, $columns, $cursorName, $cursor);

    $this->assertEquals(new CursorPaginator($results, $perPage, $cursor, [
        'path' => $path,
        'cursorName' => $cursorName,
        'parameters' => ['created_at'],
    ]), $result);
});

test('where expression', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('orders')->where(
        new class() implements ConditionExpression
        {
            public function getValue(\Voyager\Database\Grammar $grammar)
            {
                return '1 = 1';
            }
        }
    );
    $this->assertSame('select * from "orders" where 1 = 1', $builder->toSql());
    $this->assertSame([], $builder->getBindings());
});

test('where row values', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('orders')->whereRowValues(['last_update', 'order_number'], '<', [1, 2]);
    $this->assertSame('select * from "orders" where ("last_update", "order_number") < (?, ?)', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('orders')->where('company_id', 1)->orWhereRowValues(['last_update', 'order_number'], '<', [1, 2]);
    $this->assertSame('select * from "orders" where "company_id" = ? or ("last_update", "order_number") < (?, ?)', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('orders')->whereRowValues(['last_update', 'order_number'], '<', [1, new Raw('2')]);
    $this->assertSame('select * from "orders" where ("last_update", "order_number") < (?, 2)', $builder->toSql());
    $this->assertEquals([1], $builder->getBindings());
});

test('where row values arity mismatch', function () {

    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('orders')->whereRowValues(['last_update'], '<', [1, 2]);
})->throws(InvalidArgumentException::class, 'The number of columns must match the number of values');

test('where json contains my sql', function () {
    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users')->whereJsonContains('options', ['en']);
    $this->assertSame('select * from `users` where json_contains(`options`, ?)', $builder->toSql());
    $this->assertEquals(['["en"]'], $builder->getBindings());

    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users')->whereJsonContains('users.options->languages', ['en']);
    $this->assertSame('select * from `users` where json_contains(`users`.`options`, ?, \'$."languages"\')', $builder->toSql());
    $this->assertEquals(['["en"]'], $builder->getBindings());

    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users')->where('id', '=', 1)->orWhereJsonContains('options->languages', new Raw("'[\"en\"]'"));
    $this->assertSame('select * from `users` where `id` = ? or json_contains(`options`, \'["en"]\', \'$."languages"\')', $builder->toSql());
    $this->assertEquals([1], $builder->getBindings());
});

test('where json overlaps my sql', function () {
    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users')->whereJsonOverlaps('options', ['en', 'fr']);
    $this->assertSame('select * from `users` where json_overlaps(`options`, ?)', $builder->toSql());
    $this->assertEquals(['["en","fr"]'], $builder->getBindings());

    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users')->whereJsonOverlaps('users.options->languages', ['en', 'fr']);
    $this->assertSame('select * from `users` where json_overlaps(`users`.`options`, ?, \'$."languages"\')', $builder->toSql());
    $this->assertEquals(['["en","fr"]'], $builder->getBindings());

    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users')->where('id', '=', 1)->orWhereJsonOverlaps('options->languages', new Raw("'[\"en\", \"fr\"]'"));
    $this->assertSame('select * from `users` where `id` = ? or json_overlaps(`options`, \'["en", "fr"]\', \'$."languages"\')', $builder->toSql());
    $this->assertEquals([1], $builder->getBindings());
});

test('where json contains postgres', function () {
    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('*')->from('users')->whereJsonContains('options', ['en']);
    $this->assertSame('select * from "users" where ("options")::jsonb @> ?', $builder->toSql());
    $this->assertEquals(['["en"]'], $builder->getBindings());

    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('*')->from('users')->whereJsonContains('users.options->languages', ['en']);
    $this->assertSame('select * from "users" where ("users"."options"->\'languages\')::jsonb @> ?', $builder->toSql());
    $this->assertEquals(['["en"]'], $builder->getBindings());

    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('*')->from('users')->where('id', '=', 1)->orWhereJsonContains('options->languages', new Raw("'[\"en\"]'"));
    $this->assertSame('select * from "users" where "id" = ? or ("options"->\'languages\')::jsonb @> \'["en"]\'', $builder->toSql());
    $this->assertEquals([1], $builder->getBindings());
});

test('where json contains sqlite', function () {
    $builder = queryBuilderGetSQLiteBuilder();
    $builder->select('*')->from('users')->whereJsonContains('options', 'en')->toSql();
    $this->assertSame('select * from "users" where exists (select 1 from json_each("options") where "json_each"."value" is ?)', $builder->toSql());
    $this->assertEquals(['en'], $builder->getBindings());

    $builder = queryBuilderGetSQLiteBuilder();
    $builder->select('*')->from('users')->whereJsonContains('users.options->language', 'en')->toSql();
    $this->assertSame('select * from "users" where exists (select 1 from json_each("users"."options", \'$."language"\') where "json_each"."value" is ?)', $builder->toSql());
    $this->assertEquals(['en'], $builder->getBindings());
});

test('where json contains sql server', function () {
    $builder = queryBuilderGetSqlServerBuilder();
    $builder->select('*')->from('users')->whereJsonContains('options', true);
    $this->assertSame('select * from [users] where ? in (select [value] from openjson([options]))', $builder->toSql());
    $this->assertEquals(['true'], $builder->getBindings());

    $builder = queryBuilderGetSqlServerBuilder();
    $builder->select('*')->from('users')->whereJsonContains('users.options->languages', 'en');
    $this->assertSame('select * from [users] where ? in (select [value] from openjson([users].[options], \'$."languages"\'))', $builder->toSql());
    $this->assertEquals(['en'], $builder->getBindings());

    $builder = queryBuilderGetSqlServerBuilder();
    $builder->select('*')->from('users')->where('id', '=', 1)->orWhereJsonContains('options->languages', new Raw("'en'"));
    $this->assertSame('select * from [users] where [id] = ? or \'en\' in (select [value] from openjson([options], \'$."languages"\'))', $builder->toSql());
    $this->assertEquals([1], $builder->getBindings());
});

test('where json doesnt contain my sql', function () {
    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users')->whereJsonDoesntContain('options->languages', ['en']);
    $this->assertSame('select * from `users` where not json_contains(`options`, ?, \'$."languages"\')', $builder->toSql());
    $this->assertEquals(['["en"]'], $builder->getBindings());

    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users')->where('id', '=', 1)->orWhereJsonDoesntContain('options->languages', new Raw("'[\"en\"]'"));
    $this->assertSame('select * from `users` where `id` = ? or not json_contains(`options`, \'["en"]\', \'$."languages"\')', $builder->toSql());
    $this->assertEquals([1], $builder->getBindings());
});

test('where json doesnt overlap my sql', function () {
    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users')->whereJsonDoesntOverlap('options->languages', ['en', 'fr']);
    $this->assertSame('select * from `users` where not json_overlaps(`options`, ?, \'$."languages"\')', $builder->toSql());
    $this->assertEquals(['["en","fr"]'], $builder->getBindings());

    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users')->where('id', '=', 1)->orWhereJsonDoesntOverlap('options->languages', new Raw("'[\"en\", \"fr\"]'"));
    $this->assertSame('select * from `users` where `id` = ? or not json_overlaps(`options`, \'["en", "fr"]\', \'$."languages"\')', $builder->toSql());
    $this->assertEquals([1], $builder->getBindings());
});

test('where json doesnt contain postgres', function () {
    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('*')->from('users')->whereJsonDoesntContain('options->languages', ['en']);
    $this->assertSame('select * from "users" where not ("options"->\'languages\')::jsonb @> ?', $builder->toSql());
    $this->assertEquals(['["en"]'], $builder->getBindings());

    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('*')->from('users')->where('id', '=', 1)->orWhereJsonDoesntContain('options->languages', new Raw("'[\"en\"]'"));
    $this->assertSame('select * from "users" where "id" = ? or not ("options"->\'languages\')::jsonb @> \'["en"]\'', $builder->toSql());
    $this->assertEquals([1], $builder->getBindings());
});

test('where json doesnt contain sqlite', function () {
    $builder = queryBuilderGetSQLiteBuilder();
    $builder->select('*')->from('users')->whereJsonDoesntContain('options', 'en')->toSql();
    $this->assertSame('select * from "users" where not exists (select 1 from json_each("options") where "json_each"."value" is ?)', $builder->toSql());
    $this->assertEquals(['en'], $builder->getBindings());

    $builder = queryBuilderGetSQLiteBuilder();
    $builder->select('*')->from('users')->whereJsonDoesntContain('users.options->language', 'en')->toSql();
    $this->assertSame('select * from "users" where not exists (select 1 from json_each("users"."options", \'$."language"\') where "json_each"."value" is ?)', $builder->toSql());
    $this->assertEquals(['en'], $builder->getBindings());
});

test('where json doesnt contain sql server', function () {
    $builder = queryBuilderGetSqlServerBuilder();
    $builder->select('*')->from('users')->whereJsonDoesntContain('options->languages', 'en');
    $this->assertSame('select * from [users] where not ? in (select [value] from openjson([options], \'$."languages"\'))', $builder->toSql());
    $this->assertEquals(['en'], $builder->getBindings());

    $builder = queryBuilderGetSqlServerBuilder();
    $builder->select('*')->from('users')->where('id', '=', 1)->orWhereJsonDoesntContain('options->languages', new Raw("'en'"));
    $this->assertSame('select * from [users] where [id] = ? or not \'en\' in (select [value] from openjson([options], \'$."languages"\'))', $builder->toSql());
    $this->assertEquals([1], $builder->getBindings());
});

test('where json contains key my sql', function () {
    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users')->whereJsonContainsKey('users.options->languages');
    $this->assertSame('select * from `users` where ifnull(json_contains_path(`users`.`options`, \'one\', \'$."languages"\'), 0)', $builder->toSql());

    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users')->whereJsonContainsKey('options->language->primary');
    $this->assertSame('select * from `users` where ifnull(json_contains_path(`options`, \'one\', \'$."language"."primary"\'), 0)', $builder->toSql());

    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users')->where('id', '=', 1)->orWhereJsonContainsKey('options->languages');
    $this->assertSame('select * from `users` where `id` = ? or ifnull(json_contains_path(`options`, \'one\', \'$."languages"\'), 0)', $builder->toSql());

    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users')->whereJsonContainsKey('options->languages[0][1]');
    $this->assertSame('select * from `users` where ifnull(json_contains_path(`options`, \'one\', \'$."languages"[0][1]\'), 0)', $builder->toSql());
});

test('where json contains key postgres', function () {
    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('*')->from('users')->whereJsonContainsKey('users.options->languages');
    $this->assertSame('select * from "users" where coalesce(("users"."options")::jsonb ?? \'languages\', false)', $builder->toSql());

    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('*')->from('users')->whereJsonContainsKey('options->language->primary');
    $this->assertSame('select * from "users" where coalesce(("options"->\'language\')::jsonb ?? \'primary\', false)', $builder->toSql());

    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('*')->from('users')->where('id', '=', 1)->orWhereJsonContainsKey('options->languages');
    $this->assertSame('select * from "users" where "id" = ? or coalesce(("options")::jsonb ?? \'languages\', false)', $builder->toSql());

    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('*')->from('users')->whereJsonContainsKey('options->languages[0][1]');
    $this->assertSame('select * from "users" where case when jsonb_typeof(("options"->\'languages\'->0)::jsonb) = \'array\' then jsonb_array_length(("options"->\'languages\'->0)::jsonb) >= 2 else false end', $builder->toSql());

    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('*')->from('users')->whereJsonContainsKey('options->languages[-1]');
    $this->assertSame('select * from "users" where case when jsonb_typeof(("options"->\'languages\')::jsonb) = \'array\' then jsonb_array_length(("options"->\'languages\')::jsonb) >= 1 else false end', $builder->toSql());
});

test('where json contains key sqlite', function () {
    $builder = queryBuilderGetSQLiteBuilder();
    $builder->select('*')->from('users')->whereJsonContainsKey('users.options->languages');
    $this->assertSame('select * from "users" where json_type("users"."options", \'$."languages"\') is not null', $builder->toSql());

    $builder = queryBuilderGetSQLiteBuilder();
    $builder->select('*')->from('users')->whereJsonContainsKey('options->language->primary');
    $this->assertSame('select * from "users" where json_type("options", \'$."language"."primary"\') is not null', $builder->toSql());

    $builder = queryBuilderGetSQLiteBuilder();
    $builder->select('*')->from('users')->where('id', '=', 1)->orWhereJsonContainsKey('options->languages');
    $this->assertSame('select * from "users" where "id" = ? or json_type("options", \'$."languages"\') is not null', $builder->toSql());

    $builder = queryBuilderGetSQLiteBuilder();
    $builder->select('*')->from('users')->whereJsonContainsKey('options->languages[0][1]');
    $this->assertSame('select * from "users" where json_type("options", \'$."languages"[0][1]\') is not null', $builder->toSql());
});

test('where json contains key sql server', function () {
    $builder = queryBuilderGetSqlServerBuilder();
    $builder->select('*')->from('users')->whereJsonContainsKey('users.options->languages');
    $this->assertSame('select * from [users] where \'languages\' in (select [key] from openjson([users].[options]))', $builder->toSql());

    $builder = queryBuilderGetSqlServerBuilder();
    $builder->select('*')->from('users')->whereJsonContainsKey('options->language->primary');
    $this->assertSame('select * from [users] where \'primary\' in (select [key] from openjson([options], \'$."language"\'))', $builder->toSql());

    $builder = queryBuilderGetSqlServerBuilder();
    $builder->select('*')->from('users')->where('id', '=', 1)->orWhereJsonContainsKey('options->languages');
    $this->assertSame('select * from [users] where [id] = ? or \'languages\' in (select [key] from openjson([options]))', $builder->toSql());

    $builder = queryBuilderGetSqlServerBuilder();
    $builder->select('*')->from('users')->whereJsonContainsKey('options->languages[0][1]');
    $this->assertSame('select * from [users] where 1 in (select [key] from openjson([options], \'$."languages"[0]\'))', $builder->toSql());
});

test('where json doesnt contain key my sql', function () {
    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users')->whereJsonDoesntContainKey('options->languages');
    $this->assertSame('select * from `users` where not ifnull(json_contains_path(`options`, \'one\', \'$."languages"\'), 0)', $builder->toSql());

    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users')->where('id', '=', 1)->orWhereJsonDoesntContainKey('options->languages');
    $this->assertSame('select * from `users` where `id` = ? or not ifnull(json_contains_path(`options`, \'one\', \'$."languages"\'), 0)', $builder->toSql());

    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users')->whereJsonDoesntContainKey('options->languages[0][1]');
    $this->assertSame('select * from `users` where not ifnull(json_contains_path(`options`, \'one\', \'$."languages"[0][1]\'), 0)', $builder->toSql());
});

test('where json doesnt contain key postgres', function () {
    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('*')->from('users')->whereJsonDoesntContainKey('options->languages');
    $this->assertSame('select * from "users" where not coalesce(("options")::jsonb ?? \'languages\', false)', $builder->toSql());

    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('*')->from('users')->where('id', '=', 1)->orWhereJsonDoesntContainKey('options->languages');
    $this->assertSame('select * from "users" where "id" = ? or not coalesce(("options")::jsonb ?? \'languages\', false)', $builder->toSql());

    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('*')->from('users')->whereJsonDoesntContainKey('options->languages[0][1]');
    $this->assertSame('select * from "users" where not case when jsonb_typeof(("options"->\'languages\'->0)::jsonb) = \'array\' then jsonb_array_length(("options"->\'languages\'->0)::jsonb) >= 2 else false end', $builder->toSql());

    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('*')->from('users')->whereJsonDoesntContainKey('options->languages[-1]');
    $this->assertSame('select * from "users" where not case when jsonb_typeof(("options"->\'languages\')::jsonb) = \'array\' then jsonb_array_length(("options"->\'languages\')::jsonb) >= 1 else false end', $builder->toSql());
});

test('where json doesnt contain key sqlite', function () {
    $builder = queryBuilderGetSQLiteBuilder();
    $builder->select('*')->from('users')->whereJsonDoesntContainKey('options->languages');
    $this->assertSame('select * from "users" where not json_type("options", \'$."languages"\') is not null', $builder->toSql());

    $builder = queryBuilderGetSQLiteBuilder();
    $builder->select('*')->from('users')->where('id', '=', 1)->orWhereJsonDoesntContainKey('options->languages');
    $this->assertSame('select * from "users" where "id" = ? or not json_type("options", \'$."languages"\') is not null', $builder->toSql());

    $builder = queryBuilderGetSQLiteBuilder();
    $builder->select('*')->from('users')->where('id', '=', 1)->orWhereJsonDoesntContainKey('options->languages[0][1]');
    $this->assertSame('select * from "users" where "id" = ? or not json_type("options", \'$."languages"[0][1]\') is not null', $builder->toSql());
});

test('where json doesnt contain key sql server', function () {
    $builder = queryBuilderGetSqlServerBuilder();
    $builder->select('*')->from('users')->whereJsonDoesntContainKey('options->languages');
    $this->assertSame('select * from [users] where not \'languages\' in (select [key] from openjson([options]))', $builder->toSql());

    $builder = queryBuilderGetSqlServerBuilder();
    $builder->select('*')->from('users')->where('id', '=', 1)->orWhereJsonDoesntContainKey('options->languages');
    $this->assertSame('select * from [users] where [id] = ? or not \'languages\' in (select [key] from openjson([options]))', $builder->toSql());

    $builder = queryBuilderGetSqlServerBuilder();
    $builder->select('*')->from('users')->where('id', '=', 1)->orWhereJsonDoesntContainKey('options->languages[0][1]');
    $this->assertSame('select * from [users] where [id] = ? or not 1 in (select [key] from openjson([options], \'$."languages"[0]\'))', $builder->toSql());
});

test('where json length my sql', function () {
    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users')->whereJsonLength('options', 0);
    $this->assertSame('select * from `users` where json_length(`options`) = ?', $builder->toSql());
    $this->assertEquals([0], $builder->getBindings());

    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users')->whereJsonLength('users.options->languages', '>', 0);
    $this->assertSame('select * from `users` where json_length(`users`.`options`, \'$."languages"\') > ?', $builder->toSql());
    $this->assertEquals([0], $builder->getBindings());

    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users')->where('id', '=', 1)->orWhereJsonLength('options->languages', new Raw('0'));
    $this->assertSame('select * from `users` where `id` = ? or json_length(`options`, \'$."languages"\') = 0', $builder->toSql());
    $this->assertEquals([1], $builder->getBindings());

    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('*')->from('users')->where('id', '=', 1)->orWhereJsonLength('options->languages', '>', new Raw('0'));
    $this->assertSame('select * from `users` where `id` = ? or json_length(`options`, \'$."languages"\') > 0', $builder->toSql());
    $this->assertEquals([1], $builder->getBindings());
});

test('where json length postgres', function () {
    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('*')->from('users')->whereJsonLength('options', 0);
    $this->assertSame('select * from "users" where jsonb_array_length(("options")::jsonb) = ?', $builder->toSql());
    $this->assertEquals([0], $builder->getBindings());

    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('*')->from('users')->whereJsonLength('users.options->languages', '>', 0);
    $this->assertSame('select * from "users" where jsonb_array_length(("users"."options"->\'languages\')::jsonb) > ?', $builder->toSql());
    $this->assertEquals([0], $builder->getBindings());

    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('*')->from('users')->where('id', '=', 1)->orWhereJsonLength('options->languages', new Raw('0'));
    $this->assertSame('select * from "users" where "id" = ? or jsonb_array_length(("options"->\'languages\')::jsonb) = 0', $builder->toSql());
    $this->assertEquals([1], $builder->getBindings());

    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('*')->from('users')->where('id', '=', 1)->orWhereJsonLength('options->languages', '>', new Raw('0'));
    $this->assertSame('select * from "users" where "id" = ? or jsonb_array_length(("options"->\'languages\')::jsonb) > 0', $builder->toSql());
    $this->assertEquals([1], $builder->getBindings());
});

test('where json length sqlite', function () {
    $builder = queryBuilderGetSQLiteBuilder();
    $builder->select('*')->from('users')->whereJsonLength('options', 0);
    $this->assertSame('select * from "users" where json_array_length("options") = ?', $builder->toSql());
    $this->assertEquals([0], $builder->getBindings());

    $builder = queryBuilderGetSQLiteBuilder();
    $builder->select('*')->from('users')->whereJsonLength('users.options->languages', '>', 0);
    $this->assertSame('select * from "users" where json_array_length("users"."options", \'$."languages"\') > ?', $builder->toSql());
    $this->assertEquals([0], $builder->getBindings());

    $builder = queryBuilderGetSQLiteBuilder();
    $builder->select('*')->from('users')->where('id', '=', 1)->orWhereJsonLength('options->languages', new Raw('0'));
    $this->assertSame('select * from "users" where "id" = ? or json_array_length("options", \'$."languages"\') = 0', $builder->toSql());
    $this->assertEquals([1], $builder->getBindings());

    $builder = queryBuilderGetSQLiteBuilder();
    $builder->select('*')->from('users')->where('id', '=', 1)->orWhereJsonLength('options->languages', '>', new Raw('0'));
    $this->assertSame('select * from "users" where "id" = ? or json_array_length("options", \'$."languages"\') > 0', $builder->toSql());
    $this->assertEquals([1], $builder->getBindings());
});

test('where json length sql server', function () {
    $builder = queryBuilderGetSqlServerBuilder();
    $builder->select('*')->from('users')->whereJsonLength('options', 0);
    $this->assertSame('select * from [users] where (select count(*) from openjson([options])) = ?', $builder->toSql());
    $this->assertEquals([0], $builder->getBindings());

    $builder = queryBuilderGetSqlServerBuilder();
    $builder->select('*')->from('users')->whereJsonLength('users.options->languages', '>', 0);
    $this->assertSame('select * from [users] where (select count(*) from openjson([users].[options], \'$."languages"\')) > ?', $builder->toSql());
    $this->assertEquals([0], $builder->getBindings());

    $builder = queryBuilderGetSqlServerBuilder();
    $builder->select('*')->from('users')->where('id', '=', 1)->orWhereJsonLength('options->languages', new Raw('0'));
    $this->assertSame('select * from [users] where [id] = ? or (select count(*) from openjson([options], \'$."languages"\')) = 0', $builder->toSql());
    $this->assertEquals([1], $builder->getBindings());

    $builder = queryBuilderGetSqlServerBuilder();
    $builder->select('*')->from('users')->where('id', '=', 1)->orWhereJsonLength('options->languages', '>', new Raw('0'));
    $this->assertSame('select * from [users] where [id] = ? or (select count(*) from openjson([options], \'$."languages"\')) > 0', $builder->toSql());
    $this->assertEquals([1], $builder->getBindings());
});

test('from', function () {
    $builder = queryBuilderGetBuilder();
    $builder->from(queryBuilderGetBuilder()->from('users'), 'u');
    $this->assertSame('select * from (select * from "users") as "u"', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $instrumentBuilder = new InstrumentBuilder(queryBuilderGetBuilder());
    $builder->from($instrumentBuilder->from('users'), 'u');
    $this->assertSame('select * from (select * from "users") as "u"', $builder->toSql());
});

test('from sub', function () {
    $builder = queryBuilderGetBuilder();
    $builder->fromSub(function ($query) {
        $query->select(new Raw('max(last_seen_at) as last_seen_at'))->from('user_sessions')->where('foo', '=', '1');
    }, 'sessions')->where('bar', '<', '10');
    $this->assertSame('select * from (select max(last_seen_at) as last_seen_at from "user_sessions" where "foo" = ?) as "sessions" where "bar" < ?', $builder->toSql());
    $this->assertEquals(['1', '10'], $builder->getBindings());

    $builder = queryBuilderGetBuilder();
    $builder->fromSub(['invalid'], 'sessions')->where('bar', '<', '10');
})->throws(InvalidArgumentException::class);

test('from sub with prefix', function () {
    $builder = queryBuilderGetBuilder(prefix: 'prefix_');
    $builder->fromSub(function ($query) {
        $query->select(new Raw('max(last_seen_at) as last_seen_at'))->from('user_sessions')->where('foo', '=', '1');
    }, 'sessions')->where('bar', '<', '10');
    $this->assertSame('select * from (select max(last_seen_at) as last_seen_at from "prefix_user_sessions" where "foo" = ?) as "prefix_sessions" where "bar" < ?', $builder->toSql());
    $this->assertEquals(['1', '10'], $builder->getBindings());
});

test('from sub without bindings', function () {
    $builder = queryBuilderGetBuilder();
    $builder->fromSub(function ($query) {
        $query->select(new Raw('max(last_seen_at) as last_seen_at'))->from('user_sessions');
    }, 'sessions');
    $this->assertSame('select * from (select max(last_seen_at) as last_seen_at from "user_sessions") as "sessions"', $builder->toSql());

    $builder = queryBuilderGetBuilder();
    $builder->fromSub(['invalid'], 'sessions');
})->throws(InvalidArgumentException::class);

test('from raw', function () {
    $builder = queryBuilderGetBuilder();
    $builder->fromRaw(new Raw('(select max(last_seen_at) as last_seen_at from "user_sessions") as "sessions"'));
    $this->assertSame('select * from (select max(last_seen_at) as last_seen_at from "user_sessions") as "sessions"', $builder->toSql());
});

test('from raw on sql server', function () {
    $builder = queryBuilderGetSqlServerBuilder();
    $builder->fromRaw('dbo.[SomeNameWithRoundBrackets (test)]');
    $this->assertSame('select * from dbo.[SomeNameWithRoundBrackets (test)]', $builder->toSql());
});

test('from raw with where on the main query', function () {
    $builder = queryBuilderGetBuilder();
    $builder->fromRaw(new Raw('(select max(last_seen_at) as last_seen_at from "sessions") as "last_seen_at"'))->where('last_seen_at', '>', '1520652582');
    $this->assertSame('select * from (select max(last_seen_at) as last_seen_at from "sessions") as "last_seen_at" where "last_seen_at" > ?', $builder->toSql());
    $this->assertEquals(['1520652582'], $builder->getBindings());
});

test('from question mark operator on postgres', function () {
    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('*')->from('users')->where('roles', '?', 'superuser');
    $this->assertSame('select * from "users" where "roles" ?? ?', $builder->toSql());

    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('*')->from('users')->where('roles', '?|', 'superuser');
    $this->assertSame('select * from "users" where "roles" ??| ?', $builder->toSql());

    $builder = queryBuilderGetPostgresBuilder();
    $builder->select('*')->from('users')->where('roles', '?&', 'superuser');
    $this->assertSame('select * from "users" where "roles" ??& ?', $builder->toSql());
});

test('use index my sql', function () {
    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('foo')->from('users')->useIndex('test_index');
    $this->assertSame('select `foo` from `users` use index (test_index)', $builder->toSql());
});

test('force index my sql', function () {
    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('foo')->from('users')->forceIndex('test_index');
    $this->assertSame('select `foo` from `users` force index (test_index)', $builder->toSql());
});

test('ignore index my sql', function () {
    $builder = queryBuilderGetMySqlBuilder();
    $builder->select('foo')->from('users')->ignoreIndex('test_index');
    $this->assertSame('select `foo` from `users` ignore index (test_index)', $builder->toSql());
});

test('use index sqlite', function () {
    $builder = queryBuilderGetSQLiteBuilder();
    $builder->select('foo')->from('users')->useIndex('test_index');
    $this->assertSame('select "foo" from "users"', $builder->toSql());
});

test('force index sqlite', function () {
    $builder = queryBuilderGetSQLiteBuilder();
    $builder->select('foo')->from('users')->forceIndex('test_index');
    $this->assertSame('select "foo" from "users" indexed by test_index', $builder->toSql());
});

test('ignore index sqlite', function () {
    $builder = queryBuilderGetSQLiteBuilder();
    $builder->select('foo')->from('users')->ignoreIndex('test_index');
    $this->assertSame('select "foo" from "users"', $builder->toSql());
});

test('use index sql server', function () {
    $builder = queryBuilderGetSqlServerBuilder();
    $builder->select('foo')->from('users')->useIndex('test_index');
    $this->assertSame('select [foo] from [users]', $builder->toSql());
});

test('force index sql server', function () {
    $builder = queryBuilderGetSqlServerBuilder();
    $builder->select('foo')->from('users')->forceIndex('test_index');
    $this->assertSame('select [foo] from [users] with (index([test_index]))', $builder->toSql());
});

test('ignore index sql server', function () {
    $builder = queryBuilderGetSqlServerBuilder();
    $builder->select('foo')->from('users')->ignoreIndex('test_index');
    $this->assertSame('select [foo] from [users]', $builder->toSql());
});

test('clone', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users');
    $clone = $builder->clone()->where('email', 'foo');

    $this->assertNotSame($builder, $clone);
    $this->assertSame('select * from "users"', $builder->toSql());
    $this->assertSame('select * from "users" where "email" = ?', $clone->toSql());
});

test('clone without', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('email', 'foo')->orderBy('email');
    $clone = $builder->cloneWithout(['orders']);

    $this->assertSame('select * from "users" where "email" = ? order by "email" asc', $builder->toSql());
    $this->assertSame('select * from "users" where "email" = ?', $clone->toSql());
});

test('clone without bindings', function () {
    $builder = queryBuilderGetBuilder();
    $builder->select('*')->from('users')->where('email', 'foo')->orderBy('email');
    $clone = $builder->cloneWithout(['wheres'])->cloneWithoutBindings(['where']);

    $this->assertSame('select * from "users" where "email" = ? order by "email" asc', $builder->toSql());
    $this->assertEquals([0 => 'foo'], $builder->getBindings());

    $this->assertSame('select * from "users" order by "email" asc', $clone->toSql());
    $this->assertEquals([], $clone->getBindings());
});

test('to raw sql', function () {
    $connection = queryBuilderGetConnection();
    $connection->shouldReceive('prepareBindings')
        ->with(['foo'])
        ->andReturn(['foo']);
    $grammar = m::mock(Grammar::class, [$connection])->makePartial();
    $grammar->shouldReceive('substituteBindingsIntoRawSql')
        ->with('select * from "users" where "email" = ?', ['foo'])
        ->andReturn('select * from "users" where "email" = \'foo\'');
    $builder = new Builder($connection, $grammar, m::mock(Processor::class));
    $builder->select('*')->from('users')->where('email', 'foo');

    $this->assertSame('select * from "users" where "email" = \'foo\'', $builder->toRawSql());
});

function queryBuilderGetConnection(string $prefix = '')
{
    $connection = m::mock(Connection::class);
    $connection->shouldReceive('getDatabaseName')->andReturn('database');
    $connection->shouldReceive('getTablePrefix')->andReturn($prefix);

    return $connection;
}

function queryBuilderGetBuilder(string $prefix = '')
{
    $connection = queryBuilderGetConnection(prefix: $prefix);
    $grammar = new Grammar($connection);
    $processor = m::mock(Processor::class);

    return new Builder($connection, $grammar, $processor);
}

function queryBuilderGetPostgresBuilder(string $prefix = '')
{
    $connection = queryBuilderGetConnection(prefix: $prefix);
    $grammar = new PostgresGrammar($connection);
    $processor = m::mock(Processor::class);

    return new Builder($connection, $grammar, $processor);
}

function queryBuilderGetMySqlBuilder(string $prefix = '')
{
    $connection = queryBuilderGetConnection(prefix: $prefix);
    $grammar = new MySqlGrammar($connection);
    $processor = m::mock(Processor::class);

    return new Builder($connection, $grammar, $processor);
}

function queryBuilderGetMariaDbBuilder(string $prefix = '')
{
    $connection = queryBuilderGetConnection(prefix: $prefix);
    $grammar = new MariaDbGrammar($connection);
    $processor = m::mock(Processor::class);

    return new Builder($connection, $grammar, $processor);
}

function queryBuilderGetSQLiteBuilder(string $prefix = '')
{
    $connection = queryBuilderGetConnection(prefix: $prefix);
    $grammar = new SQLiteGrammar($connection);
    $processor = m::mock(Processor::class);

    return new Builder($connection, $grammar, $processor);
}

function queryBuilderGetSqlServerBuilder(string $prefix = '')
{
    $connection = queryBuilderGetConnection(prefix: $prefix);
    $grammar = new SqlServerGrammar($connection);
    $processor = m::mock(Processor::class);

    return new Builder($connection, $grammar, $processor);
}

function queryBuilderGetMySqlBuilderWithProcessor(string $prefix = '')
{
    $connection = queryBuilderGetConnection(prefix: $prefix);
    $grammar = new MySqlGrammar($connection);
    $processor = new MySqlProcessor;

    return new Builder($connection, $grammar, $processor);
}

function queryBuilderGetPostgresBuilderWithProcessor(string $prefix = '')
{
    $connection = queryBuilderGetConnection(prefix: $prefix);
    $grammar = new PostgresGrammar($connection);
    $processor = new PostgresProcessor;

    return new Builder($connection, $grammar, $processor);
}

/**
 * @return \Mockery\MockInterface|\Voyager\Database\Query\Builder
 */
function queryBuilderGetMockQueryBuilder()
{
    return m::mock(Builder::class, [
        $connection = queryBuilderGetConnection(),
        new Grammar($connection),
        m::mock(Processor::class),
    ])->makePartial();
}
