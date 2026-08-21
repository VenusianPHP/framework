<?php

use Tests\NutsAndBolts\Fixtures\MyClass;
use Tests\NutsAndBolts\Fixtures\MyExtendedClass;
use Voyager\NutsAndBolts\Once;

use function Tests\NutsAndBolts\Fixtures\my_rand;

$letter = 'a';

$GLOBALS['onceable1'] = fn () => once(fn () => $letter.rand(1, PHP_INT_MAX));
$GLOBALS['onceable2'] = fn () => once(fn () => $letter.rand(1, PHP_INT_MAX));

afterEach(function () {
    Once::flush();
    Once::enable();
});

test('the result is memoized', function () {
    $instance = new class
    {
        public function rand()
        {
            return once(fn () => rand(1, PHP_INT_MAX));
        }
    };

    expect($instance->rand())->toBe($instance->rand());
});

test('the callable only runs once', function () {
    $instance = new class
    {
        public int $count = 0;

        public function increment()
        {
            return once(fn () => ++$this->count);
        }
    };

    expect($instance->increment())->toBe(1)
        ->and($instance->increment())->toBe(1)
        ->and($instance->count)->toBe(1);
});

test('flush clears the memo, and a disabled Once never memoizes', function () {
    $instance = new MyClass;

    $first = $instance->rand();

    Once::flush();

    expect($instance->rand())->not->toBe($first);

    Once::disable();
    Once::flush();

    expect($instance->rand())->not->toBe($instance->rand());
});

test('the memo is dropped when the object is garbage collected', function () {
    $instance = new MyClass;

    $first = $instance->rand();
    unset($instance);
    gc_collect_cycles();

    expect((new MyClass)->rand())->not->toBe($first);
});

test('a change in the bound variables breaks memoization', function () {
    $instance = new class
    {
        public function rand(string $letter)
        {
            return once(function () use ($letter) {
                return $letter.rand(1, 10000000);
            });
        }
    };

    expect($instance->rand('a'))->not->toBe($instance->rand('b'))
        ->and($instance->rand('a'))->toBe($instance->rand('a'));

    $results = [];
    $letter = 'a';

    a:
    $results[] = once(fn () => $letter.rand(1, 10000000));

    if (count($results) < 2) {
        goto a;
    }

    expect($results[0])->toBe($results[1]);
});

test('$this may be used inside the callable', function () {
    $instance = new MyClass;

    expect($instance->callRand())->toBe($instance->callRand());
});

test('an invokable object is only invoked once', function () {
    $invokable = new class
    {
        public static $count = 0;

        public function __invoke()
        {
            return static::$count = static::$count + 1;
        }
    };

    $instance = new class($invokable)
    {
        public function __construct(protected $invokable) {}

        public function call()
        {
            return once($this->invokable);
        }
    };

    $first = $instance->call();

    expect($instance->call())->toBe($first)
        ->and($instance->call())->toBe($first)
        ->and($invokable::$count)->toBe(1);
});

test('first class callable syntax is memoized', function () {
    $instance = new class
    {
        public function rand()
        {
            return once(MyClass::staticRand(...));
        }
    };

    expect($instance->rand())->toBe($instance->rand());
});

test('array callable syntax is memoized', function () {
    $instance = new class
    {
        public function rand()
        {
            return once([MyClass::class, 'staticRand']);
        }
    };

    expect($instance->rand())->toBe($instance->rand());
});

test('a static method is memoized', function () {
    expect(MyClass::staticRand())->toBe(MyClass::staticRand());
});

test('once inside a closure is memoized per closure', function () {
    $resolver = fn () => once(fn () => rand(1, PHP_INT_MAX));

    expect($resolver())->toBe($resolver());
});

test('once inside a global function is memoized', function () {
    expect(my_rand())->toBe(my_rand());
});

test('disable turns memoization off', function () {
    Once::disable();

    expect(my_rand())->not->toBe(my_rand());
});

test('memoization can be turned off and back on', function () {
    $first = my_rand();
    $second = my_rand();

    Once::disable();

    $third = my_rand();

    Once::enable();

    $fourth = my_rand();

    expect($second)->toBe($first)
        ->and($third)->not->toBe($first)
        ->and($fourth)->toBe($first);
});

test('code inside eval is never memoized', function () {
    $firstResolver = eval('return fn () => once( function () { return random_int(1, PHP_INT_MAX); } ) ;');

    $firstA = $firstResolver();
    $firstB = $firstResolver();

    $secondResolver = eval('return fn () => fn () => once( function () { return random_int(1, PHP_INT_MAX); } ) ;');

    $secondA = $secondResolver()();
    $secondB = $secondResolver()();

    $third = eval('return once( function () { return random_int(1, PHP_INT_MAX); } ) ;');
    $fourth = eval('return once( function () { return random_int(1, PHP_INT_MAX); } ) ;');

    expect($firstA)->not->toBe($firstB)
        ->and($secondA)->not->toBe($secondB)
        ->and($third)->not->toBe($fourth);
});

test('two once calls on the same line are not told apart', function () {
    $result = [once(fn () => rand(1, PHP_INT_MAX)), once(fn () => rand(1, PHP_INT_MAX))];

    expect($result[0])->not->toBe($result[1]);
})->skip('This test shows a limitation of the current implementation.');

test('two closures on different lines memoize separately', function () {
    $resolver = fn () => once(fn () => rand(1, PHP_INT_MAX));
    $resolver2 = fn () => once(fn () => rand(1, PHP_INT_MAX));

    expect($resolver())->not->toBe($resolver2());
});

test('methods with the same name on different classes memoize separately', function () {
    $instanceA = new class
    {
        public function rand()
        {
            return once(fn () => rand(1, PHP_INT_MAX));
        }
    };

    $instanceB = new class
    {
        public function rand()
        {
            return once(fn () => rand(1, PHP_INT_MAX));
        }
    };

    expect($instanceA->rand())->not->toBe($instanceB->rand());
});

test('nested once calls are memoized', function () {
    $instance = new class
    {
        public function rand()
        {
            return once(fn () => once(fn () => rand(1, PHP_INT_MAX)));
        }
    };

    expect($instance->rand())->toBe($instance->rand());
});

test('global closures memoize separately from one another', function () {
    $first = $GLOBALS['onceable1']();
    $second = $GLOBALS['onceable1']();

    expect($second)->toBe($first);

    $third = $GLOBALS['onceable2']();
    $fourth = $GLOBALS['onceable2']();

    expect($fourth)->toBe($third)
        ->and($third)->not->toBe($first);
});

test('a null result is memoized', function () {
    $instance = new class
    {
        public $i = 0;

        public function null()
        {
            return once(function () {
                $this->i++;

                return null;
            });
        }
    };

    expect($instance->null())->toBe($instance->null())
        ->and($instance->i)->toBe(1);
});

test('a subclass memoizes separately from its parent', function () {
    expect(MyClass::staticRand())->not->toBe(MyExtendedClass::staticRand());
});
