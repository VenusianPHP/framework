<?php

use Voyager\Database\PostgresConnection;
use Voyager\Database\Query\Builder;
use Voyager\Database\Query\Grammars\PostgresGrammar;
use Voyager\Database\Query\Processors\Processor;

function vectorBuilder(): Builder
{
    return new Builder(
        Mockery::mock(PostgresConnection::class),
        Mockery::mock(PostgresGrammar::class),
        Mockery::mock(Processor::class),
    );
}

it('rejects a string where a vector is expected, instead of calling a missing embeddings generator', function (string $method, array $arguments) {
    expect(fn () => vectorBuilder()->{$method}('embedding', 'a sentence', ...$arguments))
        ->toThrow(InvalidArgumentException::class, 'Venusian does not generate embeddings');
})->with([
    'selectVectorDistance' => ['selectVectorDistance', []],
    'whereVectorSimilarTo' => ['whereVectorSimilarTo', []],
    'whereVectorDistanceLessThan' => ['whereVectorDistanceLessThan', [0.5]],
    'orderByVectorDistance' => ['orderByVectorDistance', []],
]);
