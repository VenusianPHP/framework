<?php

use Tests\Pipeline\Fixtures\PipelineTestParameterPipe;
use Tests\Pipeline\Fixtures\PipelineTestPipeOne;
use Tests\Pipeline\Fixtures\PipelineTestPipeTwo;
use Voyager\Pipeline\Pipeline;
use Voyager\Vessel\Vessel;

test('it pipes through a class name and a closure', function () {
    $pipeTwo = function ($piped, $next) {
        $_SERVER['__test.pipe.two'] = $piped;

        return $next($piped);
    };

    $result = (new Pipeline(new Vessel))
        ->send('foo')
        ->through([PipelineTestPipeOne::class, $pipeTwo])
        ->then(fn ($piped) => $piped);

    expect($result)->toBe('foo')
        ->and($_SERVER['__test.pipe.one'])->toBe('foo')
        ->and($_SERVER['__test.pipe.two'])->toBe('foo');

    unset($_SERVER['__test.pipe.one'], $_SERVER['__test.pipe.two']);
});

test('it pipes through an already constructed object', function () {
    $result = (new Pipeline(new Vessel))
        ->send('foo')
        ->through([new PipelineTestPipeOne])
        ->then(fn ($piped) => $piped);

    expect($result)->toBe('foo')
        ->and($_SERVER['__test.pipe.one'])->toBe('foo');

    unset($_SERVER['__test.pipe.one']);
});

test('it pipes through an invokable object', function () {
    $result = (new Pipeline(new Vessel))
        ->send('foo')
        ->through([new PipelineTestPipeTwo])
        ->then(fn ($piped) => $piped);

    expect($result)->toBe('foo')
        ->and($_SERVER['__test.pipe.one'])->toBe('foo');

    unset($_SERVER['__test.pipe.one']);
});

test('it pipes through a callable, given as an array or on its own', function () {
    $function = function ($piped, $next) {
        $_SERVER['__test.pipe.one'] = 'foo';

        return $next($piped);
    };

    $result = (new Pipeline(new Vessel))
        ->send('foo')
        ->through([$function])
        ->then(fn ($piped) => $piped);

    expect($result)->toBe('foo')
        ->and($_SERVER['__test.pipe.one'])->toBe('foo');

    unset($_SERVER['__test.pipe.one']);

    $result = (new Pipeline(new Vessel))
        ->send('bar')
        ->through($function)
        ->thenReturn();

    expect($result)->toBe('bar')
        ->and($_SERVER['__test.pipe.one'])->toBe('foo');

    unset($_SERVER['__test.pipe.one']);
});

test('pipe appends to the pipes already set', function () {
    $object = new stdClass();
    $object->value = 0;

    $function = function ($object, $next) {
        $object->value++;

        return $next($object);
    };

    $result = (new Pipeline(new Vessel))
        ->send($object)
        ->through([$function])
        ->pipe([$function])
        ->then(fn ($piped) => $piped);

    expect($result)->toBe($object)
        ->and($object->value)->toEqual(2);
});

test('through overwrites previously set and appended pipes', function () {
    $object = new stdClass();
    $object->value = 0;

    $function = function ($object, $next) {
        $object->value++;

        return $next($object);
    };

    $result = (new Pipeline(new Vessel))
        ->send($object)
        ->through([$function])
        ->pipe([$function])
        ->through([$function])
        ->then(fn ($piped) => $piped);

    expect($result)->toBe($object)
        ->and($object->value)->toEqual(1);
});

test('it pipes through an invokable class name', function () {
    $result = (new Pipeline(new Vessel))
        ->send('foo')
        ->through([PipelineTestPipeTwo::class])
        ->then(fn ($piped) => $piped);

    expect($result)->toBe('foo')
        ->and($_SERVER['__test.pipe.one'])->toBe('foo');

    unset($_SERVER['__test.pipe.one']);
});

test('then is not called if a pipe returns without calling next', function () {
    $_SERVER['__test.pipe.then'] = '(*_*)';
    $_SERVER['__test.pipe.second'] = '(*_*)';

    $result = (new Pipeline(new Vessel))
        ->send('foo')
        ->through([
            fn ($value, $next) => 'm(-_-)m',
            fn ($value, $next) => $_SERVER['__test.pipe.second'] = 'm(-_-)m',
        ])
        ->then(function ($piped) {
            $_SERVER['__test.pipe.then'] = '(0_0)';

            return $piped;
        });

    expect($result)->toBe('m(-_-)m')
        // The then callback is not called.
        ->and($_SERVER['__test.pipe.then'])->toBe('(*_*)')
        // The second pipe is not called.
        ->and($_SERVER['__test.pipe.second'])->toBe('(*_*)');

    unset($_SERVER['__test.pipe.then']);
});

test('then receives whatever the last pipe passed to next', function () {
    $result = (new Pipeline(new Vessel))
        ->send('foo')
        ->through([function ($value, $next) {
            $value = $next('::not_foo::');

            $_SERVER['__test.pipe.return'] = $value;

            return 'pipe::'.$value;
        }])
        ->then(function ($piped) {
            $_SERVER['__test.then.arg'] = $piped;

            return 'then'.$piped;
        });

    expect($result)->toBe('pipe::then::not_foo::')
        ->and($_SERVER['__test.then.arg'])->toBe('::not_foo::');

    unset($_SERVER['__test.then.arg']);
    unset($_SERVER['__test.pipe.return']);
});

