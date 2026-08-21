<?php

use Voyager\Broadcasting\Broadcasters\Broadcaster;
use Voyager\Broadcasting\Broadcasters\UsePusherChannelConventions;

test('channel name normalization', function ($requestChannelName, $normalizedName, $guarded) {
    $broadcaster = new FakeBroadcasterUsingPusherChannelsNames;

    $this->assertSame(
        $normalizedName,
        $broadcaster->normalizeChannelName($requestChannelName)
    );
})->with(usePusherChannelsNamesChannelsProvider());

test('channel name normalization special case', function () {
    $broadcaster = new FakeBroadcasterUsingPusherChannelsNames;

    $this->assertSame(
        'private-123',
        $broadcaster->normalizeChannelName('private-encrypted-private-123')
    );
});

test('channel name pattern matching', function () {
    $broadcaster = new FakeBroadcasterUsingPusherChannelsNames;

    $this->assertEquals(
        0,
        $broadcaster->testChannelNameMatchesPattern(
            'TestChannel',
            'Test.{id}'
        )
    );
});

test('is guarded channel', function ($requestChannelName, $normalizedName, $guarded) {
    $broadcaster = new FakeBroadcasterUsingPusherChannelsNames;

    $this->assertSame(
        $guarded,
        $broadcaster->isGuardedChannel($requestChannelName)
    );
})->with(usePusherChannelsNamesChannelsProvider());

function usePusherChannelsNamesChannelsProvider()
{
    $prefixesInfos = [
        ['prefix' => 'private-', 'guarded' => true],
        ['prefix' => 'private-encrypted-', 'guarded' => true],
        ['prefix' => 'presence-', 'guarded' => true],
        ['prefix' => '', 'guarded' => false],
    ];

    $channels = [
        'test',
        'test-channel',
        'test-private-channel',
        'test-presence-channel',
        'abcd.efgh',
        'abcd.efgh.ijkl',
        'test.{param}',
        'test-{param}',
        '{a}.{b}',
        '{a}-{b}',
        '{a}-{b}.{c}',
    ];

    $tests = [];
    foreach ($prefixesInfos as $prefixInfos) {
        foreach ($channels as $channel) {
            $tests[] = [
                $prefixInfos['prefix'].$channel,
                $channel,
                $prefixInfos['guarded'],
            ];
        }
    }

    $tests[] = ['private-private-test', 'private-test', true];
    $tests[] = ['private-presence-test', 'presence-test', true];
    $tests[] = ['presence-private-test', 'private-test', true];
    $tests[] = ['presence-presence-test', 'presence-test', true];
    $tests[] = ['public-test', 'public-test', false];

    return $tests;
}

class FakeBroadcasterUsingPusherChannelsNames extends Broadcaster
{
    use UsePusherChannelConventions;

    public function auth($request)
    {
        //
    }

    public function validAuthenticationResponse($request, $result)
    {
        //
    }

    public function broadcast(array $channels, $event, array $payload = [])
    {
        //
    }

    public function testChannelNameMatchesPattern($channel, $pattern)
    {
        return $this->channelNameMatchesPattern($channel, $pattern);
    }
}
