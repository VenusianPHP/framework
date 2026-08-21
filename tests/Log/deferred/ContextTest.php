<?php

use Monolog\LogRecord;
use Orchestra\Testbench\TestCase;
use Tests\Log\Fixtures\MyAddContextProcessor;
use Tests\Log\Fixtures\StringBackedSuit;
use Tests\Log\Fixtures\Suit;
use Voyager\Contracts\Debug\ExceptionHandler;
use Voyager\Contracts\Log\ContextLogProcessor;
use Voyager\Log\Context\Events\ContextDehydrating as Dehydrating;
use Voyager\Log\Context\Events\ContextHydrated as Hydrated;
use Voyager\Log\Context\Repository;
use Voyager\MagicAliases\Context;
use Voyager\NutsAndBolts\DataObjects\Str;
use Voyager\NutsAndBolts\MagicAliases\Event;
use Voyager\NutsAndBolts\MagicAliases\Log;
use Voyager\System\Testing\LazilyRefreshDatabase;

uses(TestCase::class, LazilyRefreshDatabase::class);

/** Truncate the single-channel log file and pin the uuid sequence. */
function resetContextLog(): string
{
    $path = storage_path('logs/venusian.log');
    file_put_contents($path, '');
    Str::createUuidsUsingSequence(['expected-trace-id']);

    return $path;
}

/** The log line with the leading timestamp stripped off. */
function contextLogLine(string $path): string
{
    return Str::after(file_get_contents($path), '] ');
}

afterEach(function () {
    MyAddContextProcessor::$wasConstructed = false;
});

test('it can set values', function () {
    $values = [
        'string' => 'string',
        'bool' => false,
        'int' => 5,
        'float' => 5.5,
        'null' => null,
        'array' => [1, 2, 3],
        'hash' => ['foo' => 'bar'],
        'object' => (object) ['foo' => 'bar'],
        'enum' => Suit::Clubs,
        'backed_enum' => StringBackedSuit::Clubs,
    ];

    foreach ($values as $type => $value) {
        Context::add($type, $value);
    }

    foreach ($values as $type => $value) {
        expect(Context::get($type))->toBe($value);
    }
});

test('it can add values when not already present', function () {
    Context::addIf('foo', 1);
    expect(Context::get('foo'))->toBe(1);

    Context::addIf('foo', 2);
    expect(Context::get('foo'))->toBe(1);
});

describe('hydration', function () {
    test('it can listen to the hydrating event', function () {
        Context::add('one', 1);
        Context::add('two', 2);
        Context::hydrated(function (Repository $context) {
            Context::add('two', 99);
            Context::add('three', 3);
        });
        Event::dispatch(new Hydrated(Context::getFacadeRoot()));

        expect(Context::get('one'))->toBe(1)
            ->and(Context::get('two'))->toBe(99)
            ->and(Context::get('three'))->toBe(3);
    });

    test('it can listen to the dehydrated event', function () {
        Context::add('one', 1);
        Context::add('two', 2);
        Context::dehydrating(function (Repository $context) {
            Context::add('two', 99);
            Context::add('three', 3);
        });
        Event::dispatch(new Dehydrating(Context::getFacadeRoot()));

        expect(Context::get('one'))->toBe(1)
            ->and(Context::get('two'))->toBe(99)
            ->and(Context::get('three'))->toBe(3);
    });

    test('it can modify context while dehydrating without impacting the global instance', function () {
        Context::add('one', 1);
        Context::dehydrating(function (Repository $context) {
            $context->add('one', 99);
        });

        $dehydrated = Context::dehydrate();
        expect(Context::get('one'))->toBe(1);

        Context::hydrate($dehydrated);
        expect(Context::get('one'))->toBe(99);
    });

    test('dehydrate returns null when empty', function () {
        expect(Context::dehydrate())->toBeNull();
    });

    test('hydrating null triggers the hydrating event', function () {
        $called = false;
        Context::hydrated(function () use (&$called) {
            $called = true;
        });

        Context::hydrate(null);

        expect($called)->toBeTrue();
    });

    test('it can serialize values', function () {
        Context::add([
            'string' => 'string',
            'bool' => false,
            'int' => 5,
            'float' => 5.5,
            'null' => null,
            'array' => [1, 2, 3],
            'hash' => ['foo' => 'bar'],
            'object' => (object) ['foo' => 'bar'],
            'enum' => Suit::Clubs,
            'backed_enum' => StringBackedSuit::Clubs,
        ]);
        Context::addHidden('number', 55);

        $dehydrated = Context::dehydrate();

        expect($dehydrated)->toBe([
            'data' => [
                'string' => 's:6:"string";',
                'bool' => 'b:0;',
                'int' => 'i:5;',
                'float' => 'd:5.5;',
                'null' => 'N;',
                'array' => 'a:3:{i:0;i:1;i:1;i:2;i:2;i:3;}',
                'hash' => 'a:1:{s:3:"foo";s:3:"bar";}',
                'object' => 'O:8:"stdClass":1:{s:3:"foo";s:3:"bar";}',
                'enum' => 'E:31:"Tests\Log\Fixtures\Suit:Clubs";',
                'backed_enum' => 'E:43:"Tests\Log\Fixtures\StringBackedSuit:Clubs";',
            ],
            'hidden' => [
                'number' => 'i:55;',
            ],
        ]);

        Context::flush();
        expect(Context::get('string'))->toBeNull();

        Context::hydrate($dehydrated);

        expect(Context::get('string'))->toBe('string')
            ->and(Context::get('bool'))->toBe(false)
            ->and(Context::get('int'))->toBe(5)
            ->and(Context::get('float'))->toBe(5.5)
            ->and(Context::get('null'))->toBe(null)
            ->and(Context::get('array'))->toBe([1, 2, 3])
            ->and(Context::get('hash'))->toBe(['foo' => 'bar'])
            ->and(Context::get('object'))->toEqual((object) ['foo' => 'bar'])
            ->and(Context::get('enum'))->toBe(Suit::Clubs)
            ->and(Context::get('backed_enum'))->toBe(StringBackedSuit::Clubs)
            ->and(Context::getHidden('number'))->toBe(55);
    });
});

