<?php

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use GuzzleHttp\Exception\TooManyRedirectsException;
use GuzzleHttp\Middleware;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\RejectedPromise;
use GuzzleHttp\Psr7\NoSeekStream;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Psr7\Response as Psr7Response;
use GuzzleHttp\Psr7\Utils;
use GuzzleHttp\TransferStats;
use Voyager\Contracts\Events\Dispatcher;
use Voyager\Contracts\NutsAndBolts\Arrayable;
use Voyager\Http\Client\Batch;
use Voyager\Http\Client\BatchInProgressException;
use Voyager\Http\Client\ConnectionException;
use Voyager\Http\Client\Events\RequestSending;
use Voyager\Http\Client\Events\ResponseReceived;
use Voyager\Http\Client\Factory;
use Voyager\Http\Client\PendingRequest;
use Voyager\Http\Client\Pool;
use Voyager\Http\Client\Request;
use Voyager\Http\Client\RequestException;
use Voyager\Http\Client\Response;
use Voyager\Http\Client\ResponseSequence;
use Voyager\Http\Client\StrayRequestException;
use Voyager\NutsAndBolts\DataObjects\Arr;
use Voyager\NutsAndBolts\DataObjects\Carbon;
use Voyager\NutsAndBolts\Collection;
use Voyager\NutsAndBolts\Defer\DeferredCallback;
use Voyager\NutsAndBolts\Fluent;
use Voyager\NutsAndBolts\Sleep;
use Voyager\NutsAndBolts\DataObjects\Str;
use Voyager\NutsAndBolts\DataObjects\Stringable;
use Tests\Http\Stubs\BodyTrackingResponse;
use Tests\Http\Stubs\CustomFactory;
use Tests\Http\Stubs\TestResponse;
use Mockery as m;
use PHPUnit\Framework\AssertionFailedError;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\VarDumper\VarDumper;

beforeEach(function () {
    $this->factory = new Factory;

    RequestException::truncate();
});

afterEach(function () {
    Response::flushState();
});

test('stubbed responses are returned after faking', function () {
    $this->factory->fake();

    $response = $this->factory->post('http://laravel.com/test-missing-page');

    expect($response->ok())->toBeTrue();
});

test('created request', function () {
    $this->factory->fake([
        'vapor.laravel.com' => $this->factory::response('', 201),
        'forge.laravel.com' => $this->factory::response('', 200),
    ]);

    $response = $this->factory->post('http://vapor.laravel.com');
    expect($response->created())->toBeTrue();

    $response = $this->factory->post('http://forge.laravel.com');
    expect($response->created())->toBeFalse();
});

test('status code shorthand', function () {
    $this->factory->fake([
        'forge.laravel.com' => 204,
        'vapor.laravel.com' => 201,
    ]);

    $response = $this->factory->post('http://forge.laravel.com');
    expect($response->noContent())->toBeTrue();

    $response = $this->factory->post('http://vapor.laravel.com');
    expect($response->created())->toBeTrue();
});

test('status code shorthand assume body when invalid http status code', function () {
    $this->factory->fake([
        'forge.laravel.com' => 999,
        'vapor.laravel.com' => 1,
    ]);

    $response = $this->factory->post('http://forge.laravel.com');
    expect($response->ok())->toBeTrue();
    expect($response->body())->toBe('999');

    $response = $this->factory->post('http://vapor.laravel.com');
    expect($response->ok())->toBeTrue();
    expect($response->body())->toBe('1');
});

test('body shorthands', function () {
    $this->factory->fake([
        'google.com' => 'Hello World',
        'github.com' => ['foo' => 'bar'],
    ]);

    $response = $this->factory->get('http://google.com');
    expect($response->ok())->toBeTrue();
    expect($response->body())->toBe('Hello World');

    $response = $this->factory->post('http://github.com');
    expect($response->ok())->toBeTrue();
    expect($response->body())->toBe('{"foo":"bar"}');
    expect($response->json())->toBe(['foo' => 'bar']);
});

test('accepted request', function () {
    $this->factory->fake([
        'vapor.laravel.com' => $this->factory::response('', 202),
        'forge.laravel.com' => $this->factory::response('', 200),
    ]);

    $response = $this->factory->post('http://vapor.laravel.com');
    expect($response->accepted())->toBeTrue();

    $response = $this->factory->post('http://forge.laravel.com');
    expect($response->accepted())->toBeFalse();
});

test('moved permanently request', function () {
    $this->factory->fake([
        'vapor.laravel.com' => $this->factory::response('', 301),
        'forge.laravel.com' => $this->factory::response('', 200),
    ]);

    $response = $this->factory->post('http://vapor.laravel.com');
    expect($response->movedPermanently())->toBeTrue();

    $response = $this->factory->post('http://forge.laravel.com');
    expect($response->movedPermanently())->toBeFalse();
});

test('no content request', function () {
    $this->factory->fake([
        'vapor.laravel.com' => $this->factory::response('', 204),
        'forge.laravel.com' => $this->factory::response('', 200),
    ]);

    $response = $this->factory->post('http://vapor.laravel.com');
    expect($response->noContent())->toBeTrue();

    $response = $this->factory->post('http://forge.laravel.com');
    expect($response->noContent())->toBeFalse();
});

test('found request', function () {
    $this->factory->fake([
        'vapor.laravel.com' => $this->factory::response('', 302),
        'forge.laravel.com' => $this->factory::response('', 200),
    ]);

    $response = $this->factory->post('http://vapor.laravel.com');
    expect($response->found())->toBeTrue();

    $response = $this->factory->post('http://forge.laravel.com');
    expect($response->found())->toBeFalse();
});

test('not modified request', function () {
    $this->factory->fake([
        'vapor.laravel.com' => $this->factory::response('', 304),
        'forge.laravel.com' => $this->factory::response('', 200),
    ]);

    $response = $this->factory->post('https://vapor.laravel.com');
    expect($response->notModified())->toBeTrue();

    $response = $this->factory->post('https://forge.laravel.com');
    expect($response->notModified())->toBeFalse();
});

test('bad request request', function () {
    $this->factory->fake([
        'vapor.laravel.com' => $this->factory::response('', 400),
        'forge.laravel.com' => $this->factory::response('', 200),
    ]);

    $response = $this->factory->post('http://vapor.laravel.com');
    expect($response->badRequest())->toBeTrue();

    $response = $this->factory->post('http://forge.laravel.com');
    expect($response->badRequest())->toBeFalse();
});

test('payment required request', function () {
    $this->factory->fake([
        'vapor.laravel.com' => $this->factory::response('', 402),
        'forge.laravel.com' => $this->factory::response('', 200),
    ]);

    $response = $this->factory->post('http://vapor.laravel.com');
    expect($response->paymentRequired())->toBeTrue();

    $response = $this->factory->post('http://forge.laravel.com');
    expect($response->paymentRequired())->toBeFalse();
});

test('request timeout request', function () {
    $this->factory->fake([
        'vapor.laravel.com' => $this->factory::response('', 408),
        'forge.laravel.com' => $this->factory::response('', 200),
    ]);

    $response = $this->factory->post('http://vapor.laravel.com');
    expect($response->requestTimeout())->toBeTrue();

    $response = $this->factory->post('http://forge.laravel.com');
    expect($response->requestTimeout())->toBeFalse();
});

test('conflict response request', function () {
    $this->factory->fake([
        'vapor.laravel.com' => $this->factory::response('', 409),
        'forge.laravel.com' => $this->factory::response('', 200),
    ]);

    $response = $this->factory->post('http://vapor.laravel.com');
    expect($response->conflict())->toBeTrue();

    $response = $this->factory->post('http://forge.laravel.com');
    expect($response->conflict())->toBeFalse();
});

test('unprocessable content request', function () {
    $this->factory->fake([
        'vapor.laravel.com' => $this->factory::response('', 422),
        'forge.laravel.com' => $this->factory::response('', 200),
    ]);

    $response = $this->factory->post('http://vapor.laravel.com');
    expect($response->unprocessableContent())->toBeTrue();

    $response = $this->factory->post('http://forge.laravel.com');
    expect($response->unprocessableContent())->toBeFalse();
});

test('unprocessable entity request', function () {
    $this->factory->fake([
        'vapor.laravel.com' => $this->factory::response('', 422),
        'forge.laravel.com' => $this->factory::response('', 200),
    ]);

    $response = $this->factory->post('http://vapor.laravel.com');
    expect($response->unprocessableEntity())->toBeTrue();

    $response = $this->factory->post('http://forge.laravel.com');
    expect($response->unprocessableEntity())->toBeFalse();
});

test('too many requests request', function () {
    $this->factory->fake([
        'vapor.laravel.com' => $this->factory::response('', 429),
        'forge.laravel.com' => $this->factory::response('', 200),
    ]);

    $response = $this->factory->post('http://vapor.laravel.com');
    expect($response->tooManyRequests())->toBeTrue();

    $response = $this->factory->post('http://forge.laravel.com');
    expect($response->tooManyRequests())->toBeFalse();
});

test('unauthorized request', function () {
    $this->factory->fake([
        'laravel.com' => $this->factory::response('', 401),
    ]);

    $response = $this->factory->post('http://laravel.com');

    expect($response->unauthorized())->toBeTrue();
});

test('forbidden request', function () {
    $this->factory->fake([
        'laravel.com' => $this->factory::response('', 403),
    ]);

    $response = $this->factory->post('http://laravel.com');

    expect($response->forbidden())->toBeTrue();
});

test('not found response', function () {
    $this->factory->fake([
        'laravel.com' => $this->factory::response('', 404),
    ]);

    $response = $this->factory->post('http://laravel.com');

    expect($response->notFound())->toBeTrue();
});

test('response body casting', function () {
    $this->factory->fake([
        '*' => ['result' => ['foo' => 'bar']],
    ]);

    $response = $this->factory->get('http://foo.com/api');

    expect($response->body())->toBe('{"result":{"foo":"bar"}}');
    expect((string) $response)->toBe('{"result":{"foo":"bar"}}');
    expect($response->json())->toBeArray();
    expect($response->json()['result'])->toBe(['foo' => 'bar']);
    expect($response->json('result'))->toBe(['foo' => 'bar']);
    expect($response->json('result.foo'))->toBe('bar');
    expect($response->json('missing_key', 'default'))->toBe('default');
    expect($response['result'])->toBe(['foo' => 'bar']);
});

test('response object as array', function () {
    $this->factory->fake([
        '*' => [['foo' => 'bar'], ['bar' => 'foo']],
    ]);

    $response = $this->factory->get('http://foo.com/api');

    expect($response->body())->toBe('[{"foo":"bar"},{"bar":"foo"}]');
    expect((string) $response)->toBe('[{"foo":"bar"},{"bar":"foo"}]');
    expect($response->object())->toBeArray();
    expect($response->object()[0]->foo)->toBe('bar');
});

test('response object as object', function () {
    $this->factory->fake([
        '*' => ['result' => ['foo' => 'bar']],
    ]);

    $response = $this->factory->get('http://foo.com/api');

    expect($response->object())->toBeObject();
    expect($response->object()->result->foo)->toBe('bar');
});

test('response object is tappable', function () {
    $bar = null;
    $this->factory->fake([
        '*' => ['result' => ['foo' => 'bar']],
    ]);

    $this->factory->get('http://foo.com/api')
        ->tap(function (Response $response) use (&$bar) {
            $bar = $response['result']['foo'];
        });

    expect($bar)->toBe('bar');
});

test('response object is macroable', function () {
    Response::macro('movieFields', function () {
        return $this->collect()
            ->mapWithKeys(fn ($field, $key) => [strtolower($key) => $field])
            ->toArray();
    });

    $response = $this->factory->fake([
        '*' => [
            'Title' => 'The Godfather',
            'Year' => 1972,
            'Rated' => 'R',
            'Runtime' => '175 min',
            'Director' => 'Francis Ford Coppola',
        ],
    ]);

    $response = $this->factory->get('http://www.omdbapi.com/?apikey=test_api_key&i=test_imdb_id');

    expect($response->movieFields())->toBeArray();
    expect($response->movieFields())->toBe([
        'title' => 'The Godfather',
        'year' => 1972,
        'rated' => 'R',
        'runtime' => '175 min',
        'director' => 'Francis Ford Coppola',
    ]);
});

test('response can be returned as resource', function () {
    $this->factory->fake([
        '*' => ['result' => ['foo' => 'bar']],
    ]);

    $response = $this->factory->get('http://foo.com/api');

    expect($response->resource())->toBeResource();
    expect(stream_get_contents($response->resource()))->toBe('{"result":{"foo":"bar"}}');
});

test('response can be returned as collection', function () {
    $this->factory->fake([
        '*' => ['result' => ['foo' => 'bar']],
    ]);

    $response = $this->factory->get('http://foo.com/api');

    expect($response->collect())->toBeInstanceOf(Collection::class);
    expect($response->collect())->toEqual(collect(['result' => ['foo' => 'bar']]));
    expect($response->collect('result'))->toEqual(collect(['foo' => 'bar']));
    expect($response->collect('result.foo'))->toEqual(collect(['bar']));
    expect($response->collect('missing_key'))->toEqual(collect());
});

test('response can be returned as fluent', function () {
    $this->factory->fake([
        '*' => ['result' => ['foo' => 'bar']],
    ]);

    $response = $this->factory->get('http://foo.com/api');

    expect($response->fluent())->toBeInstanceOf(Fluent::class);
    expect($response->fluent())->toEqual(new Fluent(['result' => ['foo' => 'bar']]));
    expect($response->fluent('result'))->toEqual(new Fluent(['foo' => 'bar']));
    expect($response->fluent('result.foo'))->toEqual(new Fluent(['bar']));
    expect($response->fluent('missing_key'))->toEqual(new Fluent([]));
});

test('send request body as json by default', function () {
    $body = '{"test":"phpunit"}';

    $fakeRequest = function (Request $request) use ($body) {
        expect($request->body())->toBe($body);
        expect($request->header('Content-Type'))->toContain('application/json');

        return ['my' => 'response'];
    };

    $this->factory->fake($fakeRequest);

    $this->factory->withBody($body)->send('get', 'http://foo.com/api');
});

test('send request body with many ampersands', function () {
    $body = str_repeat('A thousand &. ', 1000);

    $fakeRequest = function (Request $request) use ($body) {
        expect($request->body())->toBe($body);
        expect($request->header('Content-Type'))->toContain('text/plain');

        return ['my' => 'response'];
    };

    $this->factory->fake($fakeRequest);

    $this->factory->withBody($body, 'text/plain')->send('post', 'http://foo.com/api');
});

test('send stream request body', function () {
    $string = 'Look at me, i am a stream!!';
    $resource = fopen('php://temp', 'w');
    fwrite($resource, $string);
    rewind($resource);
    $body = Utils::streamFor($resource);

    $fakeRequest = function (Request $request) use ($string) {
        expect($request->body())->toBe($string);
        expect($request->header('Content-Type'))->toContain('text/plain');

        return ['my' => 'response'];
    };

    $this->factory->fake($fakeRequest);

    $this->factory->withBody($body, 'text/plain')->send('post', 'http://foo.com/api');
});

test('urls can be stubbed by path', function () {
    $this->factory->fake([
        'foo.com/*' => ['page' => 'foo'],
        'bar.com/*' => ['page' => 'bar'],
        '*' => ['page' => 'fallback'],
    ]);

    $fooResponse = $this->factory->post('http://foo.com/test');
    $barResponse = $this->factory->post('http://bar.com/test');
    $fallbackResponse = $this->factory->post('http://fallback.com/test');

    expect($fooResponse['page'])->toBe('foo');
    expect($barResponse['page'])->toBe('bar');
    expect($fallbackResponse['page'])->toBe('fallback');

    $this->factory->assertSent(function (Request $request) {
        return $request->url() === 'http://foo.com/test' &&
               $request->hasHeader('Content-Type', 'application/json');
    });
});

