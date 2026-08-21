<?php

use Voyager\NutsAndBolts\Uri;

describe('parsing', function () {
    test('the parts of a plain uri are exposed', function () {
        $uri = Uri::of($originalUri = 'https://venusian.com/docs/installation');

        expect($uri->scheme())->toEqual('https')
            ->and($uri->user())->toBeNull()
            ->and($uri->password())->toBeNull()
            ->and($uri->host())->toEqual('venusian.com')
            ->and($uri->port())->toBeNull()
            ->and($uri->path())->toEqual('docs/installation')
            ->and($uri->query()->toArray())->toEqual([])
            ->and((string) $uri->query())->toEqual('')
            ->and($uri->query()->decode())->toEqual('')
            ->and($uri->fragment())->toBeNull()
            ->and((string) $uri)->toEqual($originalUri);
    });

    test('credentials, query and fragment are exposed', function () {
        $uri = Uri::of('https://taylor:password@venusian.com/docs/installation?version=1#hello');

        expect($uri->user())->toEqual('taylor')
            ->and($uri->password())->toEqual('password')
            ->and($uri->fragment())->toEqual('hello')
            ->and($uri->query()->all())->toEqual(['version' => 1])
            ->and($uri->query()->integer('version'))->toEqual(1)
            ->and($uri->authority())->toEqual('taylor:password@venusian.com');
    });

    test('a complicated query string parses into nested arrays', function () {
        $uri = Uri::of('https://example.com/users?key_1=value&key_2[sub_field]=value&key_3[]=value&key_4[9]=value&key_5[][][foo][9]=bar&key.6=value&flag_value');

        expect($uri->query()->all())->toEqual([
            'key_1' => 'value',
            'key_2' => [
                'sub_field' => 'value',
            ],
            'key_3' => [
                'value',
            ],
            'key_4' => [
                9 => 'value',
            ],
            'key_5' => [
                [
                    [
                        'foo' => [
                            9 => 'bar',
                        ],
                    ],
                ],
            ],
            'key.6' => 'value',
            'flag_value' => '',
        ])->and($uri->query()->decode())
            ->toEqual('key_1=value&key_2[sub_field]=value&key_3[]=value&key_4[9]=value&key_5[][][foo][9]=bar&key.6=value&flag_value');
    });
});

test('a uri can be built up part by part', function () {
    $uri = Uri::of()
        ->withHost('venusian.com')
        ->withScheme('https')
        ->withUser('taylor', 'password')
        ->withPath('/docs/installation')
        ->withPort(80)
        ->withQuery(['version' => 1])
        ->withFragment('hello');

    expect((string) $uri)->toEqual('https://taylor:password@venusian.com:80/docs/installation?version=1#hello');
});

describe('query manipulation', function () {
    test('withQuery merges and withoutQuery removes', function () {
        $uri = Uri::of('https://venusian.com')->withQuery([
            'name' => 'Taylor',
            'age' => 38,
            'role' => [
                'title' => 'Developer',
                'focus' => 'PHP',
            ],
            'tags' => [
                'person',
                'employee',
            ],
            'flag' => '',
        ])->withoutQuery(['name']);

        expect($uri->query()->decode())->toEqual('age=38&role[title]=Developer&role[focus]=PHP&tags[0]=person&tags[1]=employee&flag=')
            ->and($uri->replaceQuery(['name' => 'Taylor'])->query()->decode())->toEqual('name=Taylor');
    });

    test('pushOntoQuery appends to a multi-value item and creates missing ones', function () {
        $uri = Uri::of('https://venusian.com?tags[]=foo');

        expect($uri->pushOntoQuery('tags', 'bar')->query()->all())->toEqual(['tags' => ['foo', 'bar']])
            ->and($uri->pushOntoQuery('tags', ['bar', 'baz'])->query()->all())->toEqual(['tags' => ['foo', 'bar', 'baz']])
            ->and($uri->pushOntoQuery('names', 'Taylor')->query()->all())->toEqual(['tags' => ['foo'], 'names' => ['Taylor']]);
    });

    test('pushOntoQuery promotes a single-value item to an array', function () {
        $uri = Uri::of('https://venusian.com?tag=foo');

        expect($uri->pushOntoQuery('tag', 'bar')->query()->all())->toEqual(['tag' => ['foo', 'bar']]);
    });

    test('keys with dots are replaced or merged consistently', function () {
        $uri = Uri::of('https://dot.test/?foo.bar=baz');

        expect($uri->withQuery(['foo.bar' => 'zab'])->query()->decode())->toEqual('foo.bar=baz&foo[bar]=zab')
            ->and($uri->replaceQuery(['foo.bar' => 'zab'])->query()->decode())->toEqual('foo[bar]=zab');
    });

    test('withQuery on an empty array leaves no trailing question mark', function () {
        $uri = Uri::of('https://venusian.com');

        expect((string) $uri)->toEqual('https://venusian.com')
            ->and((string) $uri->withQuery([]))->toEqual('https://venusian.com');
    });
});

