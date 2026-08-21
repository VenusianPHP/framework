<?php

use Voyager\Database\Connection;
use Voyager\Database\Query\Expression;
use Voyager\MagicAliases\MagicAlias;
use Voyager\NutsAndBolts\MagicAliases\DB;
use Voyager\System\Testing\Concerns\InteractsWithDatabase;
use Mockery as m;

beforeEach(function () {
    MagicAlias::clearResolvedInstances();
    MagicAlias::setMagicAliasApplication(null);
});

$castAsJson = function ($value, $grammar) {
    $connection = m::mock(Connection::class);
    $grammarClass = 'Voyager\Database\Query\Grammars\\'.$grammar.'Grammar';
    $grammar = new $grammarClass($connection);

    $connection->shouldReceive('getQueryGrammar')->andReturn($grammar);

    $connection->shouldReceive('raw')->andReturnUsing(function ($value) {
        return new Expression($value);
    });

    $connection->shouldReceive('getPdo->quote')->andReturnUsing(function ($value) {
        return "'".$value."'";
    });

    DB::shouldReceive('connection')->with(null)->andReturn($connection);

    $instance = new class
    {
        use InteractsWithDatabase;
    };

    return $instance->castAsJson($value)->getValue($grammar);
};

test('cast to json sqlite', function () use ($castAsJson) {
    $grammar = 'SQLite';

    expect($castAsJson(['foo', 'bar'], $grammar))->toEqual(<<<'TEXT'
    '["foo","bar"]'
    TEXT);

    expect($castAsJson(collect(['foo', 'bar']), $grammar))->toEqual(<<<'TEXT'
    '["foo","bar"]'
    TEXT);

    expect($castAsJson((object) ['foo' => 'bar'], $grammar))->toEqual(<<<'TEXT'
    '{"foo":"bar"}'
    TEXT);
});

test('cast to json postgres', function () use ($castAsJson) {
    $grammar = 'Postgres';

    expect($castAsJson(['foo', 'bar'], $grammar))->toEqual(<<<'TEXT'
    '["foo","bar"]'
    TEXT);

    expect($castAsJson(collect(['foo', 'bar']), $grammar))->toEqual(<<<'TEXT'
    '["foo","bar"]'
    TEXT);

    expect($castAsJson((object) ['foo' => 'bar'], $grammar))->toEqual(<<<'TEXT'
    '{"foo":"bar"}'
    TEXT);
});

test('cast to json sql server', function () use ($castAsJson) {
    $grammar = 'SqlServer';

    expect($castAsJson(['foo', 'bar'], $grammar))->toEqual(<<<'TEXT'
    json_query('["foo","bar"]')
    TEXT);

    expect($castAsJson(collect(['foo', 'bar']), $grammar))->toEqual(<<<'TEXT'
    json_query('["foo","bar"]')
    TEXT);

    expect($castAsJson((object) ['foo' => 'bar'], $grammar))->toEqual(<<<'TEXT'
    json_query('{"foo":"bar"}')
    TEXT);
});

test('cast to json my sql', function () use ($castAsJson) {
    $grammar = 'MySql';

    expect($castAsJson(['foo', 'bar'], $grammar))->toEqual(<<<'TEXT'
    cast('["foo","bar"]' as json)
    TEXT);

    expect($castAsJson(collect(['foo', 'bar']), $grammar))->toEqual(<<<'TEXT'
    cast('["foo","bar"]' as json)
    TEXT);

    expect($castAsJson((object) ['foo' => 'bar'], $grammar))->toEqual(<<<'TEXT'
    cast('{"foo":"bar"}' as json)
    TEXT);
});

test('cast to json maria db', function () use ($castAsJson) {
    $grammar = 'MariaDb';

    expect($castAsJson(['foo', 'bar'], $grammar))->toEqual(<<<'TEXT'
    json_query('["foo","bar"]', '$')
    TEXT);

    expect($castAsJson(collect(['foo', 'bar']), $grammar))->toEqual(<<<'TEXT'
    json_query('["foo","bar"]', '$')
    TEXT);

    expect($castAsJson((object) ['foo' => 'bar'], $grammar))->toEqual(<<<'TEXT'
    json_query('{"foo":"bar"}', '$')
    TEXT);
});