test('can send json data', function () {
    $this->factory->fake();

    $this->factory->withHeaders([
        'X-Test-Header' => 'foo',
        'X-Test-ArrayHeader' => ['bar', 'baz'],
    ])->post('http://foo.com/json', [
        'name' => 'Taylor',
    ]);

    $this->factory->assertSent(function (Request $request) {
        return $request->url() === 'http://foo.com/json' &&
               $request->hasHeader('Content-Type', 'application/json') &&
               $request->hasHeader('X-Test-Header', 'foo') &&
               $request->hasHeader('X-Test-ArrayHeader', ['bar', 'baz']) &&
               $request['name'] === 'Taylor';
    });
});

test('can send form data', function () {
    $this->factory->fake();

    $this->factory->asForm()->post('http://foo.com/form', [
        'name' => 'Taylor',
        'title' => 'Laravel Developer',
    ]);

    $this->factory->assertSent(function (Request $request) {
        return $request->url() === 'http://foo.com/form' &&
               $request->hasHeader('Content-Type', 'application/x-www-form-urlencoded') &&
               $request['name'] === 'Taylor';
    });
});

test('can send arrayable form data', function (string $method) {
    $this->factory->fake();

    $this->factory->asForm()->{$method}('http://foo.com/form', new Fluent([
        'name' => 'Taylor',
        'title' => 'Laravel Developer',
    ]));

    $this->factory->assertSent(function (Request $request) {
        return $request->url() === 'http://foo.com/form' &&
               $request->hasHeader('Content-Type', 'application/x-www-form-urlencoded') &&
               $request['name'] === 'Taylor';
    });
})->with('methods receiving arrayable data');

test('can send json serializable data', function (string $method) {
    $this->factory->fake();

    $this->factory->asJson()->{$method}('http://foo.com/form', new class implements JsonSerializable
    {
        public function jsonSerialize(): mixed
        {
            return [
                'name' => 'Taylor',
                'title' => 'Laravel Developer',
            ];
        }
    });

    $this->factory->assertSent(function (Request $request) {
        return $request->url() === 'http://foo.com/form' &&
            $request->hasHeader('Content-Type', 'application/json') &&
            $request['name'] === 'Taylor';
    });
})->with('methods receiving arrayable data');

test('prefers json serializable over arrayable data', function (string $method) {
    $this->factory->fake();

    $this->factory->asJson()->{$method}('http://foo.com/form', new class implements JsonSerializable, Arrayable
    {
        public function jsonSerialize(): mixed
        {
            return [
                'attributes' => (object) [],
            ];
        }

        public function toArray(): array
        {
            return [
                'attributes' => [],
            ];
        }
    });

    $this->factory->assertSent(function (Request $request) {
        return $request->url() === 'http://foo.com/form' &&
            $request->hasHeader('Content-Type', 'application/json') &&
            $request->body() === '{"attributes":{}}';
    });
})->with('methods receiving arrayable data');

test('can send json data with stringable', function () {
    $this->factory->fake();

    $this->factory->withHeaders([
        'X-Test-Header' => 'foo',
        'X-Test-ArrayHeader' => ['bar', 'baz'],
    ])->post('http://foo.com/json', [
        'name' => new Stringable('Taylor'),
    ]);

    $this->factory->assertSent(function (Request $request) {
        return $request->url() === 'http://foo.com/json' &&
               $request->hasHeader('Content-Type', 'application/json') &&
               $request->hasHeader('X-Test-Header', 'foo') &&
               $request->hasHeader('X-Test-ArrayHeader', ['bar', 'baz']) &&
               $request['name'] === 'Taylor';
    });
});

test('can send form data with stringable', function () {
    $this->factory->fake();

    $this->factory->asForm()->post('http://foo.com/form', [
        'name' => new Stringable('Taylor'),
        'title' => 'Laravel Developer',
    ]);

    $this->factory->assertSent(function (Request $request) {
        return $request->url() === 'http://foo.com/form' &&
               $request->hasHeader('Content-Type', 'application/x-www-form-urlencoded') &&
               $request['name'] === 'Taylor';
    });
});

test('can send form data with stringable in arrays', function () {
    $this->factory->fake();

    $this->factory->asForm()->post('http://foo.com/form', [
        'posts' => [['title' => new Stringable('Taylor')]],
    ]);

    $this->factory->assertSent(function (Request $request) {
        return $request->url() === 'http://foo.com/form' &&
               $request->hasHeader('Content-Type', 'application/x-www-form-urlencoded') &&
               $request['posts'][0]['title'] === 'Taylor';
    });
});

test('recorded calls are emptied when fake is called', function () {
    $this->factory->fake([
        'http://foo.com/*' => ['page' => 'foo'],
    ]);

    $this->factory->get('http://foo.com/test');

    $this->factory->assertSent(function (Request $request) {
        return $request->url() === 'http://foo.com/test';
    });

    $this->factory->fake();

    $this->factory->assertNothingSent();
});

test('specific request is not being sent', function () {
    $this->factory->fake();

    $this->factory->post('http://foo.com/form', [
        'name' => 'Taylor',
    ]);

    $this->factory->assertNotSent(function (Request $request) {
        return $request->url() === 'http://foo.com/form' &&
            $request['name'] === 'Peter';
    });
});

test('no request is not being sent', function () {
    $this->factory->fake();

    $this->factory->assertNothingSent();
});

test('request count', function () {
    $this->factory->fake();
    $this->factory->assertSentCount(0);

    $this->factory->post('http://foo.com/form', [
        'name' => 'Taylor',
    ]);

    $this->factory->assertSentCount(1);

    $this->factory->post('http://foo.com/form', [
        'name' => 'Jim',
    ]);

    $this->factory->assertSentCount(2);
});

test('can send multipart data', function () {
    $this->factory->fake();

    $this->factory->asMultipart()->post('http://foo.com/multipart', [
        [
            'name' => 'foo',
            'contents' => 'data',
            'headers' => ['X-Test-Header' => 'foo'],
        ],
    ]);

    $this->factory->assertSent(function (Request $request) {
        return $request->url() === 'http://foo.com/multipart' &&
               Str::startsWith($request->header('Content-Type')[0], 'multipart') &&
               $request[0]['name'] === 'foo';
    });
});

test('files can be attached', function () {
    $this->factory->fake();

    $this->factory->attach('foo', 'data', 'file.txt', ['X-Test-Header' => 'foo'])
        ->post('http://foo.com/file');

    $this->factory->assertSent(function (Request $request) {
        return $request->url() === 'http://foo.com/file' &&
               Str::startsWith($request->header('Content-Type')[0], 'multipart') &&
               $request[0]['name'] === 'foo' &&
               $request->hasFile('foo', 'data', 'file.txt');
    });
});

test('attach preserves empty contents', function () {
    $this->factory->fake();

    $this->factory->attach('file')->post('http://foo.com/file');

    $this->factory->assertSent(function (Request $request) {
        return $request->url() === 'http://foo.com/file'
            && $request->isMultipart()
            && $request[0]['name'] === 'file'
            && array_key_exists('contents', $request[0])
            && $request[0]['contents'] === '';
    });
});

test('attach preserves falsey string contents and name', function () {
    $this->factory->fake();

    $this->factory->attach('0', '0')->post('http://foo.com/file');

    $this->factory->assertSent(function (Request $request) {
        return $request->url() === 'http://foo.com/file'
            && $request->isMultipart()
            && $request[0]['name'] === '0'
            && $request[0]['contents'] === '0';
    });
});

test('can send multipart data with simplified parameters', function () {
    $this->factory->fake();

    $this->factory->asMultipart()->post('http://foo.com/multipart', [
        'foo' => 'bar',
    ]);

    $this->factory->assertSent(function (Request $request) {
        return $request->url() === 'http://foo.com/multipart' &&
            Str::startsWith($request->header('Content-Type')[0], 'multipart') &&
            $request[0]['name'] === 'foo' &&
            $request[0]['contents'] === 'bar';
    });
});

test('can send multipart data with both simplified and extended parameters', function () {
    $this->factory->fake();

    $this->factory->asMultipart()->post('http://foo.com/multipart', [
        'foo' => 'bar',
        [
            'name' => 'foobar',
            'contents' => 'data',
            'headers' => ['X-Test-Header' => 'foo'],
        ],
    ]);

    $this->factory->assertSent(function (Request $request) {
        return $request->url() === 'http://foo.com/multipart' &&
            Str::startsWith($request->header('Content-Type')[0], 'multipart') &&
            $request[0]['name'] === 'foo' &&
            $request[0]['contents'] === 'bar' &&
            $request[1]['name'] === 'foobar' &&
            $request[1]['contents'] === 'data' &&
            $request[1]['headers']['X-Test-Header'] === 'foo';
    });
});

test('can send multipart data with array values', function () {
    $this->factory->fake();

    $this->factory->asMultipart()->post('http://foo.com/multipart', [
        'name' => 'Steve',
        'roles' => ['Network Administrator', 'Janitor'],
    ]);

    $this->factory->assertSent(function (Request $request) {
        return $request->url() === 'http://foo.com/multipart' &&
            Str::startsWith($request->header('Content-Type')[0], 'multipart') &&
            $request[0]['name'] === 'name' &&
            $request[0]['contents'] === 'Steve' &&
            $request[1]['name'] === 'roles[]' &&
            $request[1]['contents'] === 'Network Administrator' &&
            $request[2]['name'] === 'roles[]' &&
            $request[2]['contents'] === 'Janitor';
    });
});

test('can send multipart data with file and array values', function () {
    $this->factory->fake();

    $this->factory
        ->attach('attachment', 'photo_content', 'photo.jpg', ['Content-Type' => 'image/jpeg'])
        ->post('http://foo.com/multipart', [
            'name' => 'Steve',
            'roles' => ['Network Administrator', 'Janitor'],
        ]);

    $this->factory->assertSent(function (Request $request) {
        return $request->url() === 'http://foo.com/multipart' &&
            Str::startsWith($request->header('Content-Type')[0], 'multipart') &&
            $request[0]['name'] === 'name' &&
            $request[0]['contents'] === 'Steve' &&
            $request[1]['name'] === 'roles[]' &&
            $request[1]['contents'] === 'Network Administrator' &&
            $request[2]['name'] === 'roles[]' &&
            $request[2]['contents'] === 'Janitor' &&
            $request[3]['name'] === 'attachment' &&
            $request[3]['contents'] === 'photo_content' &&
            $request[3]['filename'] === 'photo.jpg';
    });
});

test('it can send token', function () {
    $this->factory->fake();

    $this->factory->withToken('token')->post('http://foo.com/json');

    $this->factory->assertSent(function (Request $request) {
        return $request->url() === 'http://foo.com/json' &&
            $request->hasHeader('Authorization', 'Bearer token');
    });
});

test('it can send user agent', function () {
    $this->factory->fake();

    $this->factory->withUserAgent('Laravel')->post('http://foo.com/json');

    $this->factory->assertSent(function (Request $request) {
        return $request->url() === 'http://foo.com/json' &&
            $request->hasHeader('User-Agent', 'Laravel');
    });
});

test('it only sends one user agent header', function () {
    $this->factory->fake();

    $this->factory->withUserAgent('Laravel')
        ->withUserAgent('FooBar')
        ->post('http://foo.com/json');

    $this->factory->assertSent(function (Request $request) {
        $userAgent = $request->header('User-Agent');

        return $request->url() === 'http://foo.com/json' &&
            count($userAgent) === 1 &&
            $userAgent[0] === 'FooBar';
    });
});

test('sequence builder', function () {
    $this->factory->fake([
        '*' => $this->factory->sequence()
            ->push('Ok', 201)
            ->push(['fact' => 'Cats are great!'])
            ->pushFile(__DIR__.'/fixtures/test.txt')
            ->pushStatus(403),
    ]);

    $response = $this->factory->get('https://example.com');
    expect($response->body())->toBe('Ok');
    expect($response->status())->toBe(201);

    $response = $this->factory->get('https://example.com');
    expect($response->json())->toBe(['fact' => 'Cats are great!']);
    expect($response->header('Content-Type'))->toBe('application/json');
    expect($response->status())->toBe(200);

    $response = $this->factory->get('https://example.com');
    expect(str_replace("\r\n", "\n", $response->body()))->toBe("This is a story about something that happened long ago when your grandfather was a child.\n");
    expect($response->status())->toBe(200);

    $response = $this->factory->get('https://example.com');
    expect($response->body())->toBe('');
    expect($response->status())->toBe(403);

    // The sequence is empty, it should throw an exception.
    $this->factory->get('https://example.com');
})->throws(OutOfBoundsException::class);

test('sequence builder can keep going when empty', function () {
    $this->factory->fake([
        '*' => $this->factory->sequence()
            ->dontFailWhenEmpty()
            ->push('Ok'),
    ]);

    $response = $this->factory->get('https://laravel.com');
    expect($response->body())->toBe('Ok');

    // The sequence is empty, but it should not fail.
    $this->factory->get('https://laravel.com');
});

test('assert sequences are empty', function () {
    $this->factory->fake([
        '*' => $this->factory->sequence()
            ->push('1')
            ->push('2'),
    ]);

    $this->factory->get('https://example.com');
    $this->factory->get('https://example.com');

    $this->factory->assertSequencesAreEmpty();
});

test('fake sequence', function () {
    $this->factory->fakeSequence()
        ->pushStatus(201)
        ->pushStatus(301);

    expect($this->factory->get('https://example.com')->status())->toBe(201);
    expect($this->factory->get('https://example.com')->status())->toBe(301);
});

test('with cookies', function () {
    $this->factory->fakeSequence()->pushStatus(200);

    $response = $this->factory->withCookies(
        ['foo' => 'bar'], 'https://laravel.com'
    )->get('https://laravel.com');

    expect($response->cookies()->toArray())->toHaveCount(1);

    /** @var \GuzzleHttp\Cookie\CookieJarInterface $responseCookies */
    $responseCookie = $response->cookies()->toArray()[0];

    expect($responseCookie['Name'])->toBe('foo');
    expect($responseCookie['Value'])->toBe('bar');
    expect($responseCookie['Domain'])->toBe('https://laravel.com');
});

test('with query parameters', function () {
    $this->factory->fake();

    $this->factory->withQueryParameters(
        ['foo' => 'bar']
    )->get('https://laravel.com');

    $this->factory->assertSent(function (Request $request) {
        return $request->url() === 'https://laravel.com?foo=bar';
    });
});

test('with array query parameters', function () {
    $this->factory->fake();

    $this->factory->withQueryParameters(
        ['foo' => ['bar', 'baz']],
    )->get('https://laravel.com');

    $this->factory->assertSent(function (Request $request) {
        return $request->url() === 'https://laravel.com?foo%5B0%5D=bar&foo%5B1%5D=baz';
    });
});

test('with query parameters allows adding more on request', function () {
    $this->factory->fake();

    $this->factory->withQueryParameters(
        ['foo' => 'bar']
    )->get('https://laravel.com', [
        'baz' => 'qux',
    ]);

    $this->factory->assertSent(function (Request $request) {
        return $request->url() === 'https://laravel.com?foo=bar&baz=qux';
    });
});

test('with query parameters allows overriding parameter on request', function () {
    $this->factory->fake();

    $this->factory->withQueryParameters([
        'foo' => 'bar',
        'baz' => 'baz',
    ])->get('https://laravel.com', [
        // Override the previously set value
        'baz' => 'qux',
    ]);

    $this->factory->assertSent(function (Request $request) {
        return $request->url() === 'https://laravel.com?foo=bar&baz=qux';
    });
});

test('with stringable query parameters', function () {
    $this->factory->fake();

    $this->factory->withQueryParameters(
        ['foo' => new Stringable('bar')]
    )->get('https://laravel.com');

    $this->factory->assertSent(function (Request $request) {
        return $request->url() === 'https://laravel.com?foo=bar';
    });
});

test('with array stringable query parameters', function () {
    $this->factory->fake();

    $this->factory->withQueryParameters(
        ['foo' => ['bar', new Stringable('baz')]],
    )->get('https://laravel.com');

    $this->factory->assertSent(function (Request $request) {
        return $request->url() === 'https://laravel.com?foo%5B0%5D=bar&foo%5B1%5D=baz';
    });
});

