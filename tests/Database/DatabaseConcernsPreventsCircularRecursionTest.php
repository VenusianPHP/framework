<?php

namespace Tests\Database;

use Voyager\Database\Instrument\Concerns\PreventsCircularRecursion;
use Mockery as m;

beforeEach(function () {
    PreventsCircularRecursionWithRecursiveMethod::$globalStack = 0;
});

test('recursive calls are prevented without preventing subsequent calls', function () {
    $instance = new PreventsCircularRecursionWithRecursiveMethod();

    expect(PreventsCircularRecursionWithRecursiveMethod::$globalStack)->toEqual(0)
        ->and($instance->instanceStack)->toEqual(0);

    expect($instance->callStack())->toEqual(0);
    expect(PreventsCircularRecursionWithRecursiveMethod::$globalStack)->toEqual(1)
        ->and($instance->instanceStack)->toEqual(1);

    expect($instance->callStack())->toEqual(1);
    expect(PreventsCircularRecursionWithRecursiveMethod::$globalStack)->toEqual(2)
        ->and($instance->instanceStack)->toEqual(2);
});

test('recursive default callback is called only on recursion', function () {
    $instance = new PreventsCircularRecursionWithRecursiveMethod();

    expect(PreventsCircularRecursionWithRecursiveMethod::$globalStack)->toEqual(0)
        ->and($instance->instanceStack)->toEqual(0)
        ->and($instance->defaultStack)->toEqual(0);

    expect($instance->callCallableDefaultStack())->toEqual(['instance' => 1, 'default' => 0]);
    expect(PreventsCircularRecursionWithRecursiveMethod::$globalStack)->toEqual(1)
        ->and($instance->instanceStack)->toEqual(1)
        ->and($instance->defaultStack)->toEqual(1);

    expect($instance->callCallableDefaultStack())->toEqual(['instance' => 2, 'default' => 1]);
    expect(PreventsCircularRecursionWithRecursiveMethod::$globalStack)->toEqual(2)
        ->and($instance->instanceStack)->toEqual(2)
        ->and($instance->defaultStack)->toEqual(2);
});

test('recursive default callback is called only once per call stack', function () {
    $instance = new PreventsCircularRecursionWithRecursiveMethod();

    expect(PreventsCircularRecursionWithRecursiveMethod::$globalStack)->toEqual(0)
        ->and($instance->instanceStack)->toEqual(0)
        ->and($instance->defaultStack)->toEqual(0);

    expect($instance->callCallableDefaultStackRepeatedly())->toEqual(
        [
            ['instance' => 1, 'default' => 0],
            ['instance' => 1, 'default' => 0],
            ['instance' => 1, 'default' => 0],
        ],
    );
    expect(PreventsCircularRecursionWithRecursiveMethod::$globalStack)->toEqual(1)
        ->and($instance->instanceStack)->toEqual(1)
        ->and($instance->defaultStack)->toEqual(1);

    expect($instance->callCallableDefaultStackRepeatedly())->toEqual(
        [
            ['instance' => 2, 'default' => 1],
            ['instance' => 2, 'default' => 1],
            ['instance' => 2, 'default' => 1],
        ],
    );
    expect(PreventsCircularRecursionWithRecursiveMethod::$globalStack)->toEqual(2)
        ->and($instance->instanceStack)->toEqual(2)
        ->and($instance->defaultStack)->toEqual(2);
});

test('recursive calls are limited to individual instances', function () {
    $instance = new PreventsCircularRecursionWithRecursiveMethod();
    $other = $instance->other;

    expect(PreventsCircularRecursionWithRecursiveMethod::$globalStack)->toEqual(0)
        ->and($instance->instanceStack)->toEqual(0)
        ->and($other->instanceStack)->toEqual(0);

    $instance->callStack();
    expect(PreventsCircularRecursionWithRecursiveMethod::$globalStack)->toEqual(1)
        ->and($instance->instanceStack)->toEqual(1)
        ->and($other->instanceStack)->toEqual(0);

    $instance->callStack();
    expect(PreventsCircularRecursionWithRecursiveMethod::$globalStack)->toEqual(2)
        ->and($instance->instanceStack)->toEqual(2)
        ->and($other->instanceStack)->toEqual(0);

    $other->callStack();
    expect(PreventsCircularRecursionWithRecursiveMethod::$globalStack)->toEqual(3)
        ->and($instance->instanceStack)->toEqual(2)
        ->and($other->instanceStack)->toEqual(1);

    $other->callStack();
    expect(PreventsCircularRecursionWithRecursiveMethod::$globalStack)->toEqual(4)
        ->and($instance->instanceStack)->toEqual(2)
        ->and($other->instanceStack)->toEqual(2);
});

