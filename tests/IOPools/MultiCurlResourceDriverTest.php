<?php

use Voyager\Contracts\IOPools\Completion as CompletionContract;
use Voyager\Contracts\IOPools\IOPoolsException;
use Voyager\Contracts\IOPools\PoolService;
use Voyager\IOPools\DTO\HttpResult;
use Voyager\IOPools\Drivers\MultiCurlResourceDriver;
use Voyager\IOPools\IOPoolDock;
use Voyager\Vessel\Vessel as Container;

/**
 * The transport seam is internal now, so the fake is a subclass: dispatch
 * records instead of curling, harvest hands back whatever the test staged.
 */
class FakeCurlDriver extends MultiCurlResourceDriver
{
    public array $dispatched = [];

    public array $ready = [];

    public array $moving = [];

    public function __construct(PoolService $io_pool)
    {
        parent::__construct([], $io_pool);
    }

    protected function dispatch(string $name, string $url, string $method, array $headers = [], ?array $body = null): void
    {
        $this->dispatched[] = compact('name', 'url', 'method', 'headers', 'body');
    }

    protected function harvest(): array
    {
        $drained = $this->ready;
        $this->ready = [];

        return $drained;
    }

    public function progress(): array
    {
        return $this->moving;
    }
}

function httpResult(string $name, bool $ok = true, int $status = 200, string $body = ''): HttpResult
{
    return new HttpResult(name: $name, ok: $ok, status: $status, headers: [], body: $body, error: $ok ? null : 'boom');
}

beforeEach(function () {
    $this->dock = new IOPoolDock(new Container, ['resources' => []]);
    $this->driver = new FakeCurlDriver($this->dock);
    $this->dock->resource('http', $this->driver);
});

test('a registered http resource answers http() and pump reaches it', function () {
    expect($this->dock->http())->toBe($this->driver);

    $this->driver->ready = [$result = httpResult('forecast')];
    $this->dock->pump();

    expect($this->dock->drain()->all())->toBe([$result]);
});

test('a finished call lands in the dock as its HttpResult and settles the presumption', function () {
    $presumption = $this->driver->fetch('forecast', 'https://api.weather.gov/points/39,-104');

    expect($this->driver->dispatched[0]['method'])->toBe('GET')
        ->and($presumption->settled())->toBeFalse();

    $this->driver->ready = [$result = httpResult('forecast', body: '{"ok":true}')];
    $this->dock->pump();

    expect($this->dock->drain()->all())->toBe([$result])
        ->and($presumption->settled())->toBeTrue()
        ->and($presumption->result())->toBe($result);
});

test('fetch bakes params into the query string; post carries its body', function () {
    $this->driver->fetch('points', 'https://api.weather.gov/points', params: ['lat' => 39.74]);
    $this->driver->post('report', 'https://x/report', body: ['a' => 1]);

    expect($this->driver->dispatched[0]['url'])->toBe('https://api.weather.gov/points?lat=39.74')
        ->and($this->driver->dispatched[0]['body'])->toBeNull()
        ->and($this->driver->dispatched[1]['method'])->toBe('POST')
        ->and($this->driver->dispatched[1]['body'])->toBe(['a' => 1]);
});

test('an envelope factory swaps domain mail into the dock; the presumption settles on the raw result', function () {
    $arrived = new class(httpResult('seed')) extends HttpResult
    {
        public function __construct(HttpResult $r, public ?string $verdict = null)
        {
            parent::__construct($r->name, $r->ok, $r->status, $r->headers, $r->body, $r->error);
        }
    };

    $presumption = $this->driver->fetch(
        'forecast',
        'https://api.weather.gov/gridpoints/BOU/62,61/forecast',
        envelope: function (HttpResult $result) use ($arrived): CompletionContract {
            $arrived->verdict = "hydrated:{$result->status}";

            return $arrived;
        },
    );

    $this->driver->ready = [$raw = httpResult('forecast')];
    $this->dock->pump();

    expect($this->dock->drain()->all())->toBe([$arrived])
        ->and($arrived->verdict)->toBe('hydrated:200')
        ->and($presumption->result())->toBe($raw);
});

test('success and fail hooks fire by transport truth, inside the pump', function () {
    $heard = [];
    $this->driver->fetch('good', 'https://x/1')
        ->onSuccess(function (HttpResult $r) use (&$heard) { $heard[] = "good:{$r->status}"; })
        ->onFail(fn () => $heard[] = 'good:fail');
    $this->driver->fetch('bad', 'https://x/2')
        ->onFail(function (HttpResult $r) use (&$heard) { $heard[] = "bad:{$r->error}"; });

    $this->driver->ready = [httpResult('good', status: 404), httpResult('bad', ok: false)];
    $this->dock->pump();

    expect($heard)->toBe(['good:404', 'bad:boom']);
});

test('one in-flight call per name: duplicates refused, the name frees on settle', function () {
    $first = $this->driver->fetch('clip', 'https://x/clip');

    expect(fn () => $this->driver->fetch('clip', 'https://x/clip'))
        ->toThrow(IOPoolsException::class, "Call 'clip' is already in flight.")
        ->and($this->driver->inFlight('clip'))->toBe($first);

    $this->driver->ready = [httpResult('clip')];
    $this->dock->pump();

    expect($this->driver->inFlight('clip'))->toBeNull()
        ->and($this->driver->fetch('clip', 'https://x/clip'))->not->toBe($first);
});

test('progress speaks through the hook only when the byte count moves, and never as mail', function () {
    $seen = [];
    $this->driver->fetch('clip', 'https://x/clip.mp4')
        ->onProgress(function (int $now, int $total) use (&$seen) { $seen[] = [$now, $total]; });

    $this->driver->moving = ['clip' => ['now' => 1024, 'total' => 4096]];
    $this->dock->pump();
    $this->dock->pump();

    $this->driver->moving = ['clip' => ['now' => 2048, 'total' => 4096]];
    $this->dock->pump();

    expect($seen)->toBe([[1024, 4096], [2048, 4096]])
        ->and($this->dock->drain()->isEmpty())->toBeTrue();
});
