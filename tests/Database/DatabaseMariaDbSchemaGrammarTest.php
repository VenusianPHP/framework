<?php

use Voyager\Database\Connection;
use Voyager\Database\Query\Expression;
use Voyager\Database\Schema\Blueprint;
use Voyager\Database\Schema\ForeignIdColumnDefinition;
use Voyager\Database\Schema\Grammars\MariaDbGrammar;
use Voyager\Database\Schema\MariaDbBuilder;
use Tests\Database\Fixtures\Enums\Foo;
use Mockery as m;

function mariaDbSchemaConnection(
    ?MariaDbGrammar $grammar = null,
    ?MariaDbBuilder $builder = null,
    string $prefix = ''
) {
    $connection = m::mock(Connection::class)
        ->shouldReceive('getTablePrefix')->andReturn($prefix)
        ->shouldReceive('getConfig')->with('prefix_indexes')->andReturn(null)
        ->getMock();

    $grammar ??= mariaDbSchemaGrammar($connection);
    $builder ??= mariaDbSchemaBuilder();

    return $connection
        ->shouldReceive('getSchemaGrammar')->andReturn($grammar)
        ->shouldReceive('getSchemaBuilder')->andReturn($builder)
        ->getMock();
}

function mariaDbSchemaGrammar(?Connection $connection = null)
{
    return new MariaDbGrammar($connection ?? mariaDbSchemaConnection());
}

function mariaDbSchemaBuilder()
{
    return mock(MariaDbBuilder::class);
}

test('basic create table', function () {
    $conn = mariaDbSchemaConnection();
    $conn->shouldReceive('getConfig')->once()->with('charset')->andReturn('utf8');
    $conn->shouldReceive('getConfig')->once()->with('collation')->andReturn('utf8_unicode_ci');
    $conn->shouldReceive('getConfig')->once()->with('engine')->andReturn(null);

    $blueprint = new Blueprint($conn, 'users');
    $blueprint->create();
    $blueprint->increments('id');
    $blueprint->string('email');

    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame("create table `users` (`id` int unsigned not null auto_increment primary key, `email` varchar(255) not null) default character set utf8 collate 'utf8_unicode_ci'", $statements[0]);

    $conn = mariaDbSchemaConnection();
    $conn->shouldReceive('getConfig')->andReturn(null);

    $blueprint = new Blueprint($conn, 'users');
    $blueprint->increments('id');
    $blueprint->string('email');

    $statements = $blueprint->toSql();

    $this->assertCount(2, $statements);
    $this->assertSame([
        'alter table `users` add `id` int unsigned not null auto_increment primary key',
        'alter table `users` add `email` varchar(255) not null',
    ], $statements);

    $conn = mariaDbSchemaConnection();
    $conn->shouldReceive('getConfig')->andReturn(null);
    $conn->shouldReceive('getServerVersion')->andReturn('10.7.0');

    $blueprint = new Blueprint($conn, 'users');
    $blueprint->create();
    $blueprint->uuid('id')->primary();

    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('create table `users` (`id` uuid not null, primary key (`id`))', $statements[0]);
});

test('auto increment starting value', function () {
    $conn = mariaDbSchemaConnection();
    $conn->shouldReceive('getConfig')->once()->with('charset')->andReturn('utf8');
    $conn->shouldReceive('getConfig')->once()->with('collation')->andReturn('utf8_unicode_ci');
    $conn->shouldReceive('getConfig')->once()->with('engine')->andReturn(null);

    $blueprint = new Blueprint($conn, 'users');
    $blueprint->create();
    $blueprint->increments('id')->startingValue(1000);
    $blueprint->string('email');

    $statements = $blueprint->toSql();

    $this->assertCount(2, $statements);
    $this->assertSame("create table `users` (`id` int unsigned not null auto_increment primary key, `email` varchar(255) not null) default character set utf8 collate 'utf8_unicode_ci'", $statements[0]);
    $this->assertSame('alter table `users` auto_increment = 1000', $statements[1]);
});

test('add columns with multiple auto increment starting value', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->id()->from(100);
    $blueprint->string('name')->from(200);
    $statements = $blueprint->toSql();

    $this->assertEquals([
        'alter table `users` add `id` bigint unsigned not null auto_increment primary key',
        'alter table `users` add `name` varchar(255) not null',
        'alter table `users` auto_increment = 100',
    ], $statements);
});