describe('stacks', function () {
    test('it can push to a list', function () {
        Context::push('breadcrumbs', 'foo');
        Context::push('breadcrumbs', 'bar');
        Context::push('breadcrumbs', 'baz', 'qux');

        expect(Context::get('breadcrumbs'))->toBe(['foo', 'bar', 'baz', 'qux']);
    });

    test('it throws when pushing onto a non array', function () {
        Context::add('breadcrumbs', 'foo');

        Context::push('breadcrumbs', 'bar');
    })->throws(RuntimeException::class, 'Unable to push value onto context stack for key [breadcrumbs].');

    test('it throws when pushing onto a non list array', function () {
        Context::add('breadcrumbs', ['foo' => 'bar']);

        Context::push('breadcrumbs', 'bar');
    })->throws(RuntimeException::class, 'Unable to push value onto context stack for key [breadcrumbs].');

    test('it can pop from a list', function () {
        Context::push('breadcrumbs', 'foo', 'bar');

        expect(Context::pop('breadcrumbs'))->toBe('bar')
            ->and(Context::pop('breadcrumbs'))->toBe('foo')
            ->and(Context::get('breadcrumbs'))->toBe([]);
    });

    test('it throws when popping from an empty list', function () {
        Context::push('breadcrumbs', 'bar');
        Context::pop('breadcrumbs');

        Context::pop('breadcrumbs');
    })->throws(RuntimeException::class, 'Unable to pop value from context stack for key [breadcrumbs].');

    test('it throws when popping from a non list array', function () {
        Context::add('breadcrumbs', ['foo' => 'bar']);

        Context::pop('breadcrumbs');
    })->throws(RuntimeException::class, 'Unable to pop value from context stack for key [breadcrumbs].');

    test('it can pop from a hidden list', function () {
        Context::pushHidden('breadcrumbs', 'foo', 'bar');

        expect(Context::popHidden('breadcrumbs'))->toBe('bar')
            ->and(Context::popHidden('breadcrumbs'))->toBe('foo')
            ->and(Context::getHidden('breadcrumbs'))->toBe([]);
    });

    test('it throws when popping from an empty hidden list', function () {
        Context::pushHidden('breadcrumbs', 'bar');
        Context::popHidden('breadcrumbs');

        Context::popHidden('breadcrumbs');
    })->throws(RuntimeException::class, 'Unable to pop value from hidden context stack for key [breadcrumbs].');

    test('it throws when popping from a hidden non list array', function () {
        Context::addHidden('breadcrumbs', ['foo' => 'bar']);

        Context::popHidden('breadcrumbs');
    })->throws(RuntimeException::class, 'Unable to pop value from hidden context stack for key [breadcrumbs].');

    test('it can check whether a value is in a stack', function () {
        Context::push('foo', 'bar', 'lorem');

        expect(Context::stackContains('foo', 'bar'))->toBeTrue()
            ->and(Context::stackContains('foo', 'lorem'))->toBeTrue()
            ->and(Context::stackContains('foo', 'doesNotExist'))->toBeFalse();
    });

    test('it can check a stack with a closure', function () {
        Context::push('foo', 'bar', ['lorem'], 123);
        Context::pushHidden('baz');

        expect(Context::stackContains('foo', fn ($value) => $value === 'bar'))->toBeTrue()
            ->and(Context::stackContains('foo', fn ($value) => $value === 'baz'))->toBeFalse();
    });

    test('it can check whether a value is in a hidden stack', function () {
        Context::pushHidden('foo', 'bar', 'lorem');

        expect(Context::hiddenStackContains('foo', 'bar'))->toBeTrue()
            ->and(Context::hiddenStackContains('foo', 'lorem'))->toBeTrue()
            ->and(Context::hiddenStackContains('foo', 'doesNotExist'))->toBeFalse();
    });

    test('it can check a hidden stack with a closure', function () {
        Context::pushHidden('foo', 'baz');
        Context::push('foo', 'bar', ['lorem'], 123);

        expect(Context::hiddenStackContains('foo', fn ($value) => $value === 'baz'))->toBeTrue()
            ->and(Context::hiddenStackContains('foo', fn ($value) => $value === 'bar'))->toBeFalse();
    });

    test('a hidden value is not visible in the plain stack', function () {
        Context::pushHidden('foo', 'bar', 'lorem');

        expect(Context::stackContains('foo', 'bar'))->toBeFalse();
    });
});

