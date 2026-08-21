<?php

use Voyager\NutsAndBolts\HtmlString;

test('toHtml returns the string verbatim', function (string $value) {
    expect((new HtmlString($value))->toHtml())->toEqual($value);
})->with([
    'basic html'            => ['<h1>foo</h1>'],
    'leading blank spaces'  => ['   <h1>      foo</h1>'],
    'trailing blank spaces' => ['<h1>foo       </h1>   '],
    'empty string'          => [''],
    'plain text'            => ['foo bar'],
]);

test('casting to string returns the html', function () {
    expect((string) new HtmlString('<h1>foo</h1>'))->toEqual('<h1>foo</h1>');
});

test('casting a null value to string yields a string', function () {
    expect((string) new HtmlString(null))->toBeString();
});

test('isEmpty only treats an empty or null value as empty', function (?string $value, bool $expected) {
    expect((new HtmlString($value))->isEmpty())->toBe($expected);
})->with([
    'empty string' => ['', true],
    'null'         => [null, true],
    'whitespace'   => ['   ', false],
    'content'      => ['<p>Hello</p>', false],
]);

test('isNotEmpty is the inverse of isEmpty', function () {
    expect((new HtmlString('foo'))->isNotEmpty())->toBeTrue();
});