test('engine create table', function () {
    $conn = mariaDbSchemaConnection();
    $conn->shouldReceive('getConfig')->once()->with('charset')->andReturn('utf8');
    $conn->shouldReceive('getConfig')->once()->with('collation')->andReturn('utf8_unicode_ci');

    $blueprint = new Blueprint($conn, 'users');
    $blueprint->create();
    $blueprint->increments('id');
    $blueprint->string('email');
    $blueprint->engine('InnoDB');

    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame("create table `users` (`id` int unsigned not null auto_increment primary key, `email` varchar(255) not null) default character set utf8 collate 'utf8_unicode_ci' engine = InnoDB", $statements[0]);

    $conn = mariaDbSchemaConnection();
    $conn->shouldReceive('getConfig')->once()->with('charset')->andReturn('utf8');
    $conn->shouldReceive('getConfig')->once()->with('collation')->andReturn('utf8_unicode_ci');
    $conn->shouldReceive('getConfig')->once()->with('engine')->andReturn('InnoDB');

    $blueprint = new Blueprint($conn, 'users');
    $blueprint->create();
    $blueprint->increments('id');
    $blueprint->string('email');

    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame("create table `users` (`id` int unsigned not null auto_increment primary key, `email` varchar(255) not null) default character set utf8 collate 'utf8_unicode_ci' engine = InnoDB", $statements[0]);
});

test('charset collation create table', function () {
    $conn = mariaDbSchemaConnection();
    $conn->shouldReceive('getConfig')->once()->with('engine')->andReturn(null);

    $blueprint = new Blueprint($conn, 'users');
    $blueprint->create();
    $blueprint->increments('id');
    $blueprint->string('email');
    $blueprint->charset('utf8mb4');
    $blueprint->collation('utf8mb4_unicode_ci');

    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame("create table `users` (`id` int unsigned not null auto_increment primary key, `email` varchar(255) not null) default character set utf8mb4 collate 'utf8mb4_unicode_ci'", $statements[0]);

    $conn = mariaDbSchemaConnection();
    $conn->shouldReceive('getConfig')->once()->with('charset')->andReturn('utf8');
    $conn->shouldReceive('getConfig')->once()->with('collation')->andReturn('utf8_unicode_ci');
    $conn->shouldReceive('getConfig')->once()->with('engine')->andReturn(null);

    $blueprint = new Blueprint($conn, 'users');
    $blueprint->create();
    $blueprint->increments('id');
    $blueprint->string('email')->charset('utf8mb4')->collation('utf8mb4_unicode_ci');

    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame("create table `users` (`id` int unsigned not null auto_increment primary key, `email` varchar(255) character set utf8mb4 collate 'utf8mb4_unicode_ci' not null) default character set utf8 collate 'utf8_unicode_ci'", $statements[0]);
});

test('basic create table with prefix', function () {
    $conn = mariaDbSchemaConnection(prefix: 'prefix_');
    $conn->shouldReceive('getConfig')->andReturn(null);

    $blueprint = new Blueprint($conn, 'users');
    $blueprint->create();
    $blueprint->increments('id');
    $blueprint->string('email');

    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('create table `prefix_users` (`id` int unsigned not null auto_increment primary key, `email` varchar(255) not null)', $statements[0]);
});

test('create temporary table', function () {
    $conn = mariaDbSchemaConnection();
    $conn->shouldReceive('getConfig')->andReturn(null);

    $blueprint = new Blueprint($conn, 'users');
    $blueprint->create();
    $blueprint->temporary();
    $blueprint->increments('id');
    $blueprint->string('email');

    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('create temporary table `users` (`id` int unsigned not null auto_increment primary key, `email` varchar(255) not null)', $statements[0]);
});

test('drop table', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->drop();
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('drop table `users`', $statements[0]);
});

test('drop table if exists', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->dropIfExists();
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('drop table if exists `users`', $statements[0]);
});

test('drop column', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->dropColumn('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` drop `foo`', $statements[0]);

    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->dropColumn(['foo', 'bar']);
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` drop `foo`, drop `bar`', $statements[0]);

    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->dropColumn('foo', 'bar');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` drop `foo`, drop `bar`', $statements[0]);
});

test('drop primary', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->dropPrimary();
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` drop primary key', $statements[0]);
});

test('drop unique', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->dropUnique('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` drop index `foo`', $statements[0]);
});

test('drop index', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->dropIndex('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` drop index `foo`', $statements[0]);
});

test('drop spatial index', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'geo');
    $blueprint->dropSpatialIndex(['coordinates']);
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `geo` drop index `geo_coordinates_spatialindex`', $statements[0]);
});

test('drop foreign', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->dropForeign('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` drop foreign key `foo`', $statements[0]);
});

test('drop timestamps', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->dropTimestamps();
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` drop `created_at`, drop `updated_at`', $statements[0]);
});

test('drop timestamps tz', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->dropTimestampsTz();
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` drop `created_at`, drop `updated_at`', $statements[0]);
});

test('drop morphs', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'photos');
    $blueprint->dropMorphs('imageable');
    $statements = $blueprint->toSql();

    $this->assertCount(2, $statements);
    $this->assertSame('alter table `photos` drop index `photos_imageable_type_imageable_id_index`', $statements[0]);
    $this->assertSame('alter table `photos` drop `imageable_type`, drop `imageable_id`', $statements[1]);
});

test('rename table', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->rename('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('rename table `users` to `foo`', $statements[0]);
});