test('get with array query param', function () {
    $this->factory->fake();

    $this->factory->get('http://foo.com/get', ['foo' => 'bar']);

    $this->factory->assertSent(function (Request $request) {
        return $request->url() === 'http://foo.com/get?foo=bar'
            && $request['foo'] === 'bar';
    });
});

test('get with arrayable query param', function () {
    $this->factory->fake();

    $this->factory->get('http://foo.com/get', new Fluent(['foo' => 'bar']));

    $this->factory->assertSent(function (Request $request) {
        return $request->url() === 'http://foo.com/get?foo=bar'
            && $request['foo'] === 'bar';
    });
});

test('get with string query param', function () {
    $this->factory->fake();

    $this->factory->get('http://foo.com/get', 'foo=bar');

    $this->factory->assertSent(function (Request $request) {
        return $request->url() === 'http://foo.com/get?foo=bar'
            && $request['foo'] === 'bar';
    });
});

test('get with query', function () {
    $this->factory->fake();

    $this->factory->get('http://foo.com/get?foo=bar&page=1');

    $this->factory->assertSent(function (Request $request) {
        return $request->url() === 'http://foo.com/get?foo=bar&page=1'
            && $request['foo'] === 'bar'
            && $request['page'] === '1';
    });
});

test('get with query wont encode', function () {
    $this->factory->fake();

    $this->factory->get('http://foo.com/get?foo;bar;1;5;10&page=1');

    $this->factory->assertSent(function (Request $request) {
        return $request->url() === 'http://foo.com/get?foo;bar;1;5;10&page=1'
            && ! isset($request['foo'])
            && ! isset($request['bar'])
            && $request['page'] === '1';
    });
});

test('get with array query param overwrites', function () {
    $this->factory->fake();

    $this->factory->get('http://foo.com/get?foo=bar&page=1', ['hello' => 'world']);

    $this->factory->assertSent(function (Request $request) {
        return $request->url() === 'http://foo.com/get?hello=world'
            && $request['hello'] === 'world';
    });
});

test('get with array query param encodes', function () {
    $this->factory->fake();

    $this->factory->get('http://foo.com/get', ['foo;bar; space test' => 'laravel']);

    $this->factory->assertSent(function (Request $request) {
        return $request->url() === 'http://foo.com/get?foo%3Bbar%3B%20space%20test=laravel'
            && $request['foo;bar; space test'] === 'laravel';
    });
});

test('with base url', function () {
    $this->factory->fake();

    $this->factory->baseUrl('http://foo.com/')->get('get');

    $this->factory->assertSent(function (Request $request) {
        return $request->url() === 'http://foo.com/get';
    });

    $this->factory->fake();

    $this->factory->baseUrl('http://foo.com/')->get('http://bar.com/get');

    $this->factory->assertSent(function (Request $request) {
        return $request->url() === 'http://bar.com/get';
    });
});

test('can confirm many headers', function () {
    $this->factory->fake();

    $this->factory->withHeaders([
        'X-Test-Header' => 'foo',
        'X-Test-ArrayHeader' => ['bar', 'baz'],
    ])->post('http://foo.com/json');

    $this->factory->assertSent(function (Request $request) {
        return $request->url() === 'http://foo.com/json' &&
               $request->hasHeaders([
                   'X-Test-Header' => 'foo',
                   'X-Test-ArrayHeader' => ['bar', 'baz'],
               ]);
    });
});

test('can confirm many headers using a string', function () {
    $this->factory->fake();

    $this->factory->withHeaders([
        'X-Test-Header' => 'foo',
        'X-Test-ArrayHeader' => ['bar', 'baz'],
    ])->post('http://foo.com/json');

    $this->factory->assertSent(function (Request $request) {
        return $request->url() === 'http://foo.com/json' &&
               $request->hasHeaders('X-Test-Header');
    });
});

test('it merges multiple headers', function () {
    $this->factory->fake();

    $this->factory->withHeaders([
        'X-Test-Header' => 'foo',
    ])->withHeaders([
        'X-Test-Header' => 'bar',
    ])->withHeaders([
        'X-Test-Header' => ['baz', 'qux'],
    ])->post('http://foo.com/json');

    $this->factory->assertSent(function (Request $request) {
        return $request->url() === 'http://foo.com/json' &&
               $request->hasHeaders(['X-Test-Header' => ['foo', 'bar', 'baz', 'qux']]);
    });
});

test('it can replace headers', function () {
    $this->factory->fake();

    $this->factory->withHeaders([
        'X-Test-Header' => 'foo',
    ])->replaceHeaders([
        'X-Test-Header' => 'baz',
    ])->post('http://foo.com/json');

    $this->factory->assertSent(function (Request $request) {
        return $request->url() === 'http://foo.com/json' &&
               $request->hasHeaders(['X-Test-Header' => ['baz']]);
    });
});

test('it can replace headers when no headers yet set', function () {
    $this->factory->fake();

    $this->factory->replaceHeaders([
        'X-Test-Header' => 'baz',
    ])->post('http://foo.com/json');

    $this->factory->assertSent(function (Request $request) {
        return $request->url() === 'http://foo.com/json' &&
               $request->hasHeaders(['X-Test-Header' => ['baz']]);
    });
});

test('can confirm single string header', function () {
    $this->factory->fake();

    $this->factory->withHeader('X-Test-Header', 'foo')->post('http://foo.com/json');

    $this->factory->assertSent(function (Request $request) {
        return $request->url() === 'http://foo.com/json' &&
            $request->hasHeaders([
                'X-Test-Header' => 'foo',
            ]);
    });
});

test('can confirm single array header', function () {
    $this->factory->fake();

    $this->factory->withHeader('X-Test-ArrayHeader', ['bar', 'baz'])->post('http://foo.com/json');

    $this->factory->assertSent(function (Request $request) {
        return $request->url() === 'http://foo.com/json' &&
            $request->hasHeaders([
                'X-Test-ArrayHeader' => ['bar', 'baz'],
            ]);
    });
});

test('exception accessor on success', function () {
    $resp = new Response(new Psr7Response());

    expect($resp->toException())->toBeNull();
});

test('exception accessor on failure', function () {
    $error = [
        'error' => [
            'code' => 403,
            'message' => 'The Request can not be completed',
        ],
    ];
    $response = new Psr7Response(403, [], json_encode($error));
    $resp = new Response($response);

    expect($resp->toException())->toBeInstanceOf(RequestException::class);
});

test('request exception summary', function () {
    $error = [
        'error' => [
            'code' => 403,
            'message' => 'The Request can not be completed',
        ],
    ];

    $response = new Psr7Response(403, [], json_encode($error));

    throw tap(new RequestException(new Response($response)), fn ($exception) => $exception->report());
})->throws(RequestException::class, '{"error":{"code":403,"message":"The Request can not be completed"}}');

test('request exception truncated summary', function () {
    $error = [
        'error' => [
            'code' => 403,
            'message' => 'The Request can not be completed because quota limit was exceeded. Please, check our support team to increase your limit',
        ],
    ];
    $response = new Psr7Response(403, [], json_encode($error));

    throw tap(new RequestException(new Response($response)), fn ($exception) => $exception->report());
})->throws(RequestException::class, '{"error":{"code":403,"message":"The Request can not be completed because quota limit was exceeded. Please, check our sup (truncated...)');

test('request exception without truncated summary', function () {
    RequestException::dontTruncate();

    $error = [
        'error' => [
            'code' => 403,
            'message' => 'The Request can not be completed because quota limit was exceeded. Please, check our support team to increase your limit',
        ],
    ];
    $response = new Psr7Response(403, [], json_encode($error));

    throw tap(new RequestException(new Response($response)), fn ($exception) => $exception->report());
})->throws(RequestException::class, '{"error":{"code":403,"message":"The Request can not be completed because quota limit was exceeded. Please, check our support team to increase your limit');

test('request exception with custom truncated summary', function () {
    RequestException::truncateAt(60);

    $error = [
        'error' => [
            'code' => 403,
            'message' => 'The Request can not be completed because quota limit was exceeded. Please, check our support team to increase your limit',
        ],
    ];
    $response = new Psr7Response(403, [], json_encode($error));

    throw tap(new RequestException(new Response($response)), fn ($exception) => $exception->report());
})->throws(RequestException::class, '{"error":{"code":403,"message":"The Request can not be compl (truncated...)');

test('request level truncation level on request exception', function () {
    RequestException::truncateAt(60);

    $this->factory->fake([
        '*' => $this->factory->response(['error'], 403),
    ]);

    $exception = null;
    try {
        $this->factory->throw()->truncateExceptionsAt(3)->get('http://foo.com/json');
    } catch (RequestException $e) {
        $exception = $e;
    }

    // Ensure the exception message is truncated according to the request level truncation setting.
    expect($exception->getMessage())->toEqual("HTTP request returned status code 403:\n[\"e (truncated...)\n");

    $exception->report();

    // Ensure that the truncation level is not changed when reporting the exception.
    expect($exception->getMessage())->toEqual("HTTP request returned status code 403:\n[\"e (truncated...)\n");

    expect(RequestException::$truncateAt)->toEqual(60);
});

test('no truncation on request level', function () {
    RequestException::truncateAt(60);

    $this->factory->fake([
        '*' => $this->factory->response(['error'], 403),
    ]);

    $exception = null;

    try {
        $this->factory->throw()->dontTruncateExceptions()->get('http://foo.com/json');
    } catch (RequestException $e) {
        $exception = $e;
    }

    $exception->report();

    expect($exception->getMessage())->toEqual("HTTP request returned status code 403:\nHTTP/1.1 403 Forbidden\r\nContent-Type: application/json\r\n\r\n[\"error\"]\n");

    expect(RequestException::$truncateAt)->toEqual(60);
});

test('request exception does not truncate but request does', function () {
    RequestException::dontTruncate();

    $this->factory->fake([
        '*' => $this->factory->response(['error'], 403),
    ]);

    $exception = null;
    try {
        $this->factory->throw()->truncateExceptionsAt(3)->get('http://foo.com/json');
    } catch (RequestException $e) {
        $exception = $e;
    }

    $exception->report();

    expect($exception->getMessage())->toEqual("HTTP request returned status code 403:\n[\"e (truncated...)\n");

    expect(RequestException::$truncateAt)->toBeFalse();
});

test('async request exceptions respect request truncation', function () {
    RequestException::dontTruncate();
    $this->factory->fake([
        '*' => $this->factory->response(['error'], 403),
    ]);

    $exception = $this->factory->async()->throw()->truncateExceptionsAt(4)->get('http://foo.com/json')->wait();

    $exception->report();

    expect($exception)->toBeInstanceOf(RequestException::class);
    expect($exception->getMessage())->toEqual("HTTP request returned status code 403:\n[\"er (truncated...)\n");
    expect(RequestException::$truncateAt)->toBeFalse();
});

test('request exception empty body', function () {
    $this->expectException(RequestException::class);
    $this->expectExceptionMessageMatches('/HTTP request returned status code 403$/');

    $response = new Psr7Response(403);

    throw new RequestException(new Response($response));
});

test('reporting exception twice does not include summary twice', function () {
    RequestException::dontTruncate();

    $error = [
        'error' => [
            'code' => 403,
            'message' => 'The Request can not be completed',
        ],
    ];

    $response = new Psr7Response(403, [], json_encode($error));

    $exception = new RequestException(new Response($response));
    $exception->report();
    $exception->report();

    expect(substr_count($exception->getMessage(), '{"error":{"code":403,"message":"The Request can not be completed"}}'))->toEqual(1);
});

test('streaming response exception message is not summarized when body is not seekable', function (int|false $truncateAt) {
    RequestException::$truncateAt = $truncateAt;

    $this->factory->fake([
        '*' => Create::promiseFor(
            new Psr7Response(
                400,
                ['Content-Type' => 'application/json'],
                new NoSeekStream(Utils::streamFor(json_encode(['hello' => 'world'])))
            )
        ),
    ]);

    $throwCallbackCalled = false;

    try {
        $this->factory
            ->withOptions(['stream' => true])
            ->throw(function (Response $response, RequestException $exception) use (&$throwCallbackCalled) {
                $throwCallbackCalled = true;

                expect($response->json())->not->toBeNull();
                expect($exception->getMessage())->toBe('HTTP request returned status code 400');
            })
            ->get('http://example.com');

        $this->fail('RequestException was not thrown.');
    } catch (RequestException $exception) {
        expect($exception->getMessage())->toBe('HTTP request returned status code 400');
    }

    expect($throwCallbackCalled)->toBeTrue();
})->with([[false], [120]]);

test('on error doesnt call closure on informational', function () {
    $status = 0;
    $client = $this->factory->fake([
        'laravel.com' => $this->factory::response('', 101),
    ]);

    $response = $client->get('laravel.com')
        ->onError(function ($response) use (&$status) {
            $status = $response->status();
        });

    expect($status)->toBe(0);
    expect($response->status())->toBe(101);
});

test('on error doesnt call closure on success', function () {
    $status = 0;
    $client = $this->factory->fake([
        'laravel.com' => $this->factory::response('', 201),
    ]);

    $response = $client->get('laravel.com')
        ->onError(function ($response) use (&$status) {
            $status = $response->status();
        });

    expect($status)->toBe(0);
    expect($response->status())->toBe(201);
});

test('on error doesnt call closure on redirection', function () {
    $status = 0;
    $client = $this->factory->fake([
        'laravel.com' => $this->factory::response('', 301),
    ]);

    $response = $client->get('laravel.com')
        ->onError(function ($response) use (&$status) {
            $status = $response->status();
        });

    expect($status)->toBe(0);
    expect($response->status())->toBe(301);
});

test('on error calls closure on client error', function () {
    $status = 0;
    $client = $this->factory->fake([
        'laravel.com' => $this->factory::response('', 401),
    ]);

    $response = $client->get('laravel.com')
        ->onError(function ($response) use (&$status) {
            $status = $response->status();
        });

    expect($status)->toBe(401);
    expect($response->status())->toBe(401);
});

test('on error calls closure on server error', function () {
    $status = 0;
    $client = $this->factory->fake([
        'laravel.com' => $this->factory::response('', 501),
    ]);

    $response = $client->get('laravel.com')
        ->onError(function ($response) use (&$status) {
            $status = $response->status();
        });

    expect($status)->toBe(501);
    expect($response->status())->toBe(501);
});

test('sink to file', function () {
    $this->factory->fakeSequence()->push('abc123');

    $destination = __DIR__.'/fixtures/sunk.txt';

    if (file_exists($destination)) {
        unlink($destination);
    }

    $this->factory->withOptions(['sink' => $destination])->get('https://example.com');

    expect($destination)->toBeFile();
    expect(file_get_contents($destination))->toBe('abc123');

    unlink($destination);
});

test('sink to resource', function () {
    $this->factory->fakeSequence()->push('abc123');

    $resource = fopen('php://temp', 'w');

    $this->factory->sink($resource)->get('https://example.com');

    expect(ftell($resource))->toBe(0);
    expect(stream_get_contents($resource))->toBe('abc123');
});

test('sink when stubbed by path', function () {
    $this->factory->fake([
        'foo.com/*' => ['page' => 'foo'],
    ]);

    $resource = fopen('php://temp', 'w');

    $this->factory->sink($resource)->get('http://foo.com/test');

    expect(stream_get_contents($resource))->toBe(json_encode(['page' => 'foo']));
});

test('can assert against order of http requests with url strings', function () {
    $this->factory->fake();

    $exampleUrls = [
        'http://example.com/1',
        'http://example.com/2',
        'http://example.com/3',
    ];

    foreach ($exampleUrls as $url) {
        $this->factory->get($url);
    }

    $this->factory->assertSentInOrder($exampleUrls);
});

