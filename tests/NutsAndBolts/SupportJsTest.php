<?php

use Tests\NutsAndBolts\Fixtures\IntBackedEnum;
use Tests\NutsAndBolts\Fixtures\StringBackedEnum;
use Voyager\Contracts\NutsAndBolts\Arrayable;
use Voyager\Contracts\NutsAndBolts\Htmlable;
use Voyager\Contracts\NutsAndBolts\Jsonable;
use Voyager\NutsAndBolts\Js;

/** The JSON.parse() expression every ['foo' => 'hello', 'bar' => 'world'] payload renders to. */
const JS_FOO_BAR = "JSON.parse('{\\u0022foo\\u0022:\\u0022hello\\u0022,\\u0022bar\\u0022:\\u0022world\\u0022}')";

test('scalars render as literals', function (mixed $value, string $expected) {
    expect((string) Js::from($value))->toBe($expected);
})->with([
    'false'  => [false, 'false'],
    'true'   => [true, 'true'],
    'int'    => [1, '1'],
    'float'  => [1.1, '1.1'],
    'array'  => [[], '[]'],
    'null'   => [null, 'null'],
    'string' => ['Hello world', "'Hello world'"],
]);

test('an empty collection renders as an empty array', function () {
    expect((string) Js::from(collect()))->toBe('[]');
});

test('html in a string is escaped', function () {
    expect((string) Js::from('<div class="foo">\'quoted html\'</div>'))
        ->toEqual("'\\u003Cdiv class=\\u0022foo\\u0022\\u003E\\u0027quoted html\\u0027\\u003C\\/div\\u003E'");
});

test('a list renders through JSON.parse', function () {
    expect((string) Js::from(['hello', 'world']))->toEqual("JSON.parse('[\\u0022hello\\u0022,\\u0022world\\u0022]')");
});

test('an associative array renders through JSON.parse', function () {
    expect((string) Js::from(['foo' => 'hello', 'bar' => 'world']))->toEqual(JS_FOO_BAR);
});

test('an object renders through JSON.parse', function () {
    expect((string) Js::from((object) ['foo' => 'hello', 'bar' => 'world']))->toEqual(JS_FOO_BAR);
});

test('JsonSerializable takes precedence over Arrayable', function () {
    // JsonSerializable should take precedence over Arrayable, so we'll
    // implement both and make sure the correct data is used.
    $data = new class implements Arrayable, JsonSerializable
    {
        public $foo = 'not hello';

        public $bar = 'not world';

        public function jsonSerialize(): mixed
        {
            return ['foo' => 'hello', 'bar' => 'world'];
        }

        public function toArray()
        {
            return ['foo' => 'not hello', 'bar' => 'not world'];
        }
    };

    expect((string) Js::from($data))->toEqual(JS_FOO_BAR);
});

test('Jsonable takes precedence over JsonSerializable and Arrayable', function () {
    // Jsonable should take precedence over JsonSerializable and Arrayable, so we'll
    // implement all three and make sure the correct data is used.
    $data = new class implements Arrayable, Jsonable, JsonSerializable
    {
        public $foo = 'not hello';

        public $bar = 'not world';

        public function toJson(int $options = 0): string
        {
            return json_encode(['foo' => 'hello', 'bar' => 'world'], $options);
        }

        public function jsonSerialize(): mixed
        {
            return ['foo' => 'not hello', 'bar' => 'not world'];
        }

        public function toArray()
        {
            return ['foo' => 'not hello', 'bar' => 'not world'];
        }
    };

    expect((string) Js::from($data))->toEqual(JS_FOO_BAR);
});

test('Arrayable is used when nothing else applies', function () {
    $data = new class implements Arrayable
    {
        public $foo = 'not hello';

        public $bar = 'not world';

        public function toArray()
        {
            return ['foo' => 'hello', 'bar' => 'world'];
        }
    };

    expect((string) Js::from($data))->toEqual(JS_FOO_BAR);
});

describe('Htmlable', function () {
    test('a plain Htmlable renders its html as a string literal', function () {
        $data = new class implements Htmlable
        {
            public function toHtml()
            {
                return '<p>Hello, World!</p>';
            }
        };

        expect((string) Js::from($data))->toEqual("'\\u003Cp\\u003EHello, World!\\u003C\\/p\\u003E'");
    });

    test('Arrayable takes precedence over Htmlable', function () {
        $data = new class implements Arrayable, Htmlable
        {
            public function toHtml()
            {
                return '<p>Hello, World!</p>';
            }

            public function toArray()
            {
                return ['foo' => 'hello', 'bar' => 'world'];
            }
        };

        expect((string) Js::from($data))->toEqual(JS_FOO_BAR);
    });

    test('Jsonable takes precedence over Htmlable', function () {
        $data = new class implements Htmlable, Jsonable
        {
            public function toHtml()
            {
                return '<p>Hello, World!</p>';
            }

            public function toJson(int $options = 0): string
            {
                return json_encode(['foo' => 'hello', 'bar' => 'world'], $options);
            }
        };

        expect((string) Js::from($data))->toEqual(JS_FOO_BAR);
    });

    test('JsonSerializable takes precedence over Htmlable', function () {
        $data = new class implements Htmlable, JsonSerializable
        {
            public function toHtml()
            {
                return '<p>Hello, World!</p>';
            }

            public function jsonSerialize(): mixed
            {
                return ['foo' => 'hello', 'bar' => 'world'];
            }
        };

        expect((string) Js::from($data))->toEqual(JS_FOO_BAR);
    });
});

test('backed enums render as their value', function () {
    expect((string) Js::from(IntBackedEnum::TWO))->toBe('2')
        ->and((string) Js::from(StringBackedEnum::HELLO_WORLD))->toBe("'Hello world'");
});