test('rename index', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->renameIndex('foo', 'bar');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` rename index `foo` to `bar`', $statements[0]);
});

test('adding primary key', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->primary('foo', 'bar');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add primary key (`foo`)', $statements[0]);
});

test('adding primary key with algorithm', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->primary('foo', 'bar', 'hash');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add primary key using hash(`foo`)', $statements[0]);
});

test('adding unique key', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->unique('foo', 'bar');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add unique `bar`(`foo`)', $statements[0]);
});

test('adding index', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->index(['foo', 'bar'], 'baz');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add index `baz`(`foo`, `bar`)', $statements[0]);
});

test('adding index with algorithm', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->index(['foo', 'bar'], 'baz', 'hash');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add index `baz` using hash(`foo`, `bar`)', $statements[0]);
});

test('adding fulltext index', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->fulltext('body');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add fulltext `users_body_fulltext`(`body`)', $statements[0]);
});

test('adding spatial index', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'geo');
    $blueprint->spatialIndex('coordinates');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `geo` add spatial index `geo_coordinates_spatialindex`(`coordinates`)', $statements[0]);
});

test('adding fluent spatial index', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'geo');
    $blueprint->geometry('coordinates', 'point')->spatialIndex();
    $statements = $blueprint->toSql();

    $this->assertCount(2, $statements);
    $this->assertSame('alter table `geo` add spatial index `geo_coordinates_spatialindex`(`coordinates`)', $statements[1]);
});

test('adding raw index', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->rawIndex('(function(column))', 'raw_index');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add index `raw_index`((function(column)))', $statements[0]);
});

test('adding foreign key', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->foreign('foo_id')->references('id')->on('orders');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add constraint `users_foo_id_foreign` foreign key (`foo_id`) references `orders` (`id`)', $statements[0]);

    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->foreign('foo_id')->references('id')->on('orders')->cascadeOnDelete();
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add constraint `users_foo_id_foreign` foreign key (`foo_id`) references `orders` (`id`) on delete cascade', $statements[0]);

    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->foreign('foo_id')->references('id')->on('orders')->cascadeOnUpdate();
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add constraint `users_foo_id_foreign` foreign key (`foo_id`) references `orders` (`id`) on update cascade', $statements[0]);
});

test('adding incrementing id', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->increments('id');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add `id` int unsigned not null auto_increment primary key', $statements[0]);
});

test('adding small incrementing id', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->smallIncrements('id');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add `id` smallint unsigned not null auto_increment primary key', $statements[0]);
});

test('adding id', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->id();
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add `id` bigint unsigned not null auto_increment primary key', $statements[0]);

    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->id('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add `foo` bigint unsigned not null auto_increment primary key', $statements[0]);
});

test('adding foreign id', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $foreignId = $blueprint->foreignId('foo');
    $blueprint->foreignId('company_id')->constrained();
    $blueprint->foreignId('laravel_idea_id')->constrained();
    $blueprint->foreignId('team_id')->references('id')->on('teams');
    $blueprint->foreignId('team_column_id')->constrained('teams');

    $statements = $blueprint->toSql();

    $this->assertInstanceOf(ForeignIdColumnDefinition::class, $foreignId);
    $this->assertSame([
        'alter table `users` add `foo` bigint unsigned not null',
        'alter table `users` add `company_id` bigint unsigned not null',
        'alter table `users` add constraint `users_company_id_foreign` foreign key (`company_id`) references `companies` (`id`)',
        'alter table `users` add `laravel_idea_id` bigint unsigned not null',
        'alter table `users` add constraint `users_laravel_idea_id_foreign` foreign key (`laravel_idea_id`) references `laravel_ideas` (`id`)',
        'alter table `users` add `team_id` bigint unsigned not null',
        'alter table `users` add constraint `users_team_id_foreign` foreign key (`team_id`) references `teams` (`id`)',
        'alter table `users` add `team_column_id` bigint unsigned not null',
        'alter table `users` add constraint `users_team_column_id_foreign` foreign key (`team_column_id`) references `teams` (`id`)',
    ], $statements);
});

test('adding foreign id specifying index name in constraint', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->foreignId('company_id')->constrained(indexName: 'my_index');
    $statements = $blueprint->toSql();
    $this->assertSame([
        'alter table `users` add `company_id` bigint unsigned not null',
        'alter table `users` add constraint `my_index` foreign key (`company_id`) references `companies` (`id`)',
    ], $statements);
});

test('adding big incrementing id', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->bigIncrements('id');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add `id` bigint unsigned not null auto_increment primary key', $statements[0]);
});

test('adding column in table first', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->string('name')->first();
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add `name` varchar(255) not null first', $statements[0]);
});

test('adding column after another column', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->string('name')->after('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add `name` varchar(255) not null after `foo`', $statements[0]);
});

test('adding multiple columns after another column', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->after('foo', function ($blueprint) {
        $blueprint->string('one');
        $blueprint->string('two');
    });
    $blueprint->string('three');
    $statements = $blueprint->toSql();
    $this->assertCount(3, $statements);
    $this->assertSame([
        'alter table `users` add `one` varchar(255) not null after `foo`',
        'alter table `users` add `two` varchar(255) not null after `one`',
        'alter table `users` add `three` varchar(255) not null',
    ], $statements);
});

test('adding generated column', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'products');
    $blueprint->integer('price');
    $blueprint->integer('discounted_virtual')->virtualAs('price - 5');
    $blueprint->integer('discounted_stored')->storedAs('price - 5');
    $statements = $blueprint->toSql();

    $this->assertCount(3, $statements);
    $this->assertSame([
        'alter table `products` add `price` int not null',
        'alter table `products` add `discounted_virtual` int as (price - 5)',
        'alter table `products` add `discounted_stored` int as (price - 5) stored',
    ], $statements);

    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'products');
    $blueprint->integer('price');
    $blueprint->integer('discounted_virtual')->virtualAs('price - 5')->nullable(false);
    $blueprint->integer('discounted_stored')->storedAs('price - 5')->nullable(false);
    $statements = $blueprint->toSql();

    $this->assertCount(3, $statements);
    $this->assertSame([
        'alter table `products` add `price` int not null',
        'alter table `products` add `discounted_virtual` int as (price - 5) not null',
        'alter table `products` add `discounted_stored` int as (price - 5) stored not null',
    ], $statements);
});

test('adding generated column with charset', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'links');
    $blueprint->string('url', 2083)->charset('ascii');
    $blueprint->string('url_hash_virtual', 64)->virtualAs('sha2(url, 256)')->charset('ascii');
    $blueprint->string('url_hash_stored', 64)->storedAs('sha2(url, 256)')->charset('ascii');
    $statements = $blueprint->toSql();

    $this->assertCount(3, $statements);
    $this->assertSame([
        'alter table `links` add `url` varchar(2083) character set ascii not null',
        'alter table `links` add `url_hash_virtual` varchar(64) character set ascii as (sha2(url, 256))',
        'alter table `links` add `url_hash_stored` varchar(64) character set ascii as (sha2(url, 256)) stored',
    ], $statements);
});

test('adding generated column by expression', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'products');
    $blueprint->integer('price');
    $blueprint->integer('discounted_virtual')->virtualAs(new Expression('price - 5'));
    $blueprint->integer('discounted_stored')->storedAs(new Expression('price - 5'));
    $statements = $blueprint->toSql();

    $this->assertCount(3, $statements);
    $this->assertSame([
        'alter table `products` add `price` int not null',
        'alter table `products` add `discounted_virtual` int as (price - 5)',
        'alter table `products` add `discounted_stored` int as (price - 5) stored',
    ], $statements);
});

test('adding invisible column', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->string('secret', 64)->nullable(false)->invisible();
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add `secret` varchar(64) not null invisible', $statements[0]);
});

test('adding string', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->string('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add `foo` varchar(255) not null', $statements[0]);

    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->string('foo', 100);
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add `foo` varchar(100) not null', $statements[0]);

    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->string('foo', 100)->nullable()->default('bar');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add `foo` varchar(100) null default \'bar\'', $statements[0]);

    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->string('foo', 100)->nullable()->default(new Expression('CURRENT TIMESTAMP'));
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add `foo` varchar(100) null default CURRENT TIMESTAMP', $statements[0]);

    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->string('foo', 100)->nullable()->default(Foo::BAR);
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add `foo` varchar(100) null default \'bar\'', $statements[0]);
});

test('adding text', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->text('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add `foo` text not null', $statements[0]);
});

test('adding big integer', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->bigInteger('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add `foo` bigint not null', $statements[0]);

    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->bigInteger('foo', true);
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add `foo` bigint not null auto_increment primary key', $statements[0]);
});

test('adding integer', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->integer('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add `foo` int not null', $statements[0]);

    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->integer('foo', true);
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add `foo` int not null auto_increment primary key', $statements[0]);
});

test('adding increments with starting values', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->id()->startingValue(1000);
    $statements = $blueprint->toSql();

    $this->assertCount(2, $statements);
    $this->assertSame('alter table `users` add `id` bigint unsigned not null auto_increment primary key', $statements[0]);
    $this->assertSame('alter table `users` auto_increment = 1000', $statements[1]);
});

test('adding medium integer', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->mediumInteger('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add `foo` mediumint not null', $statements[0]);

    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->mediumInteger('foo', true);
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add `foo` mediumint not null auto_increment primary key', $statements[0]);
});

test('adding small integer', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->smallInteger('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add `foo` smallint not null', $statements[0]);

    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->smallInteger('foo', true);
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add `foo` smallint not null auto_increment primary key', $statements[0]);
});

test('adding tiny integer', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->tinyInteger('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add `foo` tinyint not null', $statements[0]);

    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->tinyInteger('foo', true);
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add `foo` tinyint not null auto_increment primary key', $statements[0]);
});

test('adding float', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->float('foo', 5);
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add `foo` float(5) not null', $statements[0]);
});

test('adding double', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->double('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add `foo` double not null', $statements[0]);
});

test('adding decimal', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->decimal('foo', 5, 2);
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add `foo` decimal(5, 2) not null', $statements[0]);
});

test('adding boolean', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->boolean('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add `foo` tinyint(1) not null', $statements[0]);
});

test('adding enum', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->enum('role', ['member', 'admin']);
    $blueprint->enum('status', Foo::cases());
    $statements = $blueprint->toSql();

    $this->assertCount(2, $statements);
    $this->assertSame('alter table `users` add `role` enum(\'member\', \'admin\') not null', $statements[0]);
    $this->assertSame('alter table `users` add `status` enum(\'bar\') not null', $statements[1]);
});

test('adding set', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->set('role', ['member', 'admin']);
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add `role` set(\'member\', \'admin\') not null', $statements[0]);
});

test('adding json', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->json('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add `foo` json not null', $statements[0]);
});

test('adding jsonb', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->jsonb('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add `foo` json not null', $statements[0]);
});

test('adding date', function () {
    $conn = mariaDbSchemaConnection();
    $conn->shouldReceive('isMaria')->andReturn(true);
    $conn->shouldReceive('getServerVersion')->andReturn('10.3.0');

    $blueprint = new Blueprint($conn, 'users');
    $blueprint->date('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add `foo` date not null', $statements[0]);
});

test('adding date with default current', function () {
    $conn = mariaDbSchemaConnection();
    $conn->shouldReceive('isMaria')->andReturn(true);
    $conn->shouldReceive('getServerVersion')->andReturn('10.3.0');

    $blueprint = new Blueprint($conn, 'users');
    $blueprint->date('foo')->useCurrent();
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add `foo` date not null default (CURDATE())', $statements[0]);
});

test('adding year', function () {
    $conn = mariaDbSchemaConnection();
    $conn->shouldReceive('isMaria')->andReturn(true);
    $conn->shouldReceive('getServerVersion')->andReturn('10.3.0');

    $blueprint = new Blueprint($conn, 'users');
    $blueprint->year('birth_year');
    $statements = $blueprint->toSql();
    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add `birth_year` year not null', $statements[0]);
});

test('adding year with default current', function () {
    $conn = mariaDbSchemaConnection();
    $conn->shouldReceive('isMaria')->andReturn(true);
    $conn->shouldReceive('getServerVersion')->andReturn('10.3.0');

    $blueprint = new Blueprint($conn, 'users');
    $blueprint->year('birth_year')->useCurrent();
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add `birth_year` year not null default (YEAR(CURDATE()))', $statements[0]);
});

test('adding date time', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->dateTime('foo');
    $statements = $blueprint->toSql();
    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add `foo` datetime not null', $statements[0]);

    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->dateTime('foo', 1);
    $statements = $blueprint->toSql();
    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add `foo` datetime(1) not null', $statements[0]);
});

test('adding date time with default current', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->dateTime('foo')->useCurrent();
    $statements = $blueprint->toSql();
    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add `foo` datetime not null default CURRENT_TIMESTAMP', $statements[0]);
});

test('adding date time with on update current', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->dateTime('foo')->useCurrentOnUpdate();
    $statements = $blueprint->toSql();
    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add `foo` datetime not null on update CURRENT_TIMESTAMP', $statements[0]);
});

test('adding date time with default current and on update current', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->dateTime('foo')->useCurrent()->useCurrentOnUpdate();
    $statements = $blueprint->toSql();
    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add `foo` datetime not null default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP', $statements[0]);
});

test('adding date time with default current on update current and precision', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->dateTime('foo', 3)->useCurrent()->useCurrentOnUpdate();
    $statements = $blueprint->toSql();
    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add `foo` datetime(3) not null default CURRENT_TIMESTAMP(3) on update CURRENT_TIMESTAMP(3)', $statements[0]);
});

test('adding date time tz', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->dateTimeTz('foo', 1);
    $statements = $blueprint->toSql();
    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add `foo` datetime(1) not null', $statements[0]);

    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->dateTimeTz('foo');
    $statements = $blueprint->toSql();
    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add `foo` datetime not null', $statements[0]);
});

test('adding time', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->time('created_at');
    $statements = $blueprint->toSql();
    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add `created_at` time not null', $statements[0]);
});

test('adding time with precision', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->time('created_at', 1);
    $statements = $blueprint->toSql();
    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add `created_at` time(1) not null', $statements[0]);
});

test('adding time tz', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->timeTz('created_at');
    $statements = $blueprint->toSql();
    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add `created_at` time not null', $statements[0]);
});

test('adding time tz with precision', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->timeTz('created_at', 1);
    $statements = $blueprint->toSql();
    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add `created_at` time(1) not null', $statements[0]);
});

test('adding timestamp', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->timestamp('created_at');
    $statements = $blueprint->toSql();
    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add `created_at` timestamp not null', $statements[0]);
});

test('adding timestamp with precision', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->timestamp('created_at', 1);
    $statements = $blueprint->toSql();
    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add `created_at` timestamp(1) not null', $statements[0]);
});

test('adding timestamp with default', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->timestamp('created_at')->default('2015-07-22 11:43:17');
    $statements = $blueprint->toSql();
    $this->assertCount(1, $statements);
    $this->assertSame("alter table `users` add `created_at` timestamp not null default '2015-07-22 11:43:17'", $statements[0]);
});

test('adding timestamp with default current specifying precision', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->timestamp('created_at', 1)->useCurrent();
    $statements = $blueprint->toSql();
    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add `created_at` timestamp(1) not null default CURRENT_TIMESTAMP(1)', $statements[0]);
});

test('adding timestamp with on update current specifying precision', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->timestamp('created_at', 1)->useCurrentOnUpdate();
    $statements = $blueprint->toSql();
    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add `created_at` timestamp(1) not null on update CURRENT_TIMESTAMP(1)', $statements[0]);
});

test('adding timestamp with default current and on update current specifying precision', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->timestamp('created_at', 1)->useCurrent()->useCurrentOnUpdate();
    $statements = $blueprint->toSql();
    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add `created_at` timestamp(1) not null default CURRENT_TIMESTAMP(1) on update CURRENT_TIMESTAMP(1)', $statements[0]);
});

test('adding timestamp tz', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->timestampTz('created_at');
    $statements = $blueprint->toSql();
    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add `created_at` timestamp not null', $statements[0]);
});

test('adding timestamp tz with precision', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->timestampTz('created_at', 1);
    $statements = $blueprint->toSql();
    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add `created_at` timestamp(1) not null', $statements[0]);
});

test('adding time stamp tz with default', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->timestampTz('created_at')->default('2015-07-22 11:43:17');
    $statements = $blueprint->toSql();
    $this->assertCount(1, $statements);
    $this->assertSame("alter table `users` add `created_at` timestamp not null default '2015-07-22 11:43:17'", $statements[0]);
});

test('adding timestamps', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->timestamps();
    $statements = $blueprint->toSql();
    $this->assertCount(2, $statements);
    $this->assertSame([
        'alter table `users` add `created_at` timestamp null',
        'alter table `users` add `updated_at` timestamp null',
    ], $statements);
});

test('adding timestamps tz', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->timestampsTz();
    $statements = $blueprint->toSql();
    $this->assertCount(2, $statements);
    $this->assertSame([
        'alter table `users` add `created_at` timestamp null',
        'alter table `users` add `updated_at` timestamp null',
    ], $statements);
});

test('adding remember token', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->rememberToken();
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add `remember_token` varchar(100) null', $statements[0]);
});

test('adding binary', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->binary('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add `foo` blob not null', $statements[0]);
});

test('adding uuid', function () {
    $conn = mariaDbSchemaConnection();
    $conn->shouldReceive('getServerVersion')->andReturn('10.7.0');

    $blueprint = new Blueprint($conn, 'users');
    $blueprint->uuid('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add `foo` uuid not null', $statements[0]);
});

test('adding uuid on 106', function () {
    $conn = mariaDbSchemaConnection();
    $conn->shouldReceive('getServerVersion')->andReturn('10.6.21');

    $blueprint = new Blueprint($conn, 'users');
    $blueprint->uuid('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add `foo` char(36) not null', $statements[0]);
});

test('adding uuid defaults column name', function () {
    $conn = mariaDbSchemaConnection();
    $conn->shouldReceive('getServerVersion')->andReturn('10.7.0');

    $blueprint = new Blueprint($conn, 'users');
    $blueprint->uuid();
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add `uuid` uuid not null', $statements[0]);
});

test('adding foreign uuid', function () {
    $conn = mariaDbSchemaConnection();
    $conn->shouldReceive('getServerVersion')->andReturn('10.7.0');

    $blueprint = new Blueprint($conn, 'users');
    $foreignUuid = $blueprint->foreignUuid('foo');
    $blueprint->foreignUuid('company_id')->constrained();
    $blueprint->foreignUuid('laravel_idea_id')->constrained();
    $blueprint->foreignUuid('team_id')->references('id')->on('teams');
    $blueprint->foreignUuid('team_column_id')->constrained('teams');

    $statements = $blueprint->toSql();

    $this->assertInstanceOf(ForeignIdColumnDefinition::class, $foreignUuid);
    $this->assertSame([
        'alter table `users` add `foo` uuid not null',
        'alter table `users` add `company_id` uuid not null',
        'alter table `users` add constraint `users_company_id_foreign` foreign key (`company_id`) references `companies` (`id`)',
        'alter table `users` add `laravel_idea_id` uuid not null',
        'alter table `users` add constraint `users_laravel_idea_id_foreign` foreign key (`laravel_idea_id`) references `laravel_ideas` (`id`)',
        'alter table `users` add `team_id` uuid not null',
        'alter table `users` add constraint `users_team_id_foreign` foreign key (`team_id`) references `teams` (`id`)',
        'alter table `users` add `team_column_id` uuid not null',
        'alter table `users` add constraint `users_team_column_id_foreign` foreign key (`team_column_id`) references `teams` (`id`)',
    ], $statements);
});

test('adding ip address', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->ipAddress('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add `foo` varchar(45) not null', $statements[0]);
});

test('adding ip address defaults column name', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->ipAddress();
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add `ip_address` varchar(45) not null', $statements[0]);
});

test('adding mac address', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->macAddress('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add `foo` varchar(17) not null', $statements[0]);
});

test('adding mac address defaults column name', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->macAddress();
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `users` add `mac_address` varchar(17) not null', $statements[0]);
});

test('adding geometry', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'geo');
    $blueprint->geometry('coordinates');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `geo` add `coordinates` geometry not null', $statements[0]);
});

test('adding geography', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'geo');
    $blueprint->geography('coordinates');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `geo` add `coordinates` geometry ref_system_id=4326 not null', $statements[0]);
});

test('adding point', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'geo');
    $blueprint->geometry('coordinates', 'point');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `geo` add `coordinates` point not null', $statements[0]);
});

test('adding point with srid', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'geo');
    $blueprint->geometry('coordinates', 'point', 4326);
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `geo` add `coordinates` point ref_system_id=4326 not null', $statements[0]);
});

test('adding point with srid column', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'geo');
    $blueprint->geometry('coordinates', 'point', 4326)->after('id');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `geo` add `coordinates` point ref_system_id=4326 not null after `id`', $statements[0]);
});

test('adding line string', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'geo');
    $blueprint->geometry('coordinates', 'linestring');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `geo` add `coordinates` linestring not null', $statements[0]);
});

test('adding polygon', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'geo');
    $blueprint->geometry('coordinates', 'polygon');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `geo` add `coordinates` polygon not null', $statements[0]);
});

test('adding geometry collection', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'geo');
    $blueprint->geometry('coordinates', 'geometrycollection');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `geo` add `coordinates` geometrycollection not null', $statements[0]);
});

test('adding multi point', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'geo');
    $blueprint->geometry('coordinates', 'multipoint');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `geo` add `coordinates` multipoint not null', $statements[0]);
});

test('adding multi line string', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'geo');
    $blueprint->geometry('coordinates', 'multilinestring');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `geo` add `coordinates` multilinestring not null', $statements[0]);
});

test('adding multi polygon', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'geo');
    $blueprint->geometry('coordinates', 'multipolygon');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table `geo` add `coordinates` multipolygon not null', $statements[0]);
});

test('adding comment', function () {
    $blueprint = new Blueprint(mariaDbSchemaConnection(), 'users');
    $blueprint->string('foo')->comment("Escape ' when using words like it's");
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame("alter table `users` add `foo` varchar(255) not null comment 'Escape \\' when using words like it\\'s'", $statements[0]);
});

test('create database', function () {
    $connection = mariaDbSchemaConnection();
    $connection->shouldReceive('getConfig')->once()->once()->with('charset')->andReturn('utf8mb4_foo');
    $connection->shouldReceive('getConfig')->once()->once()->with('collation')->andReturn('utf8mb4_unicode_ci_foo');

    $statement = mariaDbSchemaGrammar($connection)->compileCreateDatabase('my_database_a');

    $this->assertSame(
        'create database `my_database_a` default character set `utf8mb4_foo` default collate `utf8mb4_unicode_ci_foo`',
        $statement
    );

    $connection = mariaDbSchemaConnection();
    $connection->shouldReceive('getConfig')->once()->once()->with('charset')->andReturn('utf8mb4_bar');
    $connection->shouldReceive('getConfig')->once()->once()->with('collation')->andReturn('utf8mb4_unicode_ci_bar');

    $statement = mariaDbSchemaGrammar($connection)->compileCreateDatabase('my_database_b');

    $this->assertSame(
        'create database `my_database_b` default character set `utf8mb4_bar` default collate `utf8mb4_unicode_ci_bar`',
        $statement
    );
});

test('create table with virtual as column', function () {
    $conn = mariaDbSchemaConnection();
    $conn->shouldReceive('getConfig')->once()->with('charset')->andReturn('utf8');
    $conn->shouldReceive('getConfig')->once()->with('collation')->andReturn('utf8_unicode_ci');
    $conn->shouldReceive('getConfig')->once()->with('engine')->andReturn(null);

    $blueprint = new Blueprint($conn, 'users');
    $blueprint->create();
    $blueprint->string('my_column');
    $blueprint->string('my_other_column')->virtualAs('my_column');

    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame("create table `users` (`my_column` varchar(255) not null, `my_other_column` varchar(255) as (my_column)) default character set utf8 collate 'utf8_unicode_ci'", $statements[0]);

    $conn = mariaDbSchemaConnection();
    $conn->shouldReceive('getConfig')->andReturn(null);

    $blueprint = new Blueprint($conn, 'users');
    $blueprint->create();
    $blueprint->string('my_json_column');
    $blueprint->string('my_other_column')->virtualAsJson('my_json_column->some_attribute');

    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame("create table `users` (`my_json_column` varchar(255) not null, `my_other_column` varchar(255) as (json_value(`my_json_column`, '$.\"some_attribute\"')))", $statements[0]);

    $conn = mariaDbSchemaConnection();
    $conn->shouldReceive('getConfig')->andReturn(null);

    $blueprint = new Blueprint($conn, 'users');
    $blueprint->create();
    $blueprint->string('my_json_column');
    $blueprint->string('my_other_column')->virtualAsJson('my_json_column->some_attribute->nested');

    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame("create table `users` (`my_json_column` varchar(255) not null, `my_other_column` varchar(255) as (json_value(`my_json_column`, '$.\"some_attribute\".\"nested\"')))", $statements[0]);
});

test('create table with virtual as column when json column has array key', function () {
    $conn = mariaDbSchemaConnection();
    $conn->shouldReceive('getConfig')->andReturn(null);

    $blueprint = new Blueprint($conn, 'users');
    $blueprint->create();
    $blueprint->string('my_json_column')->virtualAsJson('my_json_column->foo[0][1]');

    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame("create table `users` (`my_json_column` varchar(255) as (json_value(`my_json_column`, '$.\"foo\"[0][1]')))", $statements[0]);
});

test('create table with stored as column', function () {
    $conn = mariaDbSchemaConnection();
    $conn->shouldReceive('getConfig')->once()->with('charset')->andReturn('utf8');
    $conn->shouldReceive('getConfig')->once()->with('collation')->andReturn('utf8_unicode_ci');
    $conn->shouldReceive('getConfig')->once()->with('engine')->andReturn(null);

    $blueprint = new Blueprint($conn, 'users');
    $blueprint->create();
    $blueprint->string('my_column');
    $blueprint->string('my_other_column')->storedAs('my_column');

    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame("create table `users` (`my_column` varchar(255) not null, `my_other_column` varchar(255) as (my_column) stored) default character set utf8 collate 'utf8_unicode_ci'", $statements[0]);

    $conn = mariaDbSchemaConnection();
    $conn->shouldReceive('getConfig')->andReturn(null);

    $blueprint = new Blueprint($conn, 'users');
    $blueprint->create();
    $blueprint->string('my_json_column');
    $blueprint->string('my_other_column')->storedAsJson('my_json_column->some_attribute');

    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame("create table `users` (`my_json_column` varchar(255) not null, `my_other_column` varchar(255) as (json_value(`my_json_column`, '$.\"some_attribute\"')) stored)", $statements[0]);

    $conn = mariaDbSchemaConnection();
    $conn->shouldReceive('getConfig')->andReturn(null);

    $blueprint = new Blueprint($conn, 'users');
    $blueprint->create();
    $blueprint->string('my_json_column');
    $blueprint->string('my_other_column')->storedAsJson('my_json_column->some_attribute->nested');

    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame("create table `users` (`my_json_column` varchar(255) not null, `my_other_column` varchar(255) as (json_value(`my_json_column`, '$.\"some_attribute\".\"nested\"')) stored)", $statements[0]);
});

test('drop database if exists', function () {
    $statement = mariaDbSchemaGrammar()->compileDropDatabaseIfExists('my_database_a');

    $this->assertSame(
        'drop database if exists `my_database_a`',
        $statement
    );

    $statement = mariaDbSchemaGrammar()->compileDropDatabaseIfExists('my_database_b');

    $this->assertSame(
        'drop database if exists `my_database_b`',
        $statement
    );
});

test('drop all tables', function () {
    $connection = mariaDbSchemaConnection();
    $statement = mariaDbSchemaGrammar($connection)->compileDropAllTables(['alpha', 'beta', 'gamma']);

    $this->assertSame('drop table `alpha`, `beta`, `gamma`', $statement);
});

test('drop all views', function () {
    $statement = mariaDbSchemaGrammar()->compileDropAllViews(['alpha', 'beta', 'gamma']);

    $this->assertSame('drop view `alpha`, `beta`, `gamma`', $statement);
});

test('grammars are macroable', function () {
    // compileReplace macro.
    mariaDbSchemaGrammar()::macro('compileReplace', function () {
        return true;
    });

    $c = mariaDbSchemaGrammar()::compileReplace();

    $this->assertTrue($c);
});