describe('withQueryIfMissing', function () {
    test('existing parameters are preserved', function () {
        $uri = Uri::of('https://venusian.com?existing=value')->withQueryIfMissing([
            'new' => 'parameter',
            'existing' => 'new_value',
        ]);

        expect($uri->query()->decode())->toEqual('existing=value&new=parameter');
    });

    test('nested arrays are added to an empty query string', function () {
        $uri = Uri::of('https://venusian.com')->withQueryIfMissing([
            'name' => 'Taylor',
            'role' => [
                'title' => 'Developer',
                'focus' => 'PHP',
            ],
            'tags' => [
                'person',
                'employee',
            ],
        ]);

        expect($uri->query()->decode())->toEqual('name=Taylor&role[title]=Developer&role[focus]=PHP&tags[0]=person&tags[1]=employee');
    });

    test('an indexed array that is already present is left untouched', function () {
        $uri = Uri::of('https://venusian.com?name=Taylor&tags[0]=person')->withQueryIfMissing([
            'name' => 'Changed',
            'age' => 38,
            'tags' => ['should', 'not', 'change'],
        ]);

        expect($uri->query()->decode())->toEqual('name=Taylor&tags[0]=person&age=38')
            ->and($uri->query()->all())->toEqual(['name' => 'Taylor', 'tags' => ['person'], 'age' => 38]);
    });

    test('a nested array that is already present is left untouched', function () {
        $uri = Uri::of('https://venusian.com?user[name]=Taylor')->withQueryIfMissing([
            'user' => [
                'name' => 'Should Not Change',
                'age' => 38,
            ],
            'settings' => [
                'theme' => 'dark',
            ],
        ]);

        expect($uri->query()->all())->toEqual([
            'user' => [
                'name' => 'Taylor',
            ],
            'settings' => [
                'theme' => 'dark',
            ],
        ]);
    });
});

test('decode renders the whole uri without percent encoding', function () {
    $uri = Uri::of('https://venusian.com/docs/11.x/installation')->withQuery(['tags' => ['first', 'second']]);

    expect($uri->decode())->toEqual('https://venusian.com/docs/11.x/installation?tags[0]=first&tags[1]=second');
});

describe('path segments', function () {
    test('an empty path has no segments', function () {
        expect(Uri::of('https://venusian.com')->pathSegments()->toArray())->toEqual([]);
    });

    test('the path splits on slashes', function () {
        $uri = Uri::of('https://venusian.com/one/two/three');

        expect($uri->pathSegments()->toArray())->toEqual(['one', 'two', 'three'])
            ->and($uri->pathSegments()->first())->toEqual('one');
    });

    test('the query, a trailing slash and a fragment do not add segments', function (string $url) {
        expect(Uri::of($url)->pathSegments()->count())->toEqual(3);
    })->with([
        'query'          => ['https://venusian.com/one/two/three?foo=bar'],
        'trailing slash' => ['https://venusian.com/one/two/three/?foo=bar'],
        'fragment'       => ['https://venusian.com/one/two/three/#foo=bar'],
    ]);
});

test('Uri is macroable', function () {
    Uri::macro('myMacro', function () {
        return $this->withPath('foobar');
    });

    expect((string) (new Uri('https://venusian.com/'))->myMacro())->toBe('https://venusian.com/foobar');
});
