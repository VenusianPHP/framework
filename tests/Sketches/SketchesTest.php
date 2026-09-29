<?php

use Voyager\Core\VenusianVoyager;
use Voyager\Vessel\ControlPanel;
use Voyager\Sketches\SketchRunner;
use Voyager\Sketches\SketchRegistry;
use Voyager\Sketches\DiscoverSketches;
use Voyager\Contracts\IOPools\Loop;
use Voyager\Core\Bootstrap\HandleExceptions;
use Voyager\Contracts\Sketches\Kernel as SketchKernel;
use Voyager\IOPools\MailHandlers\SketchMailHandler;
use Voyager\IOPools\MailHandlers\SignalMailHandler;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Venusian\Tests\Log\Fixtures\LogApp;
use Venusian\Tests\Sketches\Fixtures\PingMail;
use Venusian\Tests\Sketches\Fixtures\FrameSketch;
use Venusian\Tests\Sketches\Fixtures\NamedSketch;
use Venusian\Tests\Sketches\Fixtures\GreetingSketch;
use Venusian\Tests\Sketches\Fixtures\Discovered\FoundSketch;

describe('the registry and discovery', function () {
    beforeEach(fn () => $this->app = LogApp::boot());
    afterEach(fn () => LogApp::tearDown($this->app, $this));

    it('names a sketch by its attribute, or by its class name in kebab case', function () {
        $registry = $this->app->make(SketchRegistry::class);
        $registry->register(NamedSketch::class);
        $registry->register(FrameSketch::class);

        expect($registry->all())->toBe(['launch-pad' => NamedSketch::class, 'frame-sketch' => FrameSketch::class])
            ->and($registry->resolve('launch-pad'))->toBeInstanceOf(NamedSketch::class);
    });

    it('refuses a class that isn\'t a sketch, a name taken twice, and a name it doesn\'t know', function () {
        $registry = $this->app->make(SketchRegistry::class);
        $registry->register(NamedSketch::class);

        expect(fn () => $registry->register(PingMail::class))->toThrow(InvalidArgumentException::class, 'is not a sketch')
            ->and(fn () => $registry->register(NamedSketch::class))->toThrow(InvalidArgumentException::class, 'Sketch name [launch-pad] is already registered')
            ->and(fn () => $registry->resolve('nowhere'))->toThrow(InvalidArgumentException::class, 'Unknown sketch [nowhere].');
    });

    it('discovers the concrete sketches under a path, passing over the rest', function () {
        $root = __DIR__.'/Fixtures/Discovered';

        expect(DiscoverSketches::within([$root], 'Venusian\\Tests\\Sketches\\Fixtures\\Discovered', $root))->toBe([FoundSketch::class]);
    });
});

describe('the runner', function () {
    beforeEach(function () {
        $this->app = LogApp::boot();
        $this->app['config']->set('sketches.refresh_rate', 100);
        $this->mail = new SketchMailHandler();
        $registry = new Voyager\IOPools\ResourceRegistry();
        $this->loop = new Voyager\IOPools\EventLoop(
            $registry,
            new Voyager\IOPools\LoopWaiter($registry, new Voyager\IOPools\Waiter\StreamSelectWaiterBackend(), 5_000_000),
            new Voyager\IOPools\PromiseEngines\GuzzlePromiseEngine(),
            $this->mail,
        );
        $this->runner = new SketchRunner($this->loop, $this->app['config'], $this->mail);
    });
    afterEach(fn () => LogApp::tearDown($this->app, $this));

    it('calls loop() every frame at the refresh rate, whether or not there is mail', function () {
        $sketch = new FrameSketch(stop_after: 10);
        $started = hrtime(true);

        expect($this->runner->run($sketch))->toBe(0)
            ->and($sketch->frames)->toHaveCount(10)
            ->and($sketch->frames)->each->toBe([])
            ->and(hrtime(true) - $started)->toBeGreaterThan(90_000_000)->toBeLessThan(300_000_000);
    });

    it('hands each frame the mail that arrived since the last one, oldest first, and nothing twice', function () {
        $sketch = new FrameSketch(stop_after: 6);
        $this->loop->at(0.025, function () {
            $this->loop->post(new PingMail('first'));
            $this->loop->post(new PingMail('second'));
        });

        $this->runner->run($sketch);

        $delivered = array_values(array_filter($sketch->frames));

        expect($delivered)->toHaveCount(1)
            ->and(array_map(fn (PingMail $mail) => $mail->from, $delivered[0]))->toBe(['first', 'second'])
            ->and(array_sum(array_map('count', $sketch->frames)))->toBe(2);
    });

    it('uses the sketch\'s own refresh rate over the configured one', function () {
        $sketch = new FrameSketch(stop_after: 5, refresh_rate: 20);
        $started = hrtime(true);

        $this->runner->run($sketch);

        expect(hrtime(true) - $started)->toBeGreaterThan(230_000_000);
    });

    it('boots before the first frame and shuts down once, whether the sketch stops, throws or is stopped', function () {
        $stopping = new FrameSketch(stop_after: 1);
        $this->runner->run($stopping);

        $throwing = new FrameSketch(throws: true);
        expect(fn () => $this->runner->run($throwing))->toThrow(RuntimeException::class, 'frame failed');

        $stopped = new FrameSketch();
        $this->loop->at(0.05, fn () => $this->runner->stop(3));
        $status = $this->runner->run($stopped);

        expect($stopping->booted_before_first_frame)->toBeTrue()
            ->and([$stopping->shutdowns, $throwing->shutdowns, $stopped->shutdowns])->toBe([1, 1, 1])
            ->and($status)->toBe(3);
    });
});

describe('rocket', function () {
    beforeEach(function () {
        $this->app = VenusianVoyager::setup(dirname(__DIR__, 2))
            ->withSketches([GreetingSketch::class, NamedSketch::class])
            ->create();
        $this->app->loadEnvironmentFrom('tests/Log/Fixtures/testing.env');
    });
    afterEach(function () {
        HandleExceptions::flushState($this);
        ControlPanel::setInstance(null);
    });

    it('lists the sketches and runs one by name, through handleSketch()\'s kernel', function () {
        $kernel = $this->app->make(SketchKernel::class);
        $listing = new BufferedOutput();
        $run = new BufferedOutput();

        expect($kernel->handle(new ArgvInput(['rocket', 'list']), $listing))->toBe(0)
            ->and($listing->fetch())->toContain('greeting-sketch')->toContain('Greets, then stops.')->toContain('launch-pad')
            ->and($kernel->handle(new ArgvInput(['rocket', 'greeting-sketch']), $run))->toBe(0)
            ->and($run->fetch())->toContain('Hello from a sketch.');
    });

    it('builds the loop with the sketch mail handler on a rocket run, and the runner holds that handler', function () {
        $this->app->make(SketchKernel::class)->bootstrap();

        $handler = $this->app['mail-handler-mgr']->driver();

        expect($handler)->toBeInstanceOf(SketchMailHandler::class)
            ->and((fn () => $this->mail_handler)->call($this->app->get(Loop::class)))->toBe($handler)
            ->and((fn () => $this->mail)->call($this->app->get(SketchRunner::class)))->toBe($handler);
    });

    it('leaves computer runs dispatching mail as signals', function () {
        $app = LogApp::boot();

        expect($app['mail-handler-mgr']->driver())->toBeInstanceOf(SignalMailHandler::class);
    });
});