test('assertions sent out of order throw assertion failed', function () {
    $this->factory->fake();

    $exampleUrls = [
        'http://example.com/1',
        'http://example.com/2',
        'http://example.com/3',
    ];

    $this->factory->get($exampleUrls[0]);
    $this->factory->get($exampleUrls[2]);
    $this->factory->get($exampleUrls[1]);

    $this->factory->assertSentInOrder($exampleUrls);
})->throws(AssertionFailedError::class);

test('wrong number of requests throw assertion failed', function () {
    $this->factory->fake();

    $exampleUrls = [
        'http://example.com/1',
        'http://example.com/2',
        'http://example.com/3',
    ];

    $this->factory->get($exampleUrls[0]);
    $this->factory->get($exampleUrls[1]);

    $this->factory->assertSentInOrder($exampleUrls);
})->throws(AssertionFailedError::class);

test('can assert against order of http requests with callables', function () {
    $this->factory->fake();

    $exampleUrls = [
        function ($request) {
            return $request->url() === 'http://example.com/1';
        },
        function ($request) {
            return $request->url() === 'http://example.com/2';
        },
        function ($request) {
            return $request->url() === 'http://example.com/3';
        },
    ];

    $this->factory->get('http://example.com/1');
    $this->factory->get('http://example.com/2');
    $this->factory->get('http://example.com/3');

    $this->factory->assertSentInOrder($exampleUrls);
});

test('can assert against order of http requests with callables and headers', function () {
    $this->factory->fake();

    $executionOrder = [
        function (Request $request) {
            return $request->url() === 'http://foo.com/json' &&
                   $request->hasHeader('Content-Type', 'application/json') &&
                   $request->hasHeader('X-Test-Header', 'foo') &&
                   $request->hasHeader('X-Test-ArrayHeader', ['bar', 'baz']) &&
                   $request['name'] === 'Taylor';
        },
        function (Request $request) {
            return $request->url() === 'http://bar.com/json' &&
                   $request->hasHeader('Content-Type', 'application/json') &&
                   $request->hasHeader('X-Test-Header', 'bar') &&
                   $request->hasHeader('X-Test-ArrayHeader', ['bar', 'baz']) &&
                   $request['name'] === 'Taylor';
        },
    ];

    $this->factory->withHeaders([
        'X-Test-Header' => 'foo',
        'X-Test-ArrayHeader' => ['bar', 'baz'],
    ])->post('http://foo.com/json', [
        'name' => 'Taylor',
    ]);

    $this->factory->withHeaders([
        'X-Test-Header' => 'bar',
        'X-Test-ArrayHeader' => ['bar', 'baz'],
    ])->post('http://bar.com/json', [
        'name' => 'Taylor',
    ]);

    $this->factory->assertSentInOrder($executionOrder);
});

test('can assert against order of http requests with callables and headers fails correctly', function () {
    $this->factory->fake();

    $executionOrder = [
        function (Request $request) {
            return $request->url() === 'http://bar.com/json' &&
                   $request->hasHeader('Content-Type', 'application/json') &&
                   $request->hasHeader('X-Test-Header', 'bar') &&
                   $request->hasHeader('X-Test-ArrayHeader', ['bar', 'baz']) &&
                   $request['name'] === 'Taylor';
        },
        function (Request $request) {
            return $request->url() === 'http://foo.com/json' &&
                   $request->hasHeader('Content-Type', 'application/json') &&
                   $request->hasHeader('X-Test-Header', 'foo') &&
                   $request->hasHeader('X-Test-ArrayHeader', ['bar', 'baz']) &&
                   $request['name'] === 'Taylor';
        },
    ];

    $this->factory->withHeaders([
        'X-Test-Header' => 'foo',
        'X-Test-ArrayHeader' => ['bar', 'baz'],
    ])->post('http://foo.com/json', [
        'name' => 'Taylor',
    ]);

    $this->factory->withHeaders([
        'X-Test-Header' => 'bar',
        'X-Test-ArrayHeader' => ['bar', 'baz'],
    ])->post('http://bar.com/json', [
        'name' => 'Taylor',
    ]);

    $this->factory->assertSentInOrder($executionOrder);
})->throws(AssertionFailedError::class);

test('can dump', function () {
    $dumped = [];

    VarDumper::setHandler(function ($value) use (&$dumped) {
        $dumped[] = $value;
    });

    $this->factory->fake()->dump(1, 2, 3)->withOptions(['delay' => 1000])->get('http://foo.com');

    expect($dumped[0])->toBe(1);
    expect($dumped[1])->toBe(2);
    expect($dumped[2])->toBe(3);
    expect($dumped[3])->toBeInstanceOf(Request::class);
    expect($dumped[4]['delay'])->toBe(1000);

    VarDumper::setHandler(null);
});

test('response can dump', function () {
    $dumped = [];

    VarDumper::setHandler(function ($value) use (&$dumped) {
        $dumped[] = $value;
    });

    $this->factory->fake([
        '200.com' => $this->factory::response('hello', 200),
    ]);

    $this->factory->get('http://200.com')->dump();

    expect($dumped[0])->toBe('"GET http://200.com" 200');
    expect($dumped[1])->toBe('hello');

    VarDumper::setHandler(null);
});

test('response can dump with key', function () {
    $dumped = [];

    VarDumper::setHandler(function ($value) use (&$dumped) {
        $dumped[] = $value;
    });

    $this->factory->fake([
        '200.com' => $this->factory::response(['hello' => 'world'], 200),
    ]);

    $this->factory->get('http://200.com')->dump('hello');

    expect($dumped[0])->toBe('"GET http://200.com" 200');
    expect($dumped[1])->toBe('world');

    VarDumper::setHandler(null);
});

test('response can dump headers', function () {
    $dumped = [];

    VarDumper::setHandler(function ($value) use (&$dumped) {
        $dumped[] = $value;
    });

    $this->factory->fake([
        '200.com' => $this->factory::response('hello', 200, ['hello' => 'world']),
    ]);

    $this->factory->get('http://200.com')->dumpHeaders();

    expect($dumped[0])->toBe(['hello' => ['world']]);

    VarDumper::setHandler(null);
});

test('response sequence is macroable', function () {
    ResponseSequence::macro('customMethod', function () {
        return 'yes!';
    });

    expect($this->factory->fakeSequence()->customMethod())->toBe('yes!');
});

test('requests can be async', function () {
    $request = new PendingRequest($this->factory);

    $promise = $request->async()->get('http://foo.com');

    expect($promise)->toBeInstanceOf(PromiseInterface::class);

    expect($request->getPromise())->toBe($promise);
});

test('client can be set', function () {
    $client = $this->factory->buildClient();

    $request = new PendingRequest($this->factory);

    expect($request->buildClient())->not->toBe($client);

    $request->setClient($client);

    expect($request->buildClient())->toBe($client);
});

test('requests can replace options', function () {
    $request = new PendingRequest($this->factory);

    $request = $request->withOptions(['http_errors' => true, 'connect_timeout' => 10]);

    expect($request->getOptions())->toBe(['connect_timeout' => 10, 'crypto_method' => 33, 'http_errors' => true, 'timeout' => 30]);

    $request = $request->withOptions(['connect_timeout' => 20]);

    expect($request->getOptions())->toBe(['connect_timeout' => 20, 'crypto_method' => 33, 'http_errors' => true, 'timeout' => 30]);
});

test('global configuration can be disabled for requests created within callback', function () {
    $this->factory->fake();
    $this->factory->globalOptions(['force_ip_resolve' => 'v4']);
    $this->factory->globalRequestMiddleware(fn ($request) => $request->withHeader('X-Global', 'Foo'));

    $request = $this->factory->withoutGlobalConfiguration(fn () => $this->factory->createPendingRequest());
    $request->get('http://laravel.com/agent');

    $this->factory->createPendingRequest()->get('http://laravel.com/global');

    expect($request->getOptions())->not->toHaveKey('force_ip_resolve');
    $this->factory->assertSent(fn (Request $request) => $request->url() === 'http://laravel.com/agent' && ! $request->hasHeader('X-Global'));
    $this->factory->assertSent(fn (Request $request) => $request->url() === 'http://laravel.com/global' && $request->hasHeader('X-Global'));
});

test('global configuration is restored after without global configuration callback', function () {
    $middleware = fn ($handler) => $handler;

    $this->factory->globalOptions(['force_ip_resolve' => 'v4']);
    $this->factory->globalMiddleware($middleware);

    try {
        $this->factory->withoutGlobalConfiguration(function () {
            throw new Exception('boom');
        });
    } catch (Exception) {
        //
    }

    expect($this->factory->createPendingRequest()->getOptions()['force_ip_resolve'])->toBe('v4');
    expect($this->factory->getGlobalMiddleware())->toBe([$middleware]);
});

test('multiple requests are sent in the pool', function () {
    $this->factory->fake([
        '200.com' => $this->factory::response('', 200),
        '400.com' => $this->factory::response('', 400),
        '500.com' => $this->factory::response('', 500),
    ]);

    $responses = $this->factory->pool(function (Pool $pool) {
        return [
            $pool->get('200.com'),
            $pool->get('400.com'),
            $pool->get('500.com'),
        ];
    });

    expect($responses[0]->status())->toBe(200);
    expect($responses[1]->status())->toBe(400);
    expect($responses[2]->status())->toBe(500);
});

test('multiple requests are sent in the pool with keys', function () {
    $this->factory->fake([
        '200.com' => $this->factory::response('', 200),
        '400.com' => $this->factory::response('', 400),
        '500.com' => $this->factory::response('', 500),
    ]);

    $responses = $this->factory->pool(function (Pool $pool) {
        return [
            $pool->as('test200')->get('200.com'),
            $pool->as('test400')->get('400.com'),
            $pool->as('test500')->get('500.com'),
            $pool->newRequest()->get('200.com'),
        ];
    });

    expect($responses['test200']->status())->toBe(200);
    expect($responses[0]->status())->toBe(200);
    expect($responses['test400']->status())->toBe(400);
    expect($responses['test500']->status())->toBe(500);
});

test('middleware runs in pool', function () {
    $this->factory->fake(function (Request $request) {
        return $this->factory->response('Fake');
    });

    $history = [];

    $middleware = Middleware::history($history);

    $responses = $this->factory->pool(fn (Pool $pool) => [
        $pool->withMiddleware($middleware)->post('https://example.com', ['hyped-for' => 'laravel-movie']),
    ]);

    $response = $responses[0];

    expect($response->body())->toBe('Fake');

    expect($history)->toHaveCount(1);

    expect(tap($history[0]['response']->getBody())->rewind()->getContents())->toBe('Fake');

    expect(json_decode(tap($history[0]['request']->getBody())->rewind()->getContents(), true))->toBe(['hyped-for' => 'laravel-movie']);
});

test('pool concurrency', function () {
    $this->factory->fake([
        '200.com' => $this->factory::response('', 200),
        '400.com' => $this->factory::response('', 400),
        '500.com' => $this->factory::response('', 500),
    ]);

    $responses = $this->factory->pool(function (Pool $pool) {
        return [
            $pool->get('200.com'),
            $pool->get('400.com'),
            $pool->get('500.com'),
        ];
    }, 2);

    expect($responses[0]->status())->toBe(200);
    expect($responses[1]->status())->toBe(400);
    expect($responses[2]->status())->toBe(500);
});

test('the request sending and response received events are fired when a request is sent', function () {
    $events = m::mock(Dispatcher::class);
    $events->shouldReceive('dispatch')->times(5)->with(m::type(RequestSending::class));
    $events->shouldReceive('dispatch')->times(5)->with(m::type(ResponseReceived::class));

    $factory = new Factory($events);
    $factory->fake();

    $factory->get('https://example.com');
    $factory->head('https://example.com');
    $factory->post('https://example.com');
    $factory->patch('https://example.com');
    $factory->delete('https://example.com');
});

test('the request sending and response received events are fired when a request is sent async', function () {
    $events = m::mock(Dispatcher::class);
    $events->shouldReceive('dispatch')->times(5)->with(m::type(RequestSending::class));
    $events->shouldReceive('dispatch')->times(5)->with(m::type(ResponseReceived::class));

    $factory = new Factory($events);
    $factory->fake();
    $factory->pool(function (Pool $pool) {
        return [
            $pool->get('https://example.com'),
            $pool->head('https://example.com'),
            $pool->post('https://example.com'),
            $pool->patch('https://example.com'),
            $pool->delete('https://example.com'),
        ];
    });
});

test('the request sending and response received events are fired for every retry', function () {
    $events = m::mock(Dispatcher::class);
    $events->shouldReceive('dispatch')->times(2)->with(m::type(RequestSending::class));
    $events->shouldReceive('dispatch')->times(2)->with(m::type(ResponseReceived::class));

    $factory = new Factory($events);
    $factory->fake([
        '*' => $factory->response(['error'], 403),
    ]);

    $response = $factory->retry(2, 1000, null, false)->get('http://foo.com/get');

    expect($response->failed())->toBeTrue();

    $factory->assertSentCount(2);
});

test('the transfer stats are called safely when faking the request', function () {
    $this->factory->fake(['https://example.com' => ['world' => 'Hello world']]);
    $stats = $this->factory->get('https://example.com')->handlerStats();
    $effectiveUri = $this->factory->get('https://example.com')->effectiveUri();

    expect($stats)->toBeArray();
    expect($stats)->toBeEmpty();

    expect($effectiveUri)->toBeNull();
});

test('transfer stats are present when faking the request using a promise response', function () {
    $this->factory->fake(['https://example.com' => $this->factory->response()]);
    $effectiveUri = $this->factory->get('https://example.com')->effectiveUri();

    expect((string) $effectiveUri)->toBe('https://example.com');
});

test('cloned clients work successfully with the request object', function () {
    $events = m::mock(Dispatcher::class);
    $events->shouldReceive('dispatch')->once()->with(m::type(RequestSending::class));
    $events->shouldReceive('dispatch')->once()->with(m::type(ResponseReceived::class));

    $factory = new Factory($events);
    $factory->fake(['example.com' => $factory->response('foo', 200)]);

    $client = $factory->timeout(10);
    $clonedClient = clone $client;

    $clonedClient->get('https://example.com');
});

test('request is macroable', function () {
    Request::macro('customMethod', function () {
        return 'yes!';
    });

    $this->factory->fake(function (Request $request) {
        expect($request->customMethod())->toBe('yes!');

        return $this->factory->response();
    });

    $this->factory->get('https://example.com');
});

test('request exception is thrown when retries exhausted', function () {
    $this->factory->fake([
        '*' => $this->factory->response(['error'], 403),
    ]);

    $exception = null;

    try {
        $this->factory
            ->retry(2, 1000, null, true)
            ->get('http://foo.com/get');
    } catch (RequestException $e) {
        $exception = $e;
    }

    expect($exception)->not->toBeNull();
    expect($exception)->toBeInstanceOf(RequestException::class);

    $this->factory->assertSentCount(2);
});

test('request exception is thrown when retries exhausted with backoff array', function () {
    $this->factory->fake([
        '*' => $this->factory->response(['error'], 403),
    ]);

    $exception = null;

    try {
        $this->factory
            ->retry([1], 0, null, true)
            ->get('http://foo.com/get');
    } catch (RequestException $e) {
        $exception = $e;
    }

    expect($exception)->not->toBeNull();
    expect($exception)->toBeInstanceOf(RequestException::class);

    $this->factory->assertSentCount(2);
});

test('request exception is thrown without retries if retry not necessary', function () {
    $this->factory->fake([
        '*' => $this->factory->response(['error'], 500),
    ]);

    $exception = null;
    $whenAttempts = 0;

    try {
        $this->factory
            ->retry(2, 1000, function ($exception) use (&$whenAttempts) {
                $whenAttempts++;

                return $exception->response->status() === 403;
            }, true)
            ->get('http://foo.com/get');
    } catch (RequestException $e) {
        $exception = $e;
    }

    expect($exception)->not->toBeNull();
    expect($exception)->toBeInstanceOf(RequestException::class);

    expect($whenAttempts)->toBe(1);

    $this->factory->assertSentCount(1);
});

