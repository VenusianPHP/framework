<?php

use Voyager\Core\Console\AboutCommand;
use Voyager\Contracts\Console\Kernel;
use Venusian\Tests\Log\Fixtures\LogApp;
use Voyager\IOPools\Waiter\KqueueWaiterBackend;
use Voyager\IOPools\Waiter\EpollWaiterBackend;
use Voyager\IOPools\Waiter\StreamSelectWaiterBackend;
use Voyager\Core\Extensions\FirstPartyExtension;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Venusian\Tests\Core\Fixtures\AboutRobotArm;

beforeEach(function () {
    $this->app = LogApp::boot();

    $this->about = function (string ...$options): array {
        $output = new BufferedOutput();
        $status = $this->app->make(Kernel::class)->handle(new ArgvInput(['computer', 'about', ...$options]), $output);

        return [$status, $output->fetch()];
    };
});

afterEach(function () {
    AboutCommand::flushState();
    LogApp::tearDown($this->app, $this);
});

it('lists the framework sections in order, then the ones packages add', function () {
    AboutCommand::add('Robot Arm', ['Joints' => '6']);

    [$status, $output] = ($this->about)();

    $order = array_map(fn (string $section) => strpos($output, $section.' ..'), ['Environment', 'Cache', 'Drivers', 'Event Loop', 'Extensions', 'Sketches', 'Robot Arm']);

    expect($status)->toBe(0)
        ->and($order)->not->toContain(false)
        ->and($order)->toBe(array_values(array_unique($order)))
        ->and($order)->toEqual((function (array $sorted) { sort($sorted); return $sorted; })($order))
        ->and($output)->toContain('Venusian Version')->toContain('Signals')->toContain('Waiter Backend');
});

it('takes package data as pairs, a label and value, a resolver, or an invokable class', function () {
    AboutCommand::add('Robot Arm', ['Joints' => '6']);
    AboutCommand::add('Robot Arm', 'Gripper', 'parallel');
    AboutCommand::add('Robot Arm', fn () => ['Bus' => 'i2c']);
    AboutCommand::add('Robot Arm', AboutRobotArm::class);

    [, $output] = ($this->about)('--json', '--only=robot-arm');

    expect(json_decode($output, true))->toBe(['robot_arm' => ['joints' => '6', 'gripper' => 'parallel', 'bus' => 'i2c', 'controller' => 'pca9685']]);
});

it('filters sections by --only, however the section name is written', function () {
    foreach (['event-loop', 'Event Loop', 'event_loop'] as $only) {
        expect(array_keys(json_decode(($this->about)('--json', '--only='.$only)[1], true)))->toBe(['event_loop']);
    }

    expect(array_keys(json_decode(($this->about)('--json', '--only=drivers,extensions')[1], true)))->toBe(['drivers', 'extensions']);
});

it('reports the drivers auto resolves to, the pools and the first-party extensions', function () {
    $this->app['config']->set('io-pools.pool_workers.process', ['enabled' => true, 'max_workers' => 3]);
    $this->app['config']->set('io-pools.pool_workers.threads', ['enabled' => false, 'max_workers' => 4]);
    $this->app['config']->set('io-pools.pool_waiters.default', 'auto');
    $this->app['config']->set('http.async.default', 'auto');

    $json = json_decode(($this->about)('--json', '--only=event-loop,drivers,extensions')[1], true);
    [, $detail] = ($this->about)('--only=event-loop');

    $extensions = [];
    foreach (FirstPartyExtension::cases() as $extension) {
        $extensions[$extension->extension()] = extension_loaded($extension->extension()) ? phpversion($extension->extension()) : false;
    }

    expect($json['event_loop']['waiter_backend'])->toBe($this->app->get('waiter-backend-mgr')->autoDriver())
        ->and($json['event_loop']['process_workers'])->toBe(3)
        ->and($json['event_loop']['thread_workers'])->toBeFalse()
        ->and($json['drivers']['async_http'])->toBe(extension_loaded('pcurl') ? 'pcurl' : 'curl')
        ->and($json['extensions'])->toBe($extensions)
        ->and($detail)->toContain('auto / '.$json['event_loop']['waiter_backend'])->toContain('ENABLED / 3 max');
});

it('picks the same waiter backend for auto that it reports', function () {
    $manager = $this->app->get('waiter-backend-mgr');

    expect($manager->driver('auto'))->toBeInstanceOf(match ($manager->autoDriver()) {
        'epoll' => EpollWaiterBackend::class,
        'kqueue' => KqueueWaiterBackend::class,
        'select' => StreamSelectWaiterBackend::class,
    });
});

it('reads the Composer version through the app\'s composer binding', function () {
    expect($this->app['composer']->getVersion())->toMatch('/^\d+\.\d+\.\d+/');
});
