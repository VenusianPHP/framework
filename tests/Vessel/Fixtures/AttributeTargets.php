<?php

/*
|--------------------------------------------------------------------------
| Contextual-attribute injection targets
|--------------------------------------------------------------------------
|
| Classes whose constructor parameters carry a contextual attribute. Their
| upstream names end in "Test", so they cannot live in one-class-per-file
| PSR-4 fixtures without being collected as test files; they are autoloaded
| through composer's `autoload-dev.files` instead.
|
*/

namespace Tests\Vessel\Fixtures;

use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Guard as GuardContract;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Connection;
use Illuminate\Database\Instrument\Model;
use Psr\Log\LoggerInterface;
use Voyager\Vessel\Attributes\Auth;
use Voyager\Vessel\Attributes\Authenticated;
use Voyager\Vessel\Attributes\Cache;
use Voyager\Vessel\Attributes\Config;
use Voyager\Vessel\Attributes\Context;
use Voyager\Vessel\Attributes\CurrentUser;
use Voyager\Vessel\Attributes\Database;
use Voyager\Vessel\Attributes\Log;
use Voyager\Vessel\Attributes\RouteParameter;
use Voyager\Vessel\Attributes\Storage;

final class AuthedTest
{
    public function __construct(#[Authenticated('foo')] AuthenticatableContract $foo, #[CurrentUser('bar')] AuthenticatableContract $bar)
    {
    }
}

final class CacheTest
{
    public function __construct(#[Cache('foo')] CacheRepository $foo, #[Cache('bar')] CacheRepository $bar)
    {
    }
}

final class ConfigTest
{
    public function __construct(#[Config('foo')] string $foo, #[Config('bar')] string $bar)
    {
    }
}

final class ContextTest
{
    public function __construct(#[Context('foo')] string $foo)
    {
    }
}

final class ContextHiddenTest
{
    public function __construct(#[Context('bar', hidden: true)] string $foo)
    {
    }
}

final class DatabaseTest
{
    public function __construct(#[Database('foo')] Connection $foo, #[Database('bar')] Connection $bar)
    {
    }
}

final class GuardTest
{
    public function __construct(#[Auth('foo')] GuardContract $foo, #[Auth('bar')] GuardContract $bar)
    {
    }
}

final class LogTest
{
    public function __construct(#[Log('foo')] LoggerInterface $foo, #[Log('bar')] LoggerInterface $bar)
    {
    }
}

final class RouteParameterTest
{
    public function __construct(#[RouteParameter('foo')] Model $foo, #[RouteParameter('bar')] string $bar)
    {
    }
}

final class StorageTest
{
    public function __construct(#[Storage('foo')] Filesystem $foo, #[Storage('bar')] Filesystem $bar)
    {
    }
}
