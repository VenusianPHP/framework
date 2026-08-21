<?php

use Voyager\Redis\Connections\PhpRedisClusterConnection;
use Mockery as m;

describe('PhpRedisClusterConnection', function () {
    test('it scans using default node', function () {
        $client = m::mock(\RedisCluster::class);
        $client->shouldReceive('_masters')->once()->andReturn([['127.0.0.1', '6379']]);
        $client->shouldReceive('scan')
            ->once()
            ->with(0, ['127.0.0.1', '6379'], '*', 10)
            ->andReturn(['key']);

        $connection = new PhpRedisClusterConnection($client);
        expect($connection->scan(0))->toEqual([0, ['key']]);
    });

    test('it only fetches default node once', function () {
        $client = m::mock(\RedisCluster::class);
        $client->shouldReceive('_masters')->once()->andReturn([['127.0.0.1', '6379']]);
        $client->shouldReceive('scan')->twice();

        $connection = new PhpRedisClusterConnection($client);
        $connection->scan(0);
        $connection->scan(0);
    });

    test('it scans using option node', function () {
        $client = m::mock(\RedisCluster::class);
        $client->shouldReceive('scan')
            ->once()
            ->with(0, 'option-node', '*', 10)
            ->andReturn(['key']);

        $connection = new PhpRedisClusterConnection($client);
        expect($connection->scan(0, ['node' => 'option-node']))->toEqual([0, ['key']]);
    });

    test('it throws exception without nodes', function () {
        $client = m::mock(\RedisCluster::class);
        $client->shouldReceive('_masters')->once()->andReturn([]);
        $client->shouldReceive('scan');

        $connection = new PhpRedisClusterConnection($client);
        $connection->scan(0);
    })->throws(InvalidArgumentException::class, 'Unable to determine default node. No master nodes found in the cluster.');

    test('it returns false when cursor is zero and result is empty', function () {
        $client = m::mock(\RedisCluster::class);
        $client->shouldReceive('_masters')->once()->andReturn([['127.0.0.1', '6379']]);
        $client->shouldReceive('scan')
            ->once()
            ->with(0, ['127.0.0.1', '6379'], '*', 10)
            ->andReturn(false);

        $connection = new PhpRedisClusterConnection($client);
        expect($connection->scan(0))->toBeFalse();
    });

    test('it flushes all master nodes', function () {
        $client = m::mock(\RedisCluster::class);
        $client->shouldReceive('_masters')->once()->andReturn([
            ['127.0.0.1', '6379'],
            ['127.0.0.2', '6379'],
        ]);
        $client->shouldReceive('flushdb')->once()->with(['127.0.0.1', '6379']);
        $client->shouldReceive('flushdb')->once()->with(['127.0.0.2', '6379']);

        $connection = new PhpRedisClusterConnection($client);
        $connection->flushdb();
    });

    test('it flushes all master nodes async', function () {
        $client = m::mock(\RedisCluster::class);
        $client->shouldReceive('_masters')->once()->andReturn([
            ['127.0.0.1', '6379'],
            ['127.0.0.2', '6379'],
        ]);
        $client->shouldReceive('rawCommand')->once()->with(['127.0.0.1', '6379'], 'flushdb', 'async');
        $client->shouldReceive('rawCommand')->once()->with(['127.0.0.2', '6379'], 'flushdb', 'async');

        $connection = new PhpRedisClusterConnection($client);
        $connection->flushdb('ASYNC');
    });
})->skip(! extension_loaded('redis'), 'The redis extension is not installed.');
