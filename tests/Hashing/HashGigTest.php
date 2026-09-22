<?php

use Voyager\Config\Repository as Config;
use Voyager\Hashing\HashGig;
use Voyager\Hashing\HashManager;
use Voyager\Vessel\ControlPanel;

beforeEach(function () {
    $vessel = ControlPanel::setInstance(new ControlPanel);           // handle() resolves 'hash' from the current container
    $vessel->registerInstance('config', new Config(['hashing' => ['bcrypt' => ['rounds' => 4]]]));
    $vessel->registerInstance('hash', new HashManager($vessel));
});

afterEach(fn () => ControlPanel::setInstance(null));

it('hashes with the default driver', function () {
    $hash = (new HashGig('secret'))->handle();

    expect(app('hash')->check('secret', $hash))->toBeTrue()
        ->and(app('hash')->info($hash)['algoName'])->toBe('bcrypt');
});

it('hashes with a named driver and options', function () {
    $hash = (new HashGig('secret', 'argon2id', ['time' => 1, 'memory' => 1024]))->handle();

    expect(app('hash')->info($hash)['algoName'])->toBe('argon2id');
});

it('survives serialization, which is how it reaches a worker', function () {
    $gig = unserialize(serialize(new HashGig('secret', 'bcrypt', ['rounds' => 4])));

    expect(app('hash')->check('secret', $gig->handle()))->toBeTrue();
});
