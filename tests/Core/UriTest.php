<?php

use Voyager\NutsAndBolts\Uri;
use Voyager\NutsAndBolts\UriQueryString;

it('parses the parts of a URI', function () {
    $uri = Uri::of('https://ada:secret@venusian.dev:8443/docs/install?a=1&b[]=x#top');

    expect($uri->scheme())->toBe('https')
        ->and($uri->user())->toBe('ada')
        ->and($uri->user(withPassword: true))->toBe('ada:secret')
        ->and($uri->host())->toBe('venusian.dev')
        ->and($uri->port())->toBe(8443)
        ->and($uri->path())->toBe('docs/install')
        ->and($uri->pathSegments()->all())->toBe(['docs', 'install'])
        ->and($uri->query())->toBeInstanceOf(UriQueryString::class)
        ->and($uri->query()->all())->toBe(['a' => '1', 'b' => ['x']])
        ->and($uri->fragment())->toBe('top');
});

it('returns a new URI from every with method and leaves the original alone', function () {
    $uri = Uri::of('http://venusian.dev/a');

    $changed = $uri->withScheme('https')->withHost('docs.venusian.dev')->withPort(444)->withPath('b')->withFragment('f');

    expect((string) $uri)->toBe('http://venusian.dev/a')
        ->and((string) $changed)->toBe('https://docs.venusian.dev:444/b#f')
        ->and((string) $changed->withoutFragment())->toBe('https://docs.venusian.dev:444/b');
});

it('merges, replaces and removes query parameters', function () {
    $uri = Uri::of('https://venusian.dev/?a=1&b=2');

    expect($uri->withQuery(['b' => 3, 'c' => 4])->query()->all())->toBe(['a' => '1', 'b' => '3', 'c' => '4'])
        ->and($uri->withQuery(['c' => 4], merge: false)->query()->all())->toBe(['c' => '4'])
        ->and($uri->withQueryIfMissing(['a' => 9, 'd' => 5])->query()->all())->toBe(['a' => '1', 'b' => '2', 'd' => '5'])
        ->and($uri->replaceQuery(['z' => 1])->query()->all())->toBe(['z' => '1'])
        ->and($uri->withoutQuery('a')->query()->all())->toBe(['b' => '2'])
        ->and($uri->pushOntoQuery('list', 'x')->query()->all())->toBe(['a' => '1', 'b' => '2', 'list' => ['x']]);
});

it('lets an extension turn its own values into query values', function () {
    $extended = new class('https://venusian.dev') extends Uri
    {
        protected function queryValue(mixed $value): mixed
        {
            return $value instanceof Stringable ? 'key:'.$value : $value;
        }
    };

    $key = new class implements Stringable
    {
        public function __toString(): string
        {
            return '42';
        }
    };

    $with = $extended->withQuery(['id' => $key]);

    expect($with)->toBeInstanceOf($extended::class)
        ->and($with->query()->get('id'))->toBe('key:42');
});

it('casts to a string, JSON and HTML, and decodes its query', function () {
    $uri = Uri::of('https://venusian.dev/?q=a%20b');

    expect($uri->value())->toBe('https://venusian.dev/?q=a%20b')
        ->and(json_encode($uri))->toBe('"https:\/\/venusian.dev\/?q=a%20b"')
        ->and($uri->toHtml())->toBe('https://venusian.dev/?q=a%20b')
        ->and($uri->decode())->toBe('https://venusian.dev/?q=a b')
        ->and(Uri::of('')->isEmpty())->toBeTrue()
        ->and($uri->isNotEmpty())->toBeTrue();
});