describe('reading', function () {
    test('it can check if context has been set', function () {
        Context::add('foo', 'bar');
        Context::add('null', null);

        expect(Context::has('foo'))->toBeTrue()
            ->and(Context::has('null'))->toBeTrue()
            ->and(Context::has('unset'))->toBeFalse();
    });

    test('it can check if context is missing', function () {
        Context::add('foo', 'bar');

        expect(Context::missing('lorem'))->toBeTrue()
            ->and(Context::missing('foo'))->toBeFalse();
    });

    test('it can get all values', function () {
        Context::add('foo', 'bar');
        Context::add('null', null);

        expect(Context::all())->toBe([
            'foo' => 'bar',
            'null' => null,
        ]);
    });

    test('it silently ignores unset values', function () {
        expect(Context::get('foo'))->toBeNull()
            ->and(Context::has('foo'))->toBeFalse()
            ->and(Context::all())->toBe([]);
    });

    test('it is a simple key value system', function () {
        Context::add('parent.child', 5);

        expect(Context::get('parent'))->toBeNull()
            ->and(Context::get('parent.child'))->toBe(5);
    });

    test('it can retrieve a subset of context', function () {
        Context::add('parent.child.1', 5);
        Context::add('parent.child.2', 6);
        Context::add('another', 7);

        expect(Context::only([
            'parent.child.1',
            'parent.child.2',
        ]))->toBe([
            'parent.child.1' => 5,
            'parent.child.2' => 6,
        ]);
    });

    test('it can exclude a subset of context', function () {
        Context::add('parent.child.1', 5);
        Context::add('parent.child.2', 6);
        Context::add('another', 7);

        expect(Context::except([
            'parent.child.1',
            'parent.child.2',
        ]))->toBe([
            'another' => 7,
        ]);
    });

    test('it can exclude a subset of hidden context', function () {
        Context::addHidden('parent.child.1', 5);
        Context::addHidden('parent.child.2', 6);
        Context::addHidden('another', 7);

        expect(Context::exceptHidden([
            'parent.child.1',
            'parent.child.2',
        ]))->toBe([
            'another' => 7,
        ]);
    });

    test('it can pull a value out', function () {
        Context::add('foo', 'data');

        expect(Context::pull('foo'))->toBe('data')
            ->and(Context::get('foo'))->toBeNull();

        Context::addHidden('foo', 'data');

        expect(Context::pullHidden('foo'))->toBe('data')
            ->and(Context::getHidden('foo'))->toBeNull();
    });
});