test('request exception is thrown without retries if retry not necessary with backoff array', function () {
    $this->factory->fake([
        '*' => $this->factory->response(['error'], 500),
    ]);

    $exception = null;
    $whenAttempts = 0;

    try {
        $this->factory
            ->retry([1000, 1000], 1000, function ($exception) use (&$whenAttempts) {
                $whenAttempts++;

                return $exception->response->status() === 403;
            }, true)
            ->get('http://foo.com/get');
    } catch (RequestException $e) {
        $exception = $e;
    }

    expect($exception)->not->toBeNull();
    expect($exception)->toBeInstanceOf(RequestException::class);

    expect($whenAttempts)->toBe(1);

    $this->factory->assertSentCount(1);
});

test('request exception is not thrown when disabled and retries exhausted', function () {
    $this->factory->fake([
        '*' => $this->factory->response(['error'], 403),
    ]);

    $response = $this->factory
        ->retry(2, 1000, null, false)
        ->get('http://foo.com/get');

    expect($response->failed())->toBeTrue();

    $this->factory->assertSentCount(2);
});

test('request exception is not thrown when disabled and retries exhausted with backoff array', function () {
    $this->factory->fake([
        '*' => $this->factory->response(['error'], 403),
    ]);

    $response = $this->factory
        ->retry([1, 2], throw: false)
        ->get('http://foo.com/get');

    expect($response->failed())->toBeTrue();

    $this->factory->assertSentCount(3);
});

test('request exception is not thrown without retries if retry not necessary', function () {
    $this->factory->fake([
        '*' => $this->factory->response(['error'], 500),
    ]);

    $whenAttempts = 0;

    $response = $this->factory
        ->retry(2, 1000, function ($exception) use (&$whenAttempts) {
            $whenAttempts++;

            return $exception->response->status() === 403;
        }, false)
        ->get('http://foo.com/get');

    expect($response->failed())->toBeTrue();

    expect($whenAttempts)->toBe(1);

    $this->factory->assertSentCount(1);
});

test('request exception is not thrown without retries if retry not necessary with backoff array', function () {
    $this->factory->fake([
        '*' => $this->factory->response(['error'], 500),
    ]);

    $whenAttempts = 0;

    $response = $this->factory
        ->retry([1, 2], 0, function ($exception) use (&$whenAttempts) {
            $whenAttempts++;

            return $exception->response->status() === 403;
        }, false)
        ->get('http://foo.com/get');

    expect($response->failed())->toBeTrue();

    expect($whenAttempts)->toBe(1);

    $this->factory->assertSentCount(1);
});

test('request can be modified in retry callback', function () {
    $this->factory->fake([
        '*' => $this->factory->sequence()
            ->push(['error'], 500)
            ->push(['ok'], 200),
    ]);

    $response = $this->factory
        ->retry(2, 1000, function ($exception, $request) {
            expect($request)->toBeInstanceOf(PendingRequest::class);

            $request->withHeaders(['Foo' => 'Bar']);

            return true;
        }, false)
        ->get('http://foo.com/get');

    expect($response->successful())->toBeTrue();

    $this->factory->assertSent(function (Request $request) {
        return $request->hasHeader('Foo') && $request->header('Foo') === ['Bar'];
    });
});

test('request can be modified in retry callback with backoff array', function () {
    $this->factory->fake([
        '*' => $this->factory->sequence()
            ->push(['error'], 500)
            ->push(['ok'], 200),
    ]);

    $response = $this->factory
        ->retry([2], when: function ($exception, $request) {
            expect($request)->toBeInstanceOf(PendingRequest::class);

            $request->withHeaders(['Foo' => 'Bar']);

            return true;
        }, throw: false)
        ->get('http://foo.com/get');

    expect($response->successful())->toBeTrue();

    $this->factory->assertSent(function (Request $request) {
        return $request->hasHeader('Foo') && $request->header('Foo') === ['Bar'];
    });
});

test('exception thrown in retry callback without retrying', function () {
    $this->factory->fake([
        '*' => $this->factory->response(['error'], 500),
    ]);

    $exception = null;

    try {
        $this->factory
            ->retry(2, 1000, function ($exception) use (&$whenAttempts) {
                throw new Exception('Foo bar');
            }, false)
            ->get('http://foo.com/get');
    } catch (Exception $e) {
        $exception = $e;
    }

    expect($exception)->not->toBeNull();
    expect($exception)->toBeInstanceOf(Exception::class);
    expect($exception->getMessage())->toEqual('Foo bar');

    $this->factory->assertSentCount(1);
});

test('exception thrown in retry callback without retrying with backoff array', function () {
    $this->factory->fake([
        '*' => $this->factory->response(['error'], 500),
    ]);

    $exception = null;

    try {
        $this->factory
            ->retry([1, 2, 3], when: function ($exception) use (&$whenAttempts) {
                throw new Exception('Foo bar');
            }, throw: false)
            ->get('http://foo.com/get');
    } catch (Exception $e) {
        $exception = $e;
    }

    expect($exception)->not->toBeNull();
    expect($exception)->toBeInstanceOf(Exception::class);
    expect($exception->getMessage())->toEqual('Foo bar');

    $this->factory->assertSentCount(1);
});

test('requests will be waiting sleep milliseconds received before retry', function () {
    Sleep::fake();

    $this->factory->fake([
        '*' => $this->factory->sequence()
            ->push(['error'], 500)
            ->push(['error'], 500)
            ->push(['ok'], 200),
    ]);

    $this->factory
        ->retry(3, function ($attempt, $exception) {
            expect($exception)->toBeInstanceOf(RequestException::class);

            return $attempt * 100;
        }, null, true)
        ->get('http://foo.com/get');

    $this->factory->assertSentCount(3);

    // Make sure we waited 300ms for the first two attempts
    Sleep::assertSleptTimes(2);

    Sleep::assertSequence([
        Sleep::usleep(100_000),
        Sleep::usleep(200_000),
    ]);
});

test('request exception returned when retries exhausted in pool', function () {
    $this->factory->fake([
        '*' => $this->factory->response(['error'], 403),
    ]);

    [$exception] = $this->factory->pool(fn ($pool) => [
        $pool->retry(2, 1000, null, true)->get('http://foo.com/get'),
    ]);

    expect($exception)->not->toBeNull();
    expect($exception)->toBeInstanceOf(RequestException::class);

    $this->factory->assertSentCount(2);
});

test('request exception is returned without retries if retry not necessary in pool', function () {
    $this->factory->fake([
        '*' => $this->factory->response(['error'], 500),
    ]);

    $whenAttempts = collect();

    [$exception] = $this->factory->pool(fn ($pool) => [
        $pool->retry(2, 1000, function ($exception) use ($whenAttempts) {
            $whenAttempts->push($exception);

            return $exception->response->status() === 403;
        }, true)->get('http://foo.com/get'),
    ]);

    expect($exception)->not->toBeNull();
    expect($exception)->toBeInstanceOf(RequestException::class);

    expect($whenAttempts)->toHaveCount(1);

    $this->factory->assertSentCount(1);
});

test('request exception is not returned when disabled and retries exhausted in pool', function () {
    $this->factory->fake([
        '*' => $this->factory->response(['error'], 403),
    ]);

    [$response] = $this->factory->pool(fn ($pool) => [
        $pool->retry(2, 1000, null, false)->get('http://foo.com/get'),
    ]);

    expect($response)->not->toBeNull();
    expect($response)->toBeInstanceOf(Response::class);
    expect($response->failed())->toBeTrue();

    $this->factory->assertSentCount(2);
});

test('request exception is not returned without retries if retry not necessary in pool', function () {
    $this->factory->fake([
        '*' => $this->factory->response(['error'], 500),
    ]);

    $whenAttempts = collect();

    [$response] = $this->factory->pool(fn ($pool) => [
        $pool->retry(2, 1000, function ($exception) use ($whenAttempts) {
            $whenAttempts->push($exception);

            return $exception->response->status() === 403;
        }, false)->get('http://foo.com/get'),
    ]);

    expect($response)->not->toBeNull();
    expect($response)->toBeInstanceOf(Response::class);
    expect($response->failed())->toBeTrue();

    expect($whenAttempts)->toHaveCount(1);

    $this->factory->assertSentCount(1);
});

test('request can be modified in retry callback in pool', function () {
    $this->factory->fake([
        '*' => $this->factory->sequence()
            ->push(['error'], 500)
            ->push(['ok'], 200),
    ]);

    [$response] = $this->factory->pool(fn ($pool) => [
        $pool->retry(2, 1000, function ($exception, $request) {
            expect($request)->toBeInstanceOf(PendingRequest::class);

            $request->withHeaders(['Foo' => 'Bar']);

            return true;
        }, false)->get('http://foo.com/get'),
    ]);

    expect($response->successful())->toBeTrue();

    $this->factory->assertSent(function (Request $request) {
        return $request->hasHeader('Foo') && $request->header('Foo') === ['Bar'];
    });
});

test('handle request exeption with no response in pool considered connection exception', function () {
    $requestException = new GuzzleRequestException('Error', new \GuzzleHttp\Psr7\Request('GET', '/'));
    $this->factory->fake([
        'noresponse.com' => new RejectedPromise($requestException),
    ]);

    $responses = $this->factory->pool(function (Pool $pool) {
        return [
            $pool->get('noresponse.com'),
        ];
    });

    expect($responses[0])->toBeInstanceOf(ConnectionException::class);
    expect($responses[0]->getPrevious())->toBe($requestException);
});

test('exception thrown in retry callback is returned without retrying in pool', function () {
    $this->factory->fake([
        '*' => $this->factory->response(['error'], 500),
    ]);

    [$exception] = $this->factory->pool(fn ($pool) => [
        $pool->retry(2, 1000, function ($exception) {
            throw new Exception('Foo bar');
        }, false)->get('http://foo.com/get'),
    ]);

    expect($exception)->not->toBeNull();
    expect($exception)->toBeInstanceOf(Exception::class);
    expect($exception->getMessage())->toEqual('Foo bar');

    $this->factory->assertSentCount(1);
});

test('exception throw in middleware allows retry', function () {
    $middleware = Middleware::mapRequest(function (RequestInterface $request) {
        throw new RuntimeException;
    });

    $this->factory->fake(function (Request $request) {
        return $this->factory->response('Fake');
    })->withMiddleware($middleware)
        ->retry(3, 1, function (Exception $exception, PendingRequest $request) {
            return true;
        })->post('https://example.com');
})->throws(RuntimeException::class);

test('requests will be waiting sleep milliseconds received in backoff array', function () {
    Sleep::fake();

    $this->factory->fake([
        '*' => $this->factory->sequence()
            ->push(['error'], 500)
            ->push(['error'], 500)
            ->push(['error'], 500)
            ->push(['ok'], 200),
    ]);

    $this->factory
        ->retry([50, 100, 200], 0, null, true)
        ->get('http://foo.com/get');

    $this->factory->assertSentCount(4);

    // Make sure we waited 300ms for the first two attempts
    Sleep::assertSleptTimes(3);

    Sleep::assertSequence([
        Sleep::usleep(50_000),
        Sleep::usleep(100_000),
        Sleep::usleep(200_000),
    ]);
});

test('failed request', function () {
    $requestException = $this->factory->failedRequest(['code' => 'not_found'], 404, ['X-RateLimit-Remaining' => 199]);

    expect($requestException)->toBeInstanceOf(RequestException::class);
    expect($requestException->response->json())->toEqualCanonicalizing(['code' => 'not_found']);
    expect($requestException->response->status())->toEqual(404);
    expect($requestException->response->header('X-RateLimit-Remaining'))->toEqual(199);
});

test('fake connection exception', function () {
    $this->factory->fake($this->factory->failedConnection('Fake'));

    $exception = null;

    try {
        $this->factory->post('https://example.com');
    } catch (Throwable $e) {
        $exception = $e;
    }

    expect($exception)->not->toBeNull();
    expect($exception)->toBeInstanceOf(ConnectionException::class);
    expect($exception->getMessage())->toBe('Fake');

    $this->factory->assertSentCount(1);
    $this->factory->assertSent(function (Request $request, ?Response $response) {
        return $request->url() === 'https://example.com' && $response === null;
    });
});

test('fake connection exception within fake closure', function () {
    $this->factory->fake(fn () => $this->factory->failedConnection('Fake'));

    $exception = null;

    try {
        $this->factory->post('https://example.com');
    } catch (Throwable $e) {
        $exception = $e;
    }

    expect($exception)->not->toBeNull();
    expect($exception)->toBeInstanceOf(ConnectionException::class);
    expect($exception->getMessage())->toBe('Fake');

    $this->factory->assertSentCount(1);
});

test('fake connection exception within array', function () {
    $this->factory->fake(['*' => $this->factory->failedConnection('Fake')]);

    $exception = null;

    try {
        $this->factory->post('https://example.com');
    } catch (Throwable $e) {
        $exception = $e;
    }

    expect($exception)->not->toBeNull();
    expect($exception)->toBeInstanceOf(ConnectionException::class);
    expect($exception->getMessage())->toBe('Fake');

    $this->factory->assertSentCount(1);
});

test('fake connection exception within sequence', function () {
    $this->factory->fake([
        '*' => $this->factory->sequence()
            ->pushFailedConnection('Fake')
            ->push('Success'),
    ]);

    $exception = null;

    $response = $this->factory->retry(3, function ($attempt, $e) use (&$exception) {
        $exception = $e;

        return true;
    })->post('https://example.com');

    expect($response->body())->toBe('Success');

    expect($exception)->not->toBeNull();
    expect($exception)->toBeInstanceOf(ConnectionException::class);
    expect($exception->getMessage())->toBe('Fake');

    $this->factory->assertSentCount(2);
});

test('middleware runs when faked', function () {
    $this->factory->fake(function (Request $request) {
        return $this->factory->response('Fake');
    });

    $history = [];

    $pendingRequest = $this->factory->withMiddleware(
        Middleware::history($history)
    );

    $response = $pendingRequest->post('https://example.com', ['hyped-for' => 'laravel-movie']);

    expect($response->body())->toBe('Fake');

    expect($history)->toHaveCount(1);

    expect(tap($history[0]['response']->getBody())->rewind()->getContents())->toBe('Fake');

    expect(json_decode(tap($history[0]['request']->getBody())->rewind()->getContents(), true))->toBe(['hyped-for' => 'laravel-movie']);
});

test('middleware runs and can change request on assert sent', function () {
    $this->factory->fake(function (Request $request) {
        return $this->factory->response('Fake');
    });

    $pendingRequest = $this->factory->withMiddleware(
        Middleware::mapRequest(fn (RequestInterface $request) => $request->withHeader('X-Test-Header', 'Test'))
    );

    $pendingRequest->post('https://laravel.example', ['laravel' => 'framework']);

    $this->factory->assertSent(function (Request $request) {
        return
            $request->url() === 'https://laravel.example' &&
            $request->hasHeader('X-Test-Header', 'Test');
    });
});

test('ssl certificate errors converted to connection exception', function () {
    $this->factory->fake(function () {
        $request = new GuzzleRequest('HEAD', 'https://ssl-error.laravel.example');
        throw new GuzzleRequestException(
            'cURL error 60: SSL certificate problem: unable to get local issuer certificate',
            $request
        );
    });

    $this->factory->head('https://ssl-error.laravel.example');
})->throws(ConnectionException::class, 'cURL error 60: SSL certificate problem: unable to get local issuer certificate');

test('connect exception is converted to connection exception even when without factory', function () {
    $pendingRequest = new PendingRequest();

    $pendingRequest->setHandler(function () {
        throw new ConnectException(
            'cURL error 60: SSL certificate problem: unable to get local issuer certificate',
            new GuzzleRequest('HEAD', 'https://ssl-error.laravel.example')
        );
    });

    $pendingRequest->head('https://ssl-error.laravel.example');
})->throws(ConnectionException::class, 'cURL error 60: SSL certificate problem');