test('recursive calls to circular reference calls other instance once', function () {
    $instance = new PreventsCircularRecursionWithRecursiveMethod();
    $other = $instance->other;

    expect(PreventsCircularRecursionWithRecursiveMethod::$globalStack)->toEqual(0)
        ->and($instance->instanceStack)->toEqual(0)
        ->and($other->instanceStack)->toEqual(0);

    $instance->callOtherStack();
    expect(PreventsCircularRecursionWithRecursiveMethod::$globalStack)->toEqual(2)
        ->and($instance->instanceStack)->toEqual(1)
        ->and($other->instanceStack)->toEqual(1);

    $instance->callOtherStack();
    expect(PreventsCircularRecursionWithRecursiveMethod::$globalStack)->toEqual(4)
        ->and($instance->instanceStack)->toEqual(2)
        ->and($other->instanceStack)->toEqual(2);

    $other->callOtherStack();
    expect(PreventsCircularRecursionWithRecursiveMethod::$globalStack)->toEqual(6)
        ->and($other->instanceStack)->toEqual(3)
        ->and($instance->instanceStack)->toEqual(3);

    $other->callOtherStack();
    expect(PreventsCircularRecursionWithRecursiveMethod::$globalStack)->toEqual(8)
        ->and($other->instanceStack)->toEqual(4)
        ->and($instance->instanceStack)->toEqual(4);
});

test('recursive calls to circular linked list calls each instance once', function () {
    $instance = new PreventsCircularRecursionWithRecursiveMethod();
    $second = $instance->other;
    $third = new PreventsCircularRecursionWithRecursiveMethod($second);
    $instance->other = $third;

    expect(PreventsCircularRecursionWithRecursiveMethod::$globalStack)->toEqual(0)
        ->and($instance->instanceStack)->toEqual(0)
        ->and($second->instanceStack)->toEqual(0)
        ->and($third->instanceStack)->toEqual(0);

    $instance->callOtherStack();
    expect(PreventsCircularRecursionWithRecursiveMethod::$globalStack)->toEqual(3)
        ->and($instance->instanceStack)->toEqual(1)
        ->and($second->instanceStack)->toEqual(1)
        ->and($third->instanceStack)->toEqual(1);

    $second->callOtherStack();
    expect(PreventsCircularRecursionWithRecursiveMethod::$globalStack)->toEqual(6)
        ->and($instance->instanceStack)->toEqual(2)
        ->and($second->instanceStack)->toEqual(2)
        ->and($third->instanceStack)->toEqual(2);

    $third->callOtherStack();
    expect(PreventsCircularRecursionWithRecursiveMethod::$globalStack)->toEqual(9)
        ->and($instance->instanceStack)->toEqual(3)
        ->and($second->instanceStack)->toEqual(3)
        ->and($third->instanceStack)->toEqual(3);
});

test('mocked model call to without recursion method works', function () {
    $mock = m::mock(TestModel::class)->makePartial();

    // Model toArray method implementation
    $toArray = $mock->withoutRecursion(
        fn () => array_merge($mock->attributesToArray(), $mock->relationsToArray()),
        fn () => $mock->attributesToArray(),
    );
    expect($toArray)->toEqual([]);
});

class PreventsCircularRecursionWithRecursiveMethod
{
    use PreventsCircularRecursion;

    public function __construct(
        public ?PreventsCircularRecursionWithRecursiveMethod $other = null,
    ) {
        $this->other ??= new PreventsCircularRecursionWithRecursiveMethod($this);
    }

    public static int $globalStack = 0;
    public int $instanceStack = 0;
    public int $defaultStack = 0;

    public function callStack(): int
    {
        return $this->withoutRecursion(
            function () {
                static::$globalStack++;
                $this->instanceStack++;

                return $this->callStack();
            },
            $this->instanceStack,
        );
    }

    public function callCallableDefaultStack(): array
    {
        return $this->withoutRecursion(
            function () {
                static::$globalStack++;
                $this->instanceStack++;

                return $this->callCallableDefaultStack();
            },
            fn () => [
                'instance' => $this->instanceStack,
                'default' => $this->defaultStack++,
            ],
        );
    }

    public function callCallableDefaultStackRepeatedly(): array
    {
        return $this->withoutRecursion(
            function () {
                static::$globalStack++;
                $this->instanceStack++;

                return [
                    $this->callCallableDefaultStackRepeatedly(),
                    $this->callCallableDefaultStackRepeatedly(),
                    $this->callCallableDefaultStackRepeatedly(),
                ];
            },
            fn () => [
                'instance' => $this->instanceStack,
                'default' => $this->defaultStack++,
            ],
        );
    }

    public function callOtherStack(): int
    {
        return $this->withoutRecursion(
            function () {
                $this->other->callStack();

                return $this->other->callOtherStack();
            },
            $this->instanceStack,
        );
    }
}