describe('logging', function () {
    test('it adds context to logging', function () {
        $path = resetContextLog();

        Context::add('trace_id', Str::uuid());
        Context::add('foo.bar', 123);
        Context::push('bar.baz', 456);
        Context::push('bar.baz', 789);

        Log::channel('single')->info('My name is {name}', [
            'name' => 'Tim',
            'framework' => 'Venusian',
        ]);
        $log = contextLogLine(storage_path('logs/venusian.log'));

        expect(trim($log))->toBe('testing.INFO: My name is Tim {"name":"Tim","framework":"Venusian"} {"trace_id":"expected-trace-id","foo.bar":123,"bar.baz":[456,789]}');

        file_put_contents($path, '');
        Str::createUuidsNormally();
    });

    test('it does not override log instance context', function () {
        $path = resetContextLog();

        Context::add('name', 'James');

        Log::channel('single')->info('My name is {name}', [
            'name' => 'Tim',
        ]);
        $log = contextLogLine($path);

        expect(trim($log))->toBe('testing.INFO: My name is Tim {"name":"Tim"} {"name":"James"}');

        file_put_contents($path, '');
        Str::createUuidsNormally();
    });

    test('it does not allow context to be used as message parameters', function () {
        $path = resetContextLog();

        Context::add('name', 'James');

        Log::channel('single')->info('My name is {name}');
        $log = contextLogLine($path);

        expect(trim($log))->toBe('testing.INFO: My name is {name}  {"name":"James"}');

        file_put_contents($path, '');
        Str::createUuidsNormally();
    });

    test('it does not add hidden context to logging', function () {
        $path = resetContextLog();

        Context::addHidden('hidden_data', 'hidden_data');

        Log::channel('single')->info('My name is {name}', [
            'name' => 'Tim',
            'framework' => 'Venusian',
        ]);
        $log = contextLogLine($path);

        expect(trim($log))->not->toContain('hidden_data');

        file_put_contents($path, '');
        Str::createUuidsNormally();
    });

    test('it adds context to logged exceptions', function () {
        $path = resetContextLog();

        Context::add('trace_id', Str::uuid());
        Context::add('foo.bar', 123);
        Context::push('bar.baz', 456);
        Context::push('bar.baz', 789);

        $this->app[ExceptionHandler::class]->report(new Exception('Whoops!'));
        $log = contextLogLine($path);

        expect(trim($log))->toEndWith(' {"trace_id":"expected-trace-id","foo.bar":123,"bar.baz":[456,789]}');

        file_put_contents($path, '');
        Str::createUuidsNormally();
    });

    test('a closure can be bound as the context log processor', function () {
        $path = storage_path('logs/venusian.log');
        file_put_contents($path, '');

        $this->app->bind(
            ContextLogProcessor::class,
            fn () => function (LogRecord $record): LogRecord {
                $logChannel = Context::getHidden('log_channel_name');

                return $record->with(
                    // allow overriding the context from what's been set on the log
                    context: array_merge(Context::all(), $record->context),
                    // use the log channel we've set in context, or fallback to the current channel
                    channel: $logChannel ?? $record->channel,
                );
            }
        );

        Context::addHidden('log_channel_name', 'closure-test');
        Context::add(['value_from_context' => 'hello']);

        Log::info('This is an info log.', ['value_from_log_info_context' => 'foo']);

        $log = contextLogLine($path);
        expect(Str::trim($log))->toBe('closure-test.INFO: This is an info log. {"value_from_context":"hello","value_from_log_info_context":"foo"}');

        file_put_contents($path, '');
    });

    test('the context log processor can be rebound to a separate class', function () {
        $path = storage_path('logs/venusian.log');
        file_put_contents($path, '');

        $this->app->bind(ContextLogProcessor::class, MyAddContextProcessor::class);

        Context::add(['this-will-be-included' => false]);

        Log::info('This is an info log.', ['value_from_log_info_context' => 'foo']);
        $log = contextLogLine($path);

        expect(Str::trim($log))->toBe(
            'testing.INFO: This is an info log. {"value_from_log_info_context":"foo","inside of MyAddContextProcessor":true}'
        )->and(MyAddContextProcessor::$wasConstructed)->toBeTrue();

        file_put_contents($path, '');
    });
});

