<?php

use Voyager\Auth\AuthManager;
use Voyager\Contracts\Auth\Authenticatable;
use Voyager\Contracts\Auth\Guard;
use Voyager\Contracts\Auth\UserProvider;
use Voyager\System\Application;
use Voyager\System\Testing\Concerns\InteractsWithAuthentication;

uses(InteractsWithAuthentication::class);

/** The credentials the provider double treats as valid. */
const VALID_CREDENTIALS = [
    'email' => 'someone@laravel.com',
    'password' => 'secret_password',
];

/**
 * Put a mocked application on the test case whose `auth` manager hands back a
 * guard double, and return that guard.
 */
function mockGuardOn($testCase): Mockery\MockInterface
{
    $guard = Mockery::mock(Guard::class);

    $auth = Mockery::mock(AuthManager::class);
    $auth->shouldReceive('guard')
        ->once()
        ->andReturn($guard);

    $testCase->app = Mockery::mock(Application::class);
    $testCase->app->shouldReceive('make')
        ->once()
        ->withArgs(['auth'])
        ->andReturn($auth);

    return $guard;
}

/** Arrange a user provider that only validates VALID_CREDENTIALS. */
function setupCredentialProvider($testCase, array $credentials): void
{
    $user = Mockery::mock(Authenticatable::class);

    $provider = Mockery::mock(UserProvider::class);

    $provider->shouldReceive('retrieveByCredentials')
        ->with($credentials)
        ->andReturn($user);

    $provider->shouldReceive('validateCredentials')
        ->with($user, $credentials)
        ->andReturn(VALID_CREDENTIALS === $credentials);

    mockGuardOn($testCase)
        ->shouldReceive('getProvider')
        ->once()
        ->andReturn($provider);
}

test('assertAuthenticated passes when the guard reports a user', function () {
    mockGuardOn($this)
        ->shouldReceive('check')
        ->once()
        ->andReturn(true);

    $this->assertAuthenticated();
});

test('assertGuest passes when the guard reports no user', function () {
    mockGuardOn($this)
        ->shouldReceive('check')
        ->once()
        ->andReturn(false);

    $this->assertGuest();
});

test('assertAuthenticatedAs compares the identifier', function () {
    $expected = Mockery::mock(Authenticatable::class);
    $expected->shouldReceive('getAuthIdentifier')
        ->andReturn('1');

    mockGuardOn($this)
        ->shouldReceive('user')
        ->once()
        ->andReturn($expected);

    $user = Mockery::mock(Authenticatable::class);
    $user->shouldReceive('getAuthIdentifier')
        ->andReturn('1');

    $this->assertAuthenticatedAs($user);
});

test('assertCredentials passes for credentials the provider validates', function () {
    setupCredentialProvider($this, VALID_CREDENTIALS);

    $this->assertCredentials(VALID_CREDENTIALS);
});

test('assertInvalidCredentials passes for credentials the provider rejects', function () {
    $credentials = [
        'email' => 'invalid',
        'password' => 'credentials',
    ];

    setupCredentialProvider($this, $credentials);

    $this->assertInvalidCredentials($credentials);
});