test('a pipe may be given colon separated parameters', function () {
    $parameters = ['one', 'two'];

    $result = (new Pipeline(new Vessel))
        ->send('foo')
        ->through(PipelineTestParameterPipe::class.':'.implode(',', $parameters))
        ->then(fn ($piped) => $piped);

    expect($result)->toBe('foo')
        ->and($_SERVER['__test.pipe.parameters'])->toEqual($parameters);

    unset($_SERVER['__test.pipe.parameters']);
});

test('via changes the method being called on the pipes', function () {
    $pipelineInstance = new Pipeline(new Vessel);

    $result = $pipelineInstance->send('data')
        ->through(PipelineTestPipeOne::class)
        ->via('differentMethod')
        ->then(fn ($piped) => $piped);

    expect($result)->toBe('data');
});

test('it throws when resolving a pipe without a container', function () {
    (new Pipeline)->send('data')
        ->through(PipelineTestPipeOne::class)
        ->then(fn ($piped) => $piped);
})->throws(RuntimeException::class, 'A container instance has not been passed to the Pipeline.');

test('it throws when using transactions without a container', function () {
    (new Pipeline)->send('data')
        ->through(PipelineTestPipeOne::class)
        ->withinTransaction()
        ->then(fn ($piped) => $piped);
})->throws(RuntimeException::class, 'A container instance has not been passed to the Pipeline.');

test('thenReturn runs the pipeline and returns the passable', function () {
    $result = (new Pipeline(new Vessel))
        ->send('foo')
        ->through([PipelineTestPipeOne::class])
        ->thenReturn();

    expect($result)->toBe('foo')
        ->and($_SERVER['__test.pipe.one'])->toBe('foo');

    unset($_SERVER['__test.pipe.one']);
});

test('the pipeline is conditionable', function () {
    $result = (new Pipeline(new Vessel))
        ->send('foo')
        ->when(true, function (Pipeline $pipeline) {
            $pipeline->pipe([PipelineTestPipeOne::class]);
        })
        ->then(fn ($piped) => $piped);

    expect($result)->toBe('foo')
        ->and($_SERVER['__test.pipe.one'])->toBe('foo');

    unset($_SERVER['__test.pipe.one']);

    $_SERVER['__test.pipe.one'] = null;

    $result = (new Pipeline(new Vessel))
        ->send('foo')
        ->when(false, function (Pipeline $pipeline) {
            $pipeline->pipe([PipelineTestPipeOne::class]);
        })
        ->then(fn ($piped) => $piped);

    expect($result)->toBe('foo')
        ->and($_SERVER['__test.pipe.one'])->toBeNull();

    unset($_SERVER['__test.pipe.one']);
});

describe('finally', function () {
    test('it runs after the pipeline completes', function () {
        $pipeTwo = function ($piped, $next) {
            $_SERVER['__test.pipe.two'] = $piped;

            $next($piped);
        };

        $result = (new Pipeline(new Vessel))
            ->send('foo')
            ->through([PipelineTestPipeOne::class, $pipeTwo])
            ->finally(function ($piped) {
                $_SERVER['__test.pipe.finally'] = $piped;
            })
            ->then(fn ($piped) => $piped);

        expect($result)->toBe(null)
            ->and($_SERVER['__test.pipe.one'])->toBe('foo')
            ->and($_SERVER['__test.pipe.two'])->toBe('foo')
            ->and($_SERVER['__test.pipe.finally'])->toBe('foo');

        unset($_SERVER['__test.pipe.one'], $_SERVER['__test.pipe.two'], $_SERVER['__test.pipe.finally']);
    });

    test('it runs when the chain is stopped', function () {
        $pipeTwo = function ($piped) {
            $_SERVER['__test.pipe.two'] = $piped;
        };

        $result = (new Pipeline(new Vessel))
            ->send('foo')
            ->through([PipelineTestPipeOne::class, $pipeTwo])
            ->finally(function ($piped) {
                $_SERVER['__test.pipe.finally'] = $piped;
            })
            ->then(fn ($piped) => $piped);

        expect($result)->toBe(null)
            ->and($_SERVER['__test.pipe.one'])->toBe('foo')
            ->and($_SERVER['__test.pipe.two'])->toBe('foo')
            ->and($_SERVER['__test.pipe.finally'])->toBe('foo');

        unset($_SERVER['__test.pipe.one'], $_SERVER['__test.pipe.two'], $_SERVER['__test.pipe.finally']);
    });

    test('it runs after then, not before it', function () {
        $std = new stdClass();

        $result = (new Pipeline(new Vessel))
            ->send($std)
            ->through([
                function ($std, $next) {
                    $std->value = 1;

                    return $next($std);
                },
                function ($std, $next) {
                    $std->value++;

                    return $next($std);
                },
            ])->finally(function ($std) {
                expect($std->value)->toBe(3);

                $std->value++;
            })->then(function ($std) {
                $std->value++;

                return $std;
            });

        expect($std->value)->toBe(4)
            ->and($result->value)->toBe(4);
    });

    test('it runs when an exception is thrown, and the exception still propagates', function () {
        $std = new stdClass();

        try {
            (new Pipeline(new Vessel))
                ->send($std)
                ->through([
                    function ($std, $next) {
                        $std->value = 1;

                        return $next($std);
                    },
                    function ($std) {
                        throw new Exception('My Exception: '.$std->value);
                    },
                ])->finally(function ($std) {
                    expect($std->value)->toBe(1);

                    $std->value++;
                })->then(function ($std) {
                    $std->value = 0;

                    return $std;
                });
        } catch (Exception $e) {
            expect($e->getMessage())->toBe('My Exception: 1')
                ->and($std->value)->toBe(2);

            throw $e;
        }
    })->throws(Exception::class, 'My Exception: 1');
});
