<?php

use Voyager\NutsAndBolts\Concerns\ReflectsClosures;
use Voyager\Reflection\Reflector;

test('isCallable accepts closures and rejects broken arrays', function () {
    expect(Reflector::isCallable(fn () => true))->toBeTrue()
        ->and(Reflector::isCallable('strlen'))->toBeTrue()
        ->and(Reflector::isCallable(['missing', 'method']))->toBeFalse()
        ->and(Reflector::isCallable([new stdClass, 'nope']))->toBeFalse();
});

test('isCallable recognises public instance methods', function () {
    $subject = new class
    {
        public function greet(): string
        {
            return 'hi';
        }

        protected function hidden(): void {}
    };

    expect(Reflector::isCallable([$subject, 'greet']))->toBeTrue()
        ->and(Reflector::isCallable([$subject, 'hidden']))->toBeFalse();
});

test('getParameterClassName resolves named types', function () {
    $fn = static function (DateTimeInterface $when, string $label, int|DateTime $mixed): void {};
    $parameters = (new ReflectionFunction($fn))->getParameters();

    expect(Reflector::getParameterClassName($parameters[0]))->toBe(DateTimeInterface::class)
        ->and(Reflector::getParameterClassName($parameters[1]))->toBeNull()
        ->and(Reflector::getParameterClassNames($parameters[2]))->toBe([DateTime::class]);
});

test('isParameterSubclassOf checks the hierarchy', function () {
    $fn = static function (DateTime $when): void {};
    $parameter = (new ReflectionFunction($fn))->getParameters()[0];

    expect(Reflector::isParameterSubclassOf($parameter, DateTimeInterface::class))->toBeTrue()
        ->and(Reflector::isParameterSubclassOf($parameter, ArrayObject::class))->toBeFalse();
});

test('ReflectsClosures is a trait and can be composed', function () {
    expect(trait_exists(ReflectsClosures::class))->toBeTrue();

    $subject = new class
    {
        use ReflectsClosures;

        public function typeOf(Closure $closure): string
        {
            return $this->firstClosureParameterType($closure);
        }
    };

    expect($subject->typeOf(fn (ArrayObject $x) => null))->toBe(ArrayObject::class);
});

test('firstClosureParameterType rejects an untyped closure', function () {
    $subject = new class
    {
        use ReflectsClosures;

        public function typeOf(Closure $closure): string
        {
            return $this->firstClosureParameterType($closure);
        }
    };

    $subject->typeOf(fn ($x) => null);
})->throws(RuntimeException::class);
