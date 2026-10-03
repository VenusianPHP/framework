<?php

use Voyager\Workflows\AsyncFlow;
use Voyager\Workflows\AsyncNode;
use Voyager\Workflows\SharedBag;
use Voyager\Workflows\AsyncBatchFlow;
use Voyager\Workflows\AsyncBatchNode;
use Voyager\Workflows\AsyncRuntimeManager;
use Voyager\Workflows\AsyncParallelBatchFlow;
use Voyager\Workflows\AsyncParallelBatchNode;
use Voyager\Workflows\Runtimes\LoopRuntime;
use Voyager\Vessel\ControlPanel;
use Voyager\Contracts\IOPools\Loop;
use Voyager\Contracts\Workflows\AsyncRuntime;
use Venusian\Tests\Log\Fixtures\LogApp;

beforeEach(function () {
    $this->app = LogApp::boot();
    $this->runtime = $this->app->get(AsyncRuntimeManager::class)->driver();
});
afterEach(fn () => LogApp::tearDown($this->app, $this));

/** A parallel batch node whose items each wait $seconds on the loop, then answer ten times themselves. */
function waitingBatch(float $seconds, ?int $concurrency = null): AsyncParallelBatchNode
{
    return new class($seconds, $concurrency) extends AsyncParallelBatchNode {
        public function __construct(private float $seconds, ?int $concurrency) { parent::__construct(concurrency: $concurrency); }

        public function prepAsync(SharedBag $shared): mixed
        {
            return ['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4];
        }

        public function execAsync(mixed $item): mixed
        {
            return $this->runtime()->delay($this->seconds)->then(fn () => $item * 10);
        }

        public function postAsync(SharedBag $shared, mixed $prepRes, mixed $execRes): mixed
        {
            $shared->results = $execRes;

            return null;
        }
    };
}

it('runs on the app\'s loop by default', function () {
    $runtime = new AsyncNode()->runtime();

    expect($runtime)->toBeInstanceOf(LoopRuntime::class)
        ->and($runtime->loop())->toBe($this->app->get(Loop::class));
});

it('runs a node with no app at all on the standalone loop', function () {
    ControlPanel::setInstance(null);

    $node = new class extends AsyncNode {
        public function execAsync(mixed $prepRes): mixed
        {
            return $this->runtime()->delay(0.05)->then(fn () => 'waited');
        }

        public function postAsync(SharedBag $shared, mixed $prepRes, mixed $execRes): mixed
        {
            $shared->result = $execRes;

            return null;
        }
    };
    $bag = new SharedBag();

    $node->runAsync($bag);

    expect($node->runtime())->toBe(LoopRuntime::standalone())
        ->and($bag->result)->toBe('waited');

    ControlPanel::setInstance($this->app);
});

it('overlaps a parallel batch\'s promise-returning items, and keeps the items\' keys', function () {
    $bag = new SharedBag();
    $started = hrtime(true);

    waitingBatch(0.3)->runAsync($bag);

    expect($bag->results)->toBe(['a' => 10, 'b' => 20, 'c' => 30, 'd' => 40])
        ->and(hrtime(true) - $started)->toBeLessThan(500_000_000);
});

it('holds a parallel batch to its concurrency cap', function () {
    $started = hrtime(true);

    waitingBatch(0.2, concurrency: 2)->runAsync(new SharedBag());

    expect(hrtime(true) - $started)->toBeGreaterThan(380_000_000)->toBeLessThan(600_000_000);
});

it('does not retry a node whose task was cancelled', function () {
    $node = new class extends AsyncNode {
        public int $attempts = 0;

        public function __construct() { parent::__construct(maxRetries: 3, wait: 1); }

        public function execAsync(mixed $prepRes): mixed
        {
            $this->attempts++;

            throw new Voyager\Contracts\IOPools\CancelledException('The task was cancelled.');
        }
    };

    expect(fn () => $node->runAsync(new SharedBag()))->toThrow(Voyager\Contracts\IOPools\CancelledException::class)
        ->and($node->attempts)->toBe(1);
});

it('runs a batch node\'s items one after another', function () {
    $node = new class extends AsyncBatchNode {
        public function prepAsync(SharedBag $shared): mixed
        {
            return [1, 2, 3];
        }

        public function execAsync(mixed $item): mixed
        {
            return $this->runtime()->delay(0.1)->then(fn () => $item * 2);
        }

        public function postAsync(SharedBag $shared, mixed $prepRes, mixed $execRes): mixed
        {
            $shared->results = $execRes;

            return null;
        }
    };
    $bag = new SharedBag();
    $started = hrtime(true);

    $node->runAsync($bag);

    expect($bag->results)->toBe([2, 4, 6])
        ->and(hrtime(true) - $started)->toBeGreaterThan(290_000_000);
});

it('waits between retries without holding up sibling items', function () {
    $node = new class extends AsyncParallelBatchNode {
        public array $finished = [];
        private int $flaky_attempts = 0;

        public function __construct() { parent::__construct(maxRetries: 2, wait: 1); }

        public function prepAsync(SharedBag $shared): mixed
        {
            return ['flaky', 'steady'];
        }

        public function execAsync(mixed $item): mixed
        {
            if ($item === 'flaky' && ++$this->flaky_attempts === 1) {
                throw new RuntimeException('first attempt fails');
            }

            return $this->runtime()->delay($item === 'steady' ? 0.1 : 0)->then(function () use ($item) {
                $this->finished[] = $item;

                return $item;
            });
        }
    };
    $started = hrtime(true);

    $node->runAsync(new SharedBag());

    expect($node->finished)->toBe(['steady', 'flaky'])
        ->and(hrtime(true) - $started)->toBeGreaterThan(900_000_000)->toBeLessThan(1_500_000_000);
});

it('settles every item before it throws a parallel batch\'s first failure', function () {
    $node = new class extends AsyncParallelBatchNode {
        public array $finished = [];

        public function prepAsync(SharedBag $shared): mixed
        {
            return ['fails', 'slow'];
        }

        public function execAsync(mixed $item): mixed
        {
            if ($item === 'fails') {
                throw new RuntimeException('item failed');
            }

            return $this->runtime()->delay(0.2)->then(function () use ($item) {
                $this->finished[] = $item;
            });
        }
    };

    expect(fn () => $node->runAsync(new SharedBag()))->toThrow(RuntimeException::class, 'item failed')
        ->and($node->finished)->toBe(['slow']);
});

it('walks an async flow of async and sync nodes, handing every member its runtime', function () {
    $fetch = new class extends AsyncNode {
        public function execAsync(mixed $prepRes): mixed
        {
            return $this->runtime()->delay(0.05)->then(fn () => 'fetched');
        }

        public function postAsync(SharedBag $shared, mixed $prepRes, mixed $execRes): mixed
        {
            $shared->trail = [$execRes];

            return 'next';
        }
    };
    $store = new class extends Voyager\Workflows\Node {
        public function post(SharedBag $shared, mixed $prepRes, mixed $execRes): ?string
        {
            $shared->trail = [...$shared->trail, 'stored'];

            return 'done';
        }
    };
    $fetch->on('next')->next($store);
    $runtime = LoopRuntime::standalone();
    $bag = new SharedBag();

    $action = new AsyncFlow($fetch, $runtime)->runAsync($bag);

    expect($action)->toBe('done')
        ->and($bag->trail)->toBe(['fetched', 'stored'])
        ->and($fetch->runtime())->toBe($runtime);
});

it('runs a batch flow once per param set, in turn, and a parallel batch flow all at once', function () {
    $record = new class extends AsyncNode {
        public function execAsync(mixed $prepRes): mixed
        {
            return $this->runtime()->delay(0.2)->then(fn () => $this->params['id']);
        }

        public function postAsync(SharedBag $shared, mixed $prepRes, mixed $execRes): mixed
        {
            $shared->put('seen', [...($shared->seen ?? []), $execRes]);

            return null;
        }
    };
    $params = new class($record) extends AsyncBatchFlow {
        public function prepAsync(SharedBag $shared): mixed
        {
            return [['id' => 1], ['id' => 2], ['id' => 3]];
        }
    };
    $parallel = new class($record) extends AsyncParallelBatchFlow {
        public function prepAsync(SharedBag $shared): mixed
        {
            return [['id' => 1], ['id' => 2], ['id' => 3]];
        }
    };

    $serial_bag = new SharedBag();
    $started = hrtime(true);
    $params->runAsync($serial_bag);
    $serial = hrtime(true) - $started;

    $parallel_bag = new SharedBag();
    $started = hrtime(true);
    $parallel->runAsync($parallel_bag);
    $overlapped = hrtime(true) - $started;

    expect($serial_bag->seen)->toBe([1, 2, 3])
        ->and($serial)->toBeGreaterThan(580_000_000)
        ->and($parallel_bag->seen)->toEqualCanonicalizing([1, 2, 3])
        ->and($overlapped)->toBeLessThan(400_000_000);
});

it('offers only the loop runtime, on the app\'s loop', function () {
    expect($this->app->get(AsyncRuntimeManager::class)->driver('loop'))->toBeInstanceOf(AsyncRuntime::class)
        ->and(fn () => $this->app->get(AsyncRuntimeManager::class)->driver('isolated'))->toThrow(InvalidArgumentException::class);
});