test('request exception without response is converted to connection exception even when without factory', function () {
    $pendingRequest = new PendingRequest();

    $pendingRequest->setHandler(function () {
        throw new GuzzleRequestException(
            'cURL error 28: Operation timed out',
            new GuzzleRequest('GET', 'https://timeout-laravel.example')
        );
    });

    $pendingRequest->get('https://timeout-laravel.example');
})->throws(ConnectionException::class, 'cURL error 28: Operation timed out');

test('request exception with response is converted to connection exception even when without factory', function () {
    $pendingRequest = new PendingRequest();

    $pendingRequest->setHandler(function () {
        throw new GuzzleRequestException(
            'cURL error 28: Operation timed out',
            new GuzzleRequest('GET', 'https://timeout-laravel.example'),
            new Psr7Response(301)
        );
    });

    $pendingRequest->get('https://timeout-laravel.example');
})->throws(ConnectionException::class, 'cURL error 28: Operation timed out');

test('too many redirects exception is converted to connection exception even when without factory', function () {
    $pendingRequest = new PendingRequest();

    $pendingRequest->setHandler(function () {
        throw new TooManyRedirectsException(
            'Maximum number of redirects (5) exceeded',
            new GuzzleRequest('GET', 'https://redirect.laravel.example'),
            new Psr7Response(301)
        );
    });

    $pendingRequest->maxRedirects(5)->get('https://redirect.laravel.example');
})->throws(ConnectionException::class, 'Maximum number of redirects (5) exceeded');

test('too many redirects exception converted to connection exception', function () {
    $this->factory->fake(function () {
        $request = new GuzzleRequest('GET', 'https://redirect.laravel.example');
        $response = new Psr7Response(301, ['Location' => 'https://redirect2.laravel.example']);

        throw new TooManyRedirectsException(
            'Maximum number of redirects (5) exceeded',
            $request,
            $response
        );
    });

    $this->factory->maxRedirects(5)->get('https://redirect.laravel.example');
})->throws(ConnectionException::class, 'Maximum number of redirects (5) exceeded');

test('too many redirects with faked redirect chain', function () {
    $this->factory->fake([
        '1.example.com' => $this->factory->response(null, 301, ['Location' => 'https://2.example.com']),
        '2.example.com' => $this->factory->response(null, 301, ['Location' => 'https://3.example.com']),
        '3.example.com' => $this->factory->response('', 200),
    ]);

    $this->factory->maxRedirects(1)->get('https://1.example.com');
})->throws(ConnectionException::class);

test('request exception is not thrown if the pending request is set to throw on failure but the response is successful', function () {
    $this->factory->fake([
        '*' => $this->factory->response(['success'], 200),
    ]);

    $response = $this->factory
        ->throw()
        ->get('http://foo.com/get');

    expect($response->status())->toBe(200);
});

test('request exception is thrown if the pending request is set to throw on failure', function () {
    $this->factory->fake([
        '*' => $this->factory->response(['error'], 403),
    ]);

    $exception = null;

    try {
        $this->factory
            ->throw()
            ->get('http://foo.com/get');
    } catch (RequestException $e) {
        $exception = $e;
    }

    expect($exception)->not->toBeNull();
    expect($exception)->toBeInstanceOf(RequestException::class);
});

test('request exception is thrown if the throw if on the pending request is set to true on failure', function () {
    $this->factory->fake([
        '*' => $this->factory->response(['error'], 403),
    ]);

    $exception = null;

    try {
        $this->factory
            ->throwIf(true)
            ->get('http://foo.com/get');
    } catch (RequestException $e) {
        $exception = $e;
    }

    expect($exception)->not->toBeNull();
    expect($exception)->toBeInstanceOf(RequestException::class);
});

test('request exception is not thrown if the throw if on the pending request is set to false on failure', function () {
    $this->factory->fake([
        '*' => $this->factory->response(['error'], 403),
    ]);

    $response = $this->factory
        ->throwIf(false)
        ->get('http://foo.com/get');

    expect($response->status())->toBe(403);
});

test('request exception is thrown if the throw if closure on the pending request returns true', function () {
    $this->factory->fake([
        '*' => $this->factory->response(['error'], 403),
    ]);

    $exception = null;

    $hitThrowCallback = false;

    try {
        $this->factory
            ->throwIf(function ($response) {
                expect($response)->toBeInstanceOf(Response::class);
                expect($response->status())->toBe(403);

                return true;
            }, function ($response, $e) use (&$hitThrowCallback) {
                expect($response)->toBeInstanceOf(Response::class);
                expect($response->status())->toBe(403);

                expect($e)->toBeInstanceOf(RequestException::class);
                $hitThrowCallback = true;
            })
            ->get('http://foo.com/get');
    } catch (RequestException $e) {
        $exception = $e;
    }

    expect($exception)->not->toBeNull();
    expect($exception)->toBeInstanceOf(RequestException::class);
    expect($hitThrowCallback)->toBeTrue();
});

test('request exception is not thrown if the throw if closure on the pending request returns false', function () {
    $this->factory->fake([
        '*' => $this->factory->response(['error'], 403),
    ]);

    $hitThrowCallback = false;

    $response = $this->factory
        ->throwIf(function ($response) {
            expect($response)->toBeInstanceOf(Response::class);
            expect($response->status())->toBe(403);

            return false;
        }, function ($response, $e) use (&$hitThrowCallback) {
            $hitThrowCallback = true;
        })
        ->get('http://foo.com/get');

    expect($response->status())->toBe(403);
    expect($hitThrowCallback)->toBeFalse();
});

test('request exception is thrown with callback if the pending request is set to throw on failure', function () {
    $this->factory->fake([
        '*' => $this->factory->response(['error'], 403),
    ]);

    $exception = null;

    $flag = false;

    try {
        $this->factory
            ->throw(function ($exception) use (&$flag) {
                $flag = true;
            })
            ->get('http://foo.com/get');
    } catch (RequestException $e) {
        $exception = $e;
    }

    expect($flag)->toBeTrue();

    expect($exception)->not->toBeNull();
    expect($exception)->toBeInstanceOf(RequestException::class);
});

test('request exception is thrown if the request fails', function () {
    $this->factory->fake([
        '*' => $this->factory::response('', 400),
    ]);

    $exception = null;

    try {
        $this->factory->get('http://foo.com/api')->throw();
    } catch (RequestException $e) {
        $exception = $e;
    }

    expect($exception)->not->toBeNull();
    expect($exception)->toBeInstanceOf(RequestException::class);
});

test('request exception is thrown with callback if the request fails', function () {
    $this->factory->fake([
        '*' => $this->factory::response('', 400),
    ]);

    $exception = null;

    $flag = false;

    try {
        $this->factory->get('http://foo.com/api')->throw(function () use (&$flag) {
            $flag = true;
        });
    } catch (RequestException $e) {
        $exception = $e;
    }

    expect($flag)->toBeTrue();

    expect($exception)->not->toBeNull();
    expect($exception)->toBeInstanceOf(RequestException::class);
});

test('request exception is not thrown if the request does not fail', function () {
    $this->factory->fake([
        '*' => ['result' => ['foo' => 'bar']],
    ]);

    $response = $this->factory->get('http://foo.com/api')->throw();

    expect($response->body())->toBe('{"result":{"foo":"bar"}}');
});

test('request exception is not returned if the pending request is set to throw on failure but the response is successful in pool', function () {
    $this->factory->fake([
        '*' => $this->factory->response(['success'], 200),
    ]);

    [$response] = $this->factory->pool(fn ($pool) => [
        $pool->throw()->get('http://foo.com/get'),
    ]);

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->status())->toBe(200);
});

test('request exception is returned if the pending request is set to throw on failure in pool', function () {
    $this->factory->fake([
        '*' => $this->factory->response(['error'], 403),
    ]);

    [$exception] = $this->factory->pool(fn ($pool) => [
        $pool->throw()->get('http://foo.com/get'),
    ]);

    expect($exception)->not->toBeNull();
    expect($exception)->toBeInstanceOf(RequestException::class);
});

test('request exception is returned if the throw if on the pending request is set to true on failure in pool', function () {
    $this->factory->fake([
        '*' => $this->factory->response(['error'], 403),
    ]);

    [$exception] = $this->factory->pool(fn ($pool) => [
        $pool->throwIf(true)->get('http://foo.com/get'),
    ]);

    expect($exception)->not->toBeNull();
    expect($exception)->toBeInstanceOf(RequestException::class);
});

test('request exception is not returned if the throw if on the pending request is set to false on failure in pool', function () {
    $this->factory->fake([
        '*' => $this->factory->response(['error'], 403),
    ]);

    [$response] = $this->factory->pool(fn ($pool) => [
        $pool->throwIf(false)->get('http://foo.com/get'),
    ]);

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->status())->toBe(403);
});

test('request exception is returned if the throw if closure on the pending request returns true in pool', function () {
    $this->factory->fake([
        '*' => $this->factory->response(['error'], 403),
    ]);

    $hitThrowCallback = collect();

    [$exception] = $this->factory->pool(fn ($pool) => [
        $pool->throwIf(function ($response) {
            expect($response)->toBeInstanceOf(Response::class);
            expect($response->status())->toBe(403);

            return true;
        }, function ($response, $e) use (&$hitThrowCallback) {
            expect($response)->toBeInstanceOf(Response::class);
            expect($response->status())->toBe(403);

            expect($e)->toBeInstanceOf(RequestException::class);

            $hitThrowCallback->push(true);
        })->get('http://foo.com/get'),
    ]);

    expect($exception)->not->toBeNull();
    expect($exception)->toBeInstanceOf(RequestException::class);
    expect($hitThrowCallback)->toHaveCount(1);
});

test('request exception is not returned if the throw if closure on the pending request returns false in pool', function () {
    $this->factory->fake([
        '*' => $this->factory->response(['error'], 403),
    ]);

    $hitThrowCallback = collect();

    [$response] = $this->factory->pool(fn ($pool) => [
        $pool->throwIf(function ($response) {
            expect($response)->toBeInstanceOf(Response::class);
            expect($response->status())->toBe(403);

            return false;
        }, function ($response, $e) use (&$hitThrowCallback) {
            $hitThrowCallback->push(true);
        })->get('http://foo.com/get'),
    ]);

    expect($hitThrowCallback)->toHaveCount(0);
    expect($response->status())->toBe(403);
});

test('request exception is returned with callback if the pending request is set to throw on failure in pool', function () {
    $this->factory->fake([
        '*' => $this->factory->response(['error'], 403),
    ]);

    $flag = collect();

    [$exception] = $this->factory->pool(fn ($pool) => [
        $pool->throw(function ($exception) use (&$flag) {
            $flag->push(true);
        })->get('http://foo.com/get'),
    ]);

    expect($flag)->toHaveCount(1);

    expect($exception)->not->toBeNull();
    expect($exception)->toBeInstanceOf(RequestException::class);
});

test('request exception is returned after last retry in pool', function () {
    $this->factory->fake([
        '*' => $this->factory->response(['error'], 403),
    ]);

    [$exception] = $this->factory->pool(fn ($pool) => [
        $pool->retry(3)->throw()->get('http://foo.com/get'),
    ]);

    expect($exception)->not->toBeNull();
    expect($exception)->toBeInstanceOf(RequestException::class);

    $this->factory->assertSentCount(3);
});

test('request exception is throw if condition is satisfied', function () {
    $this->factory->fake([
        '*' => $this->factory::response('', 400),
    ]);

    $exception = null;

    try {
        $this->factory->get('http://foo.com/api')->throwIf(true);
    } catch (RequestException $e) {
        $exception = $e;
    }

    expect($exception)->not->toBeNull();
    expect($exception)->toBeInstanceOf(RequestException::class);
});

test('request exception is not thrown if condition is not satisfied', function () {
    $this->factory->fake([
        '*' => $this->factory::response(['result' => ['foo' => 'bar']], 400),
    ]);

    $response = $this->factory->get('http://foo.com/api')->throwIf(false);

    expect($response->body())->toBe('{"result":{"foo":"bar"}}');
});

test('request exception is thrown when unless condition is not satisfied', function () {
    $this->factory->fake([
        '*' => $this->factory::response('', 400),
    ]);

    $exception = null;

    try {
        $this->factory->get('http://foo.com/api')->throwUnless(false);
    } catch (RequestException $e) {
        $exception = $e;
    }

    expect($exception)->not->toBeNull();
    expect($exception)->toBeInstanceOf(RequestException::class);
});

test('request exception is not thrown when unless condition is satisfied', function () {
    $this->factory->fake([
        '*' => $this->factory::response(['result' => ['foo' => 'bar']], 400),
    ]);

    $response = $this->factory->get('http://foo.com/api')->throwUnless(true);

    expect($response->body())->toBe('{"result":{"foo":"bar"}}');
});

test('request exception is throw if condition closure is satisfied', function () {
    $this->factory->fake([
        '*' => $this->factory::response('', 400),
    ]);

    $exception = null;

    $hitThrowCallback = false;

    try {
        $this->factory->get('http://foo.com/api')->throwIf(function ($response) {
            expect($response->status())->toBe(400);

            return true;
        }, function ($response, $e) use (&$hitThrowCallback) {
            expect($response->status())->toBe(400);
            expect($e)->toBeInstanceOf(RequestException::class);

            $hitThrowCallback = true;
        });
    } catch (RequestException $e) {
        $exception = $e;
    }

    expect($exception)->not->toBeNull();
    expect($exception)->toBeInstanceOf(RequestException::class);
    expect($hitThrowCallback)->toBeTrue();
});

test('request exception is not thrown if condition closure is not satisfied', function () {
    $this->factory->fake([
        '*' => $this->factory::response(['result' => ['foo' => 'bar']], 400),
    ]);

    $hitThrowCallback = false;

    $response = $this->factory->get('http://foo.com/api')->throwIf(function ($response) {
        expect($response->status())->toBe(400);

        return false;
    }, function ($response, $e) use (&$hitThrowCallback) {
        $hitThrowCallback = true;
    });

    expect($response->body())->toBe('{"result":{"foo":"bar"}}');
    expect($hitThrowCallback)->toBeFalse();
});

test('request exception is thrown if status code is satisfied', function () {
    $this->factory->fake([
        '*' => $this->factory::response('', 400),
    ]);

    $exception = null;

    try {
        $this->factory->get('http://foo.com/api')->throwIfStatus(400);
    } catch (RequestException $e) {
        $exception = $e;
    }

    expect($exception)->not->toBeNull();
    expect($exception)->toBeInstanceOf(RequestException::class);
});

test('request exception is thrown if status code is satisfied with closure', function () {
    $this->factory->fake([
        '*' => $this->factory::response('', 400),
    ]);

    $exception = null;

    try {
        $this->factory->get('http://foo.com/api')->throwIfStatus(fn ($status) => $status === 400);
    } catch (RequestException $e) {
        $exception = $e;
    }

    expect($exception)->not->toBeNull();
    expect($exception)->toBeInstanceOf(RequestException::class);
});

test('request exception is not thrown if status code is not satisfied', function () {
    $this->factory->fake([
        '*' => $this->factory::response('', 400),
    ]);

    $exception = null;

    try {
        $this->factory->get('http://foo.com/api')->throwIfStatus(500);
    } catch (RequestException $e) {
        $exception = $e;
    }

    expect($exception)->toBeNull();
});