describe('hidden context', function () {
    test('it can add hidden values and refuses to push onto a scalar', function () {
        Context::addHidden('foo', 'data');

        expect(Context::has('foo'))->toBeFalse()
            ->and(Context::hasHidden('foo'))->toBeTrue()
            ->and(Context::get('foo'))->toBeNull()
            ->and(Context::getHidden('foo'))->toBe('data')
            ->and(Context::onlyHidden(['foo']))->toBe(['foo' => 'data']);

        Context::forgetHidden('foo');

        expect(Context::has('foo'))->toBeFalse()
            ->and(Context::hasHidden('foo'))->toBeFalse()
            ->and(Context::get('foo'))->toBeNull()
            ->and(Context::getHidden('foo'))->toBeNull();

        Context::pushHidden('foo', 1);
        Context::pushHidden('foo', 2);
        expect(Context::getHidden('foo'))->toBe([1, 2]);

        Context::addHidden('foo', 'bar');

        Context::pushHidden('foo', 2);
    })->throws(RuntimeException::class, 'Unable to push value onto hidden context stack for key [foo].');
});

test('scope sets keys and restores them afterwards', function () {
    $contextInClosure = [];
    $callback = function () use (&$contextInClosure) {
        $contextInClosure = ['data' => Context::all(), 'hidden' => Context::allHidden()];

        throw new Exception('test_with_sets_keys_and_restores');
    };

    Context::add('key1', 'value1');
    Context::add('key2', 123);
    Context::addHidden([
        'hiddenKey1' => 'hello',
        'hiddenKey2' => 'world',
    ]);

    try {
        Context::scope(
            $callback,
            ['key1' => 'with', 'key3' => 'also-with'],
            ['hiddenKey3' => 'foobar'],
        );

        $this->fail('No exception was thrown.');
    } catch (Exception) {
    }

    $this->assertEqualsCanonicalizing([
        'data' => [
            'key1' => 'with',
            'key2' => 123,
            'key3' => 'also-with',
        ],
        'hidden' => [
            'hiddenKey1' => 'hello',
            'hiddenKey2' => 'world',
            'hiddenKey3' => 'foobar',
        ],
    ], $contextInClosure);

    $this->assertEqualsCanonicalizing([
        'key1' => 'value1',
        'key2' => 123,
    ], Context::all());
    $this->assertEqualsCanonicalizing([
        'hiddenKey1' => 'hello',
        'hiddenKey2' => 'world',
    ], Context::allHidden());
});

describe('counters', function () {
    test('it increments a counter', function () {
        Context::increment('foo');
        expect(Context::get('foo'))->toBe(1);

        Context::increment('foo');
        expect(Context::get('foo'))->toBe(2);
    });

    test('it increments a counter by a custom amount', function () {
        Context::increment('foo', 2);
        expect(Context::get('foo'))->toBe(2);

        Context::increment('foo', 3);
        expect(Context::get('foo'))->toBe(5);
    });

    test('it decrements a counter', function () {
        Context::increment('foo');
        Context::decrement('foo');

        expect(Context::get('foo'))->toBe(0);
    });

    test('it decrements a counter by a custom amount', function () {
        Context::increment('foo', 2);
        Context::decrement('foo', 2);

        expect(Context::get('foo'))->toBe(0);
    });
});

describe('remember', function () {
    test('it remembers a value', function () {
        expect(Context::remember('int', 1))->toBe(1);

        $closureRunCount = 0;
        $closure = function () use (&$closureRunCount) {
            $closureRunCount++;

            return 'bar';
        };

        expect(Context::remember('foo', $closure))->toBe('bar')
            ->and(Context::get('foo'))->toBe('bar');

        Context::remember('foo', $closure);
        expect($closureRunCount)->toBe(1);
    });

    test('it remembers a hidden value', function () {
        expect(Context::rememberHidden('int', 1))->toBe(1);

        $closureRunCount = 0;
        $closure = function () use (&$closureRunCount) {
            $closureRunCount++;

            return 'bar';
        };

        expect(Context::rememberHidden('foo', $closure))->toBe('bar')
            ->and(Context::getHidden('foo'))->toBe('bar');

        Context::rememberHidden('foo', $closure);
        expect($closureRunCount)->toBe(1);
    });
});
