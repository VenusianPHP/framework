<?php

use Voyager\Config\Repository as Config;
use Voyager\Hashing\Argon2IdHasher;
use Voyager\Hashing\ArgonHasher;
use Voyager\Hashing\BcryptHasher;
use Voyager\Hashing\HashManager;
use Voyager\Vessel\Vessel;

beforeEach(function () {
    $vessel = Vessel::setInstance(new Vessel);
    $vessel->singleton('config', fn () => new Config());

    $this->hashManager = new HashManager($vessel);
});

test('an empty hashed value never checks out', function () {
    expect((new BcryptHasher())->check('password', ''))->toBeFalse()
        ->and((new ArgonHasher())->check('password', ''))->toBeFalse()
        ->and((new Argon2IdHasher())->check('password', ''))->toBeFalse();
});

test('a null hashed value never checks out', function () {
    expect((new BcryptHasher())->check('password', null))->toBeFalse()
        ->and((new ArgonHasher())->check('password', null))->toBeFalse()
        ->and((new Argon2IdHasher())->check('password', null))->toBeFalse();
});

test('bcrypt hashes, checks and reports rehash need', function () {
    $hasher = new BcryptHasher;
    $value = $hasher->make('password');

    expect($value)->not->toBe('password')
        ->and($hasher->check('password', $value))->toBeTrue()
        ->and($hasher->needsRehash($value))->toBeFalse()
        ->and($hasher->needsRehash($value, ['rounds' => 1]))->toBeTrue()
        ->and(password_get_info($value)['algoName'])->toBe('bcrypt')
        ->and(password_get_info($value)['options']['cost'])->toBeGreaterThanOrEqual(12)
        ->and($this->hashManager->isHashed($value))->toBeTrue();
});

test('bcrypt rejects a value longer than its limit', function () {
    (new BcryptHasher(['limit' => 72]))->make(str_repeat('a', 73));
})->throws(InvalidArgumentException::class);

test('argon2i hashes, checks and reports rehash need', function () {
    $hasher = new ArgonHasher;
    $value = $hasher->make('password');

    expect($value)->not->toBe('password')
        ->and($hasher->check('password', $value))->toBeTrue()
        ->and($hasher->needsRehash($value))->toBeFalse()
        ->and($hasher->needsRehash($value, ['threads' => 1]))->toBeTrue()
        ->and(password_get_info($value)['algoName'])->toBe('argon2i')
        ->and($this->hashManager->isHashed($value))->toBeTrue();
});

test('argon2id hashes, checks and reports rehash need', function () {
    $hasher = new Argon2IdHasher;
    $value = $hasher->make('password');

    expect($value)->not->toBe('password')
        ->and($hasher->check('password', $value))->toBeTrue()
        ->and($hasher->needsRehash($value))->toBeFalse()
        ->and($hasher->needsRehash($value, ['threads' => 1]))->toBeTrue()
        ->and(password_get_info($value)['algoName'])->toBe('argon2id')
        ->and($this->hashManager->isHashed($value))->toBeTrue();
});

test('bcrypt verification rejects an argon hash', function () {
    $argonHashed = (new ArgonHasher(['verify' => true]))->make('password');

    (new BcryptHasher(['verify' => true]))->check('password', $argonHashed);
})->depends('bcrypt hashes, checks and reports rehash need')
    ->throws(RuntimeException::class);

test('argon2i verification rejects a bcrypt hash', function () {
    $bcryptHashed = (new BcryptHasher(['verify' => true]))->make('password');

    (new ArgonHasher(['verify' => true]))->check('password', $bcryptHashed);
})->depends('argon2i hashes, checks and reports rehash need')
    ->throws(RuntimeException::class);

test('argon2id verification rejects a bcrypt hash', function () {
    $bcryptHashed = (new BcryptHasher(['verify' => true]))->make('password');

    (new Argon2IdHasher(['verify' => true]))->check('password', $bcryptHashed);
})->depends('argon2id hashes, checks and reports rehash need')
    ->throws(RuntimeException::class);

test('isHashed is false for a value that was never hashed', function () {
    expect($this->hashManager->isHashed('foo'))->toBeFalse();
});

test('bcrypt throws when the configured rounds are unsupported', function () {
    (new BcryptHasher(['rounds' => 0]))->make('password');
})->throws(RuntimeException::class);

test('argon2i throws when the configured time is unsupported', function () {
    (new ArgonHasher(['time' => 0]))->make('password');
})->throws(RuntimeException::class);

test('argon2id throws when the configured time is unsupported', function () {
    (new Argon2IdHasher(['time' => 0]))->make('password');
})->throws(RuntimeException::class);