test('request exception is thrown unless status code is satisfied', function () {
    $this->factory->fake([
        'http://foo.com/api/400' => $this->factory::response('', 400),
        'http://foo.com/api/408' => $this->factory::response('', 408),
        'http://foo.com/api/500' => $this->factory::response('', 500),
    ]);

    $exception = null;

    try {
        $this->factory->get('http://foo.com/api/400')->throwUnlessStatus(500);
    } catch (RequestException $e) {
        $exception = $e;
    }

    expect($exception)->not->toBeNull();
    expect($exception)->toBeInstanceOf(RequestException::class);

    $exception = null;

    $this->factory->fake([
        'http://foo.com/api/400' => $this->factory::response('', 400),
        'http://foo.com/api/408' => $this->factory::response('', 408),
        'http://foo.com/api/500' => $this->factory::response('', 500),
    ]);

    $exception = null;

    try {
        $this->factory->get('http://foo.com/api/400')->throwUnlessStatus(fn ($status) => $status === 500);
    } catch (RequestException $e) {
        $exception = $e;
    }

    expect($exception)->not->toBeNull();
    expect($exception)->toBeInstanceOf(RequestException::class);

    $exception = null;

    try {
        $this->factory->get('http://foo.com/api/408')->throwUnlessStatus(500);
    } catch (RequestException $e) {
        $exception = $e;
    }

    expect($exception)->not->toBeNull();
    expect($exception)->toBeInstanceOf(RequestException::class);

    $exception = null;

    try {
        $this->factory->get('http://foo.com/api/500')->throwUnlessStatus(500);
    } catch (RequestException $e) {
        $exception = $e;
    }

    expect($exception)->toBeNull();

    $exception = null;

    try {
        $this->factory->get('http://foo.com/api/500')->throwUnlessStatus(fn ($status) => $status === 500);
    } catch (RequestException $e) {
        $exception = $e;
    }

    expect($exception)->toBeNull();
});

test('request exception is thrown if is client error', function () {
    $this->factory->fake([
        'http://foo.com/api/400' => $this->factory::response('', 400),
        'http://foo.com/api/408' => $this->factory::response('', 408),
        'http://foo.com/api/500' => $this->factory::response('', 500),
        'http://foo.com/api/504' => $this->factory::response('', 504),
    ]);

    $exception = null;

    try {
        $this->factory->get('http://foo.com/api/400')->throwIfClientError();
    } catch (RequestException $e) {
        $exception = $e;
    }

    expect($exception)->not->toBeNull();
    expect($exception)->toBeInstanceOf(RequestException::class);

    $exception = null;

    try {
        $this->factory->get('http://foo.com/api/408')->throwIfClientError();
    } catch (RequestException $e) {
        $exception = $e;
    }

    expect($exception)->not->toBeNull();
    expect($exception)->toBeInstanceOf(RequestException::class);

    $exception = null;

    try {
        $this->factory->get('http://foo.com/api/500')->throwIfClientError();
    } catch (RequestException $e) {
        $exception = $e;
    }

    expect($exception)->toBeNull();

    $exception = null;

    try {
        $this->factory->get('http://foo.com/api/504')->throwIfClientError();
    } catch (RequestException $e) {
        $exception = $e;
    }

    expect($exception)->toBeNull();
});

test('throw if status works with non error status codes', function () {
    $this->factory->fake([
        '*' => $this->factory::response('', 201),
    ]);

    $exception = null;

    try {
        $this->factory->get('http://foo.com/api')->throwIfStatus(201);
    } catch (RequestException $e) {
        $exception = $e;
    }

    expect($exception)->not->toBeNull();
    expect($exception)->toBeInstanceOf(RequestException::class);

    $exception = null;

    try {
        $this->factory->get('http://foo.com/api')->throwIfStatus(fn ($status) => $status === 201);
    } catch (RequestException $e) {
        $exception = $e;
    }

    expect($exception)->not->toBeNull();
    expect($exception)->toBeInstanceOf(RequestException::class);
});

test('throw unless status works with non error status codes', function () {
    $this->factory->fake([
        '*' => $this->factory::response('', 201),
    ]);

    $exception = null;

    try {
        $this->factory->get('http://foo.com/api')->throwUnlessStatus(200);
    } catch (RequestException $e) {
        $exception = $e;
    }

    expect($exception)->not->toBeNull();
    expect($exception)->toBeInstanceOf(RequestException::class);

    $exception = null;

    try {
        $this->factory->get('http://foo.com/api')->throwUnlessStatus(201);
    } catch (RequestException $e) {
        $exception = $e;
    }

    expect($exception)->toBeNull();

    $exception = null;

    try {
        $this->factory->get('http://foo.com/api')->throwUnlessStatus(fn ($status) => $status === 200);
    } catch (RequestException $e) {
        $exception = $e;
    }

    expect($exception)->not->toBeNull();
    expect($exception)->toBeInstanceOf(RequestException::class);
});

test('request exception is thrown if is server error', function () {
    $this->factory->fake([
        'http://foo.com/api/400' => $this->factory::response('', 400),
        'http://foo.com/api/408' => $this->factory::response('', 408),
        'http://foo.com/api/500' => $this->factory::response('', 500),
        'http://foo.com/api/504' => $this->factory::response('', 504),
    ]);

    $exception = null;

    try {
        $this->factory->get('http://foo.com/api/400')->throwIfServerError();
    } catch (RequestException $e) {
        $exception = $e;
    }

    expect($exception)->toBeNull();

    $exception = null;

    try {
        $this->factory->get('http://foo.com/api/408')->throwIfServerError();
    } catch (RequestException $e) {
        $exception = $e;
    }

    expect($exception)->toBeNull();

    $exception = null;

    try {
        $this->factory->get('http://foo.com/api/500')->throwIfServerError();
    } catch (RequestException $e) {
        $exception = $e;
    }

    expect($exception)->not->toBeNull();
    expect($exception)->toBeInstanceOf(RequestException::class);

    $exception = null;

    try {
        $this->factory->get('http://foo.com/api/504')->throwIfServerError();
    } catch (RequestException $e) {
        $exception = $e;
    }

    expect($exception)->not->toBeNull();
    expect($exception)->toBeInstanceOf(RequestException::class);
});

test('it can enforce faking', function () {
    $this->factory->preventStrayRequests();
    $this->factory->fake(['https://vapor.laravel.com' => Factory::response('ok', 200)]);
    $this->factory->fake(['https://forge.laravel.com' => Factory::response('ok', 200)]);

    $responses = [];
    $responses[] = $this->factory->get('https://vapor.laravel.com')->body();
    $responses[] = $this->factory->get('https://forge.laravel.com')->body();
    expect($responses)->toBe(['ok', 'ok']);

    $this->factory->get('https://laravel.com');
})->throws(StrayRequestException::class, 'Attempted request to [https://laravel.com] without a matching fake.');

test('it can enforce faking in the pool', function () {
    $this->factory->preventStrayRequests();
    $this->factory->fake(['https://vapor.laravel.com' => Factory::response('ok', 200)]);
    $this->factory->fake(['https://forge.laravel.com' => Factory::response('ok', 200)]);

    $responses = $this->factory->pool(function (Pool $pool) {
        return [
            $pool->get('https://vapor.laravel.com'),
            $pool->get('https://forge.laravel.com'),
        ];
    });

    expect($responses[0]->status())->toBe(200);
    expect($responses[1]->status())->toBe(200);

    $this->factory->pool(function (Pool $pool) {
        return [
            $pool->get('https://laravel.com'),
        ];
    }, null);
})->throws(StrayRequestException::class, 'Attempted request to [https://laravel.com] without a matching fake.');

test('preventing stray requests', function () {
    expect($this->factory->preventingStrayRequests())->toBeFalse();

    $this->factory->preventStrayRequests();

    expect($this->factory->preventingStrayRequests())->toBeTrue();
});

test('allowing stray request urls', function () {
    expect($this->factory->preventingStrayRequests())->toBeFalse();
    expect($this->factory->isAllowedRequestUrl('127.0.0.1'))->toBeTrue();

    $this->factory->preventStrayRequests();
    expect($this->factory->isAllowedRequestUrl('127.0.0.1'))->toBeFalse();
    $this->factory->allowStrayRequests([
        '127.0.0.1',
    ]);

    expect($this->factory->preventingStrayRequests())->toBeTrue();
    expect($this->factory->isAllowedRequestUrl('127.0.0.1'))->toBeTrue();
});

test('it can add authorization header into request using before sending callback', function () {
    $this->factory->fake();

    $this->factory->beforeSending(function (Request $request) {
        $requestLine = sprintf(
            '%s %s HTTP/%s',
            $request->toPsrRequest()->getMethod(),
            $request->toPsrRequest()->getUri()->withScheme('')->withHost(''),
            $request->toPsrRequest()->getProtocolVersion()
        );

        return $request->toPsrRequest()->withHeader('Authorization', 'Bearer '.$requestLine);
    })->get('http://foo.com/json');

    $this->factory->assertSent(function (Request $request) {
        return
            $request->url() === 'http://foo.com/json' &&
            $request->hasHeader('Authorization', 'Bearer GET /json HTTP/1.1');
    });
});

test('it can set allow max redirects', function () {
    $request = new PendingRequest($this->factory);

    $request = $request->withOptions(['allow_redirects' => ['max' => 5]]);

    expect($request->getOptions())->toBe(['connect_timeout' => 10, 'crypto_method' => 33, 'http_errors' => false, 'timeout' => 30, 'allow_redirects' => ['max' => 5]]);

    $request = $request->maxRedirects(10);

    expect($request->getOptions())->toBe(['connect_timeout' => 10, 'crypto_method' => 33, 'http_errors' => false, 'timeout' => 30, 'allow_redirects' => ['max' => 10]]);
});

test('prevent duplicated content type', function () {
    $client = $this->factory->asJson();

    expect(Arr::get($client->getOptions(), 'headers.Content-Type'))->toBe('application/json');

    $client->asJson();
    $client->asJson();

    expect(Arr::get($client->getOptions(), 'headers.Content-Type'))->toBe('application/json');

    $client->contentType('foo');

    expect(Arr::get($client->getOptions(), 'headers.Content-Type'))->toBe('foo');
});

test('it can substitute url params', function () {
    $this->factory->fake();

    $this->factory->withUrlParameters([
        'endpoint' => 'https://laravel.com',
        'page' => 'docs',
        'version' => '9.x',
        'thing' => 'validation',
    ])->get('{+endpoint}/{page}/{version}/{thing}');

    $this->factory->assertSent(function (Request $request) {
        return $request->url() === 'https://laravel.com/docs/9.x/validation';
    });
});

test('the transfer stats are customizable', function () {
    $onStatsFunctionCalled = false;

    $stats = $this->factory
        ->withOptions([
            'on_stats' => function (TransferStats $stats) use (&$onStatsFunctionCalled) {
                $onStatsFunctionCalled = true;
            },
        ])
        ->get('http://example.com')
        ->handlerStats();

    expect($stats)->toBeArray();
    expect($stats)->not->toBeEmpty();
    expect($onStatsFunctionCalled)->toBeTrue();
});

test('the transfer stats are customizable on fake', function () {
    $onStatsFunctionCalled = false;

    $this->factory
        ->fake()
        ->withOptions([
            'on_stats' => function (TransferStats $stats) use (&$onStatsFunctionCalled) {
                $onStatsFunctionCalled = true;
            },
        ])
        ->get('https://foo.bar')
        ->handlerStats();

    expect($onStatsFunctionCalled)->toBeTrue();
});

test('it can add global middleware', function () {
    Carbon::setTestNow(now()->startOfDay());
    $requests = [];
    $responses = [];
    $this->factory->fake(function ($r) use (&$requests) {
        $requests[] = $r;

        Carbon::setTestNow(now()->addSeconds(6 * count($requests)));

        return $this->factory::response('expected content');
    });

    $this->factory->globalMiddleware(Middleware::mapRequest(function ($request) {
        // Test manipulating headers on outgoing request...
        return $request->withHeader('User-Agent', 'Laravel Framework/1.0')
            ->withAddedHeader('shared', 'global')
            ->withHeader('list', ['item-1', 'item-2'])
            ->withAddedHeader('list', ['item-3']);
    }))->globalMiddleware(Middleware::mapResponse(function ($response) use (&$requests) {
        // Test adding headers in incoming response..
        return $response->withHeader('X-Count', (string) count($requests));
    }))->globalMiddleware(function ($handler) {
        // Test wrapping request in timing function...
        return function ($request, $options) use ($handler) {
            $startedAt = now();

            return $handler($request, $options)->then(function (ResponseInterface $response) use ($startedAt) {
                return $response->withHeader('X-Duration', "{$startedAt->diffInSeconds(now())} seconds");
            });
        };
    });
    $responses[] = $this->factory->post('http://forge.laravel.com');
    $responses[] = $this->factory->withHeader('shared', 'local')->post('http://vapor.laravel.com');

    expect($requests)->toHaveCount(2);
    expect($responses)->toHaveCount(2);

    expect($requests[0]->header('User-Agent'))->toBe(['Laravel Framework/1.0']);
    expect($requests[0]->header('list'))->toBe(['item-1', 'item-2', 'item-3']);
    expect($requests[0]->header('shared'))->toBe(['global']);
    expect($responses[0]->header('X-Count'))->toBe('1');
    expect($responses[0]->header('X-Duration'))->toBe('6 seconds');

    expect($requests[1]->header('User-Agent'))->toBe(['Laravel Framework/1.0']);
    expect($requests[1]->header('list'))->toBe(['item-1', 'item-2', 'item-3']);
    expect($requests[1]->header('shared'))->toBe(['local', 'global']);
    expect($responses[1]->header('X-Count'))->toBe('2');
    expect($responses[1]->header('X-Duration'))->toBe('12 seconds');
});

test('it can add global request middleware', function () {
    $requests = [];
    $this->factory->fake(function ($r) use (&$requests) {
        $requests[] = $r;

        return Factory::response('expected content');
    });

    $this->factory->globalRequestMiddleware(function ($request) {
        return $request->withHeader('User-Agent', 'Laravel Framework/1.0');
    });
    $this->factory->post('http://forge.laravel.com');
    $this->factory->post('http://laravel.com');

    expect($requests[0]->header('User-Agent'))->toBe(['Laravel Framework/1.0']);
    expect($requests[1]->header('User-Agent'))->toBe(['Laravel Framework/1.0']);
});

test('it can add global response middleware', function () {
    $responses = [];
    $this->factory->fake(function ($r) use (&$request) {
        return Factory::response('expected content');
    });

    $this->factory->globalResponseMiddleware(function ($response) {
        return $response->withHeader('X-Foo', 'Bar');
    });
    $responses[] = $this->factory->post('http://forge.laravel.com');
    $responses[] = $this->factory->post('http://laravel.com');

    expect($responses[0]->header('X-Foo'))->toBe('Bar');
    expect($responses[1]->header('X-Foo'))->toBe('Bar');
});

test('it can get the global middleware', function () {
    $this->factory->globalMiddleware($middleware = fn () => null);

    expect($this->factory->getGlobalMiddleware())->toEqual([$middleware]);
});

test('it can add request middleware', function () {
    $requests = [];
    $this->factory->fake(function ($r) use (&$requests) {
        $requests[] = $r;

        return Factory::response('expected content');
    });

    $this->factory->withRequestMiddleware(function ($request) {
        return $request->withHeader('User-Agent', 'Laravel Framework/1.0');
    })->post('http://forge.laravel.com');
    $this->factory->post('http://laravel.com');

    expect($requests[0]->header('User-Agent'))->toBe(['Laravel Framework/1.0']);
    expect($requests[1]->header('User-Agent'))->toBe(['GuzzleHttp/7']);
});

test('it can add response middleware', function () {
    $responses = [];
    $this->factory->fake(function ($r) use (&$request) {
        return Factory::response('expected content');
    });

    $responses[] = $this->factory->withResponseMiddleware(function ($response) {
        return $response->withHeader('X-Foo', 'Bar');
    })->post('http://forge.laravel.com');
    $responses[] = $this->factory->post('http://laravel.com');

    expect($responses[0]->header('X-Foo'))->toBe('Bar');
    expect($responses[1]->header('X-Foo'))->toBe('');
});

test('it returns response', function () {
    $this->factory->fake([
        '*' => $this->factory::response('expected content'),
    ]);

    $response = $this->factory->get('http://laravel.com');

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->body())->toBe('expected content');
});

test('it can return custom response class', function () {
    $factory = new CustomFactory;

    $factory->fake([
        '*' => $factory::response('expected content'),
    ]);

    $response = $factory->get('http://laravel.fake');

    expect($response)->toBeInstanceOf(TestResponse::class);
    expect($response->body())->toBe('expected content');
});

test('it can have global default values', function () {
    $factory = new Factory;
    $timeout = null;
    $allowRedirects = null;
    $headers = null;
    $factory->fake(function ($request, $options) use (&$timeout, &$allowRedirects, &$headers, $factory) {
        $timeout = $options['timeout'];
        $allowRedirects = $options['allow_redirects'];
        $headers = $request->headers();

        return $factory->response('');
    });

    $factory->get('https://laravel.com');
    expect($timeout)->toBe(30);
    expect($allowRedirects)->toBe(['max' => 5, 'protocols' => ['http', 'https'], 'strict' => false, 'referer' => false, 'track_redirects' => false]);
    expect($headers['X-Foo'] ?? null)->toBeNull();

    $factory->globalOptions([
        'timeout' => 5,
        'allow_redirects' => false,
        'headers' => [
            'X-Foo' => 'true',
        ],
    ]);

    $factory->get('https://laravel.com');
    expect($timeout)->toBe(5);
    expect($allowRedirects)->toBeFalse();
    expect($headers['X-Foo'])->toBe(['true']);

    $factory->globalOptions(fn () => [
        'timeout' => 10,
        'headers' => [
            'X-Foo' => 'false',
            'X-Bar' => 'true',
        ],
    ]);

    $factory->get('https://laravel.com');
    expect($timeout)->toBe(10);
    expect($allowRedirects)->toBe(['max' => 5, 'protocols' => ['http', 'https'], 'strict' => false, 'referer' => false, 'track_redirects' => false]);
    expect($headers['X-Foo'])->toBe(['false']);
    expect($headers['X-Bar'])->toBe(['true']);
});

test('it can create pending request', function () {
    $factory = new Factory();

    expect($factory->createPendingRequest())->toBeInstanceOf(PendingRequest::class);
});

test('batch no callbacks', function () {
    $this->factory->fake([
        'https://200.com' => $this->factory::response('OK', 200),
        'https://201.com' => $this->factory::response('Created', 201),
        'https://500.com' => $this->factory::response('Error', 500),
    ]);

    $batch = $this->factory->batch(function (Batch $batch) {
        return [
            $batch->as('first')->get('https://200.com'),
            $batch->as('second')->get('https://201.com'),
            $batch->as('third')->get('https://500.com'),
        ];
    });

    expect($batch->totalRequests)->toBe(3);
    expect($batch->finished())->toBeFalse();

    $responses = $batch->send();

    expect($responses['first']->status())->toBe(200);
    expect($responses['second']->status())->toBe(201);
    expect($responses['third']->status())->toBe(500);

    expect($batch->totalRequests)->toBe(3);
    expect($batch->pendingRequests)->toBe(0);
    expect($batch->failedRequests)->toBe(1);
    expect($batch->hasFailures())->toBeTrue();
    expect($batch->finished())->toBeTrue();
});

test('batch defer', function () {
    $this->factory->fake([
        'https://200.com' => $this->factory::response('OK', 200),
        'https://201.com' => $this->factory::response('Created', 201),
        'https://500.com' => $this->factory::response('Error', 500),
    ]);

    $batch = $this->factory->batch(function (Batch $batch) {
        return [
            $batch->as('first')->get('https://200.com'),
            $batch->as('second')->get('https://201.com'),
            $batch->as('third')->get('https://500.com'),
        ];
    });

    expect($batch->totalRequests)->toBe(3);
    expect($batch->finished())->toBeFalse();

    $deferredCallback = $batch->defer();

    expect($deferredCallback)->toBeInstanceOf(DeferredCallback::class);
    expect($batch->finished())->toBeFalse();
});

test('cannot add requests to in progress batch', function () {
    $this->factory->fake([
        'https://200.com' => $this->factory::response('OK', 200),
        'https://201.com' => $this->factory::response('Created', 201),
        'https://500.com' => $this->factory::response('Error', 500),
    ]);

    $responses = $this->factory->batch(function (Batch $batch) {
        return [
            $batch->as('first')->get('https://200.com'),
            $batch->as('second')->get('https://201.com'),
        ];
    })->progress(function (Batch $batch, int|string $key, Response $response) use (&$progressCallbacks) {
        $batch->as('third')->get('https://500.com');
    })->send();
})->throws(BatchInProgressException::class);

test('batch before hook', function () {
    $this->factory->fake([
        'https://200.com' => $this->factory::response('OK', 200),
        'https://201.com' => $this->factory::response('Created', 201),
    ]);

    $beforeCallback = false;

    $responses = $this->factory->batch(function (Batch $batch) {
        return [
            $batch->as('first')->get('https://200.com'),
            $batch->as('second')->get('https://201.com'),
        ];
    })->before(function (Batch $batch) use (&$beforeCallback) {
        $beforeCallback = true;
    })->send();

    expect($responses['first']->status())->toBe(200);
    expect($responses['second']->status())->toBe(201);
    expect($beforeCallback)->toBeTrue();
});

test('batch progress hook', function () {
    $this->factory->fake([
        'https://200.com' => $this->factory::response('OK', 200),
        'https://201.com' => $this->factory::response('Created', 201),
        'https://500.com' => $this->factory::response('Error', 500),
    ]);

    $progressCallbacks = [];

    $responses = $this->factory->batch(function (Batch $batch) {
        return [
            $batch->as('first')->get('https://200.com'),
            $batch->as('second')->get('https://201.com'),
            $batch->as('third')->get('https://500.com'),
        ];
    })->progress(function (Batch $batch, int|string $key, Response $response) use (&$progressCallbacks) {
        $progressCallbacks[$key] = $response;
    })->send();

    expect($responses['first']->status())->toBe(200);
    expect($responses['second']->status())->toBe(201);
    expect($responses['third']->status())->toBe(500);

    expect($progressCallbacks)->toHaveCount(2);
    expect($progressCallbacks)->toHaveKey('first');
    expect($progressCallbacks)->toHaveKey('second');
    expect($progressCallbacks)->not->toHaveKey('third');

    expect($progressCallbacks['first'])->toBe($responses['first']);
    expect($progressCallbacks['second'])->toBe($responses['second']);
});

test('batch catch hook', function () {
    $this->factory->fake([
        'https://200.com' => $this->factory::response('OK', 200),
        'https://201.com' => $this->factory::response('Created', 201),
        'https://500.com' => $this->factory::response('Error', 500),
    ]);

    $catchCallbacks = [];

    $responses = $this->factory->batch(function (Batch $batch) {
        return [
            $batch->as('first')->get('https://200.com'),
            $batch->as('second')->get('https://201.com'),
            $batch->as('third')->get('https://500.com'),
        ];
    })->catch(function (Batch $batch, int|string $key, Response|RequestException $response) use (&$catchCallbacks) {
        $catchCallbacks[$key] = $response;
    })->send();

    expect($responses['first']->status())->toBe(200);
    expect($responses['second']->status())->toBe(201);
    expect($responses['third']->status())->toBe(500);

    expect($catchCallbacks)->toHaveCount(1);
    expect($catchCallbacks)->not->toHaveKey('first');
    expect($catchCallbacks)->not->toHaveKey('second');
    expect($catchCallbacks)->toHaveKey('third');

    expect($catchCallbacks['third'])->toBe($responses['third']);
});

test('batch then hook is called', function () {
    $this->factory->fake([
        'https://200.com' => $this->factory::response('OK', 200),
        'https://201.com' => $this->factory::response('Created', 201),
    ]);

    $thenCallback = [];

    $responses = $this->factory->batch(function (Batch $batch) {
        return [
            $batch->as('first')->get('https://200.com'),
            $batch->as('second')->get('https://201.com'),
        ];
    })->then(function (Batch $batch, array $results) use (&$thenCallback) {
        $thenCallback = $results;
    })->send();

    expect($responses['first']->status())->toBe(200);
    expect($responses['second']->status())->toBe(201);

    expect($thenCallback)->toHaveCount(2);
    expect($thenCallback)->toHaveKey('first');
    expect($thenCallback)->toHaveKey('second');

    expect($thenCallback['first'])->toBe($responses['first']);
    expect($thenCallback['second'])->toBe($responses['second']);
});

test('batch then hook is not called', function () {
    $this->factory->fake([
        'https://200.com' => $this->factory::response('OK', 200),
        'https://500.com' => $this->factory::response('Error', 500),
    ]);

    $thenCallback = [];

    $responses = $this->factory->batch(function (Batch $batch) {
        return [
            $batch->as('first')->get('https://200.com'),
            $batch->as('second')->get('https://500.com'),
        ];
    })->then(function (Batch $batch, array $results) use (&$thenCallback) {
        $thenCallback = $results;
    })->send();

    expect($responses['first']->status())->toBe(200);
    expect($responses['second']->status())->toBe(500);

    expect($thenCallback)->toHaveCount(0);
});

test('batch finally hook is called without errors', function () {
    $this->factory->fake([
        'https://200.com' => $this->factory::response('OK', 200),
        'https://201.com' => $this->factory::response('Created', 201),
    ]);

    $finallyCallback = [];

    $responses = $this->factory->batch(function (Batch $batch) {
        return [
            $batch->as('first')->get('https://200.com'),
            $batch->as('second')->get('https://201.com'),
        ];
    })->finally(function (Batch $batch, array $results) use (&$finallyCallback) {
        $finallyCallback = $results;
    })->send();

    expect($responses['first']->status())->toBe(200);
    expect($responses['second']->status())->toBe(201);

    expect($finallyCallback)->toHaveCount(2);
    expect($finallyCallback)->toHaveKey('first');
    expect($finallyCallback)->toHaveKey('second');

    expect($finallyCallback['first'])->toBe($responses['first']);
    expect($finallyCallback['second'])->toBe($responses['second']);
});

test('batch finally hook is called with errors', function () {
    $this->factory->fake([
        'https://200.com' => $this->factory::response('OK', 200),
        'https://500.com' => $this->factory::response('Error', 500),
    ]);

    $finallyCallback = [];

    $responses = $this->factory->batch(function (Batch $batch) {
        return [
            $batch->as('first')->get('https://200.com'),
            $batch->as('second')->get('https://500.com'),
        ];
    })->finally(function (Batch $batch, array $results) use (&$finallyCallback) {
        $finallyCallback = $results;
    })->send();

    expect($responses['first']->status())->toBe(200);
    expect($responses['second']->status())->toBe(500);

    expect($finallyCallback)->toHaveCount(2);
    expect($finallyCallback)->toHaveKey('first');
    expect($finallyCallback)->toHaveKey('second');

    expect($finallyCallback['first'])->toBe($responses['first']);
    expect($finallyCallback['second'])->toBe($responses['second']);
});

test('batch concurrency', function () {
    $this->factory->fake([
        'https://200.com' => $this->factory::response('OK', 200),
        'https://201.com' => $this->factory::response('Created', 201),
        'https://202.com' => $this->factory::response('Accepted', 202),
        'https://203.com' => $this->factory::response('Non-Authoritative Information', 203),
    ]);

    $executionOrder = [];

    $batch = $this->factory->batch(function (Batch $batch) {
        return [
            $batch->as('first')->get('https://200.com'),
            $batch->as('second')->get('https://201.com'),
            $batch->as('third')->get('https://202.com'),
            $batch->as('fourth')->get('https://203.com'),
        ];
    })->progress(function (Batch $batch, int|string $key, Response $response) use (&$executionOrder) {
        $executionOrder[] = $key;
    })->concurrency(2);

    expect($batch->totalRequests)->toBe(4);

    $responses = $batch->send();

    expect($responses['first']->status())->toBe(200);
    expect($responses['second']->status())->toBe(201);
    expect($responses['third']->status())->toBe(202);
    expect($responses['fourth']->status())->toBe(203);

    expect($batch->totalRequests)->toBe(4);
    expect($batch->pendingRequests)->toBe(0);
    expect($batch->failedRequests)->toBe(0);
});

dataset('methods receiving arrayable data', [
    'patch' => ['patch'],
    'put' => ['put'],
    'post' => ['post'],
    'delete' => ['delete'],
]);

test('after response', function () {
    $this->factory->fake([
        'http://200.com*' => $this->factory::response('OK'),
    ]);

    $response = $this->factory
        ->afterResponse(fn (Response $response): TestResponse => new TestResponse($response->toPsrResponse()))
        ->afterResponse(fn () => 'abc')
        ->afterResponse(function ($r) {
            expect($r)->toBeInstanceOf(TestResponse::class);
        })
        ->afterResponse(fn (Response $r) => new Response($r->toPsrResponse()->withBody(Utils::streamFor(strtolower($r->body())))))
        ->get('http://200.com');

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->body())->toBe('ok');
});

test('after response with throws', function () {
    $this->factory->fake([
        'http://500.com*' => $this->factory::response('oh no', 500),
    ]);

    try {
        $this->factory->throw()
            ->afterResponse(fn ($response) => new TestResponse($response->toPsrResponse()))
            ->post('http://500.com');
    } catch (RequestException $e) {
        expect($e->response)->toBeInstanceOf(TestResponse::class);
    }
});

test('after response with async', function () {
    $this->factory->fake([
        'http://200.com*' => $this->factory::response('OK', 200),
        'http://401.com*' => $this->factory::response('Unauthorized.', 401),
    ]);

    $o = $this->factory->pool(function (Pool $pool): void {
        $pool->as('200')->afterResponse(fn (Response $response) => new TestResponse($response->toPsrResponse()))->get('http://200.com');
        $pool->as('401-throwing')->throw()->afterResponse(fn (Response $response) => new TestResponse($response->toPsrResponse()))->get('http://401.com');
        $pool->as('401-response')->afterResponse(fn (Response $response) => new TestResponse($response->toPsrResponse()->withBody(Utils::streamFor('different'))))->get('http://401.com');
    }, 0);

    expect($o['200'])->toBeInstanceOf(TestResponse::class);
    expect($o['401-response'])->toBeInstanceOf(TestResponse::class);
    expect($o['401-response']->body())->toEqual('different');
    expect($o['401-throwing'])->toBeInstanceOf(RequestException::class);
    expect($o['401-throwing']->response)->toBeInstanceOf(TestResponse::class);
});

test('respects default flags', function () {
    Response::$defaultJsonDecodingFlags = JSON_BIGINT_AS_STRING;

    // Create a response with a big integer that exceeds PHP_INT_MAX
    $bigInt = '9223372036854775808';
    $body = '{"value":'.$bigInt.'}';

    $response = new Response(Factory::psr7Response($body));

    // With JSON_BIGINT_AS_STRING, it should be the exact string
    expect($response->json('value'))->toBe($bigInt);
    expect($response->object()->value)->toBe($bigInt);
    expect($response->collect('value')->first())->toBe($bigInt);
    expect($response->fluent()->get('value'))->toBe($bigInt);

    // Default json_decode behavior (flags=0), big integers become floats (losing precision)
    expect($response->json('value', null, 0))->toBeFloat();
    expect($response->object(0)->value)->toBeFloat();
    expect($response->collect('value', 0)->first())->toBeFloat();
    expect($response->fluent(flags: 0)->get('value'))->toBeFloat();
});

test('json decoding is cached when flags match', function () {
    Response::$defaultJsonDecodingFlags = JSON_BIGINT_AS_STRING;

    $response = new BodyTrackingResponse(Factory::psr7Response('{"foo":"bar"}'));

    // First call decodes with default (JSON_BIGINT_AS_STRING)
    $response->json();
    expect($response->bodyCallCount)->toBe(1);

    // Second call with same (null) flags uses cache
    $response->json();
    expect($response->bodyCallCount)->toBe(1);

    // Explicit flags matching default still uses cache
    $response->json(flags: JSON_BIGINT_AS_STRING);
    expect($response->bodyCallCount)->toBe(1);

    // Different flags triggers re-decode
    $response->json(flags: 0);
    expect($response->bodyCallCount)->toBe(2);

    // Same explicit flags uses cache
    $response->json(flags: 0);
    expect($response->bodyCallCount)->toBe(2);

    // Null flags means "use default", cached flags differ, so re-decode
    $response->json();
    expect($response->bodyCallCount)->toBe(3);
});

