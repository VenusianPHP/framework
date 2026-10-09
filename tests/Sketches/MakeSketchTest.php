<?php

use Voyager\Sketches\Sketch;
use Voyager\Sketches\SketchRegistry;
use Voyager\Sketches\DiscoverSketches;
use Voyager\Filesystem\Filesystem;
use Voyager\Contracts\Console\Kernel;
use Venusian\Tests\Log\Fixtures\LogApp;
use Voyager\Contracts\Sketches\SketchLoopResult;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Voyager\Contracts\Sketches\Kernel as SketchKernel;

// Each test writes into an app root of its own, under a namespace of its own, so no
// generated class collides with another test's or with this repository's.
beforeEach(function () {
    $this->app = LogApp::boot();
    $this->root = sys_get_temp_dir().'/venusian-make-sketch-'.bin2hex(random_bytes(6));
    $this->namespace = 'Scratch'.bin2hex(random_bytes(6)).'\\';
    mkdir($this->root);

    $this->autoload = function (string $class) {
        if (str_starts_with($class, $this->namespace)) {
            $file = $this->root.'/'.str_replace('\\', '/', substr($class, strlen($this->namespace))).'.php';

            if (is_file($file)) {
                require $file;
            }
        }
    };
    spl_autoload_register($this->autoload);

    $this->app->make(SketchKernel::class)->discoverUsing($this->namespace, $this->root);

    $this->make = function (string ...$arguments): array {
        $output = new BufferedOutput();
        $status = $this->app->make(Kernel::class)->handle(new ArgvInput(['computer', 'make:sketch', ...$arguments]), $output);

        return [$status, $output->fetch()];
    };
});

afterEach(function () {
    spl_autoload_unregister($this->autoload);
    (new Filesystem)->deleteDirectory($this->root);
    LogApp::tearDown($this->app, $this);
});

it('writes an empty sketch into the scanned sketch path, which discovery finds and runs', function () {
    $this->app->make(SketchKernel::class)->addSketchPaths([$this->root.'/Runner/../Runner/Sketches']);

    [$status, $output] = ($this->make)('BlinkLed');

    $class = $this->namespace.'Runner\\Sketches\\BlinkLed';
    $found = DiscoverSketches::within([$this->root.'/Runner/Sketches'], $this->namespace, $this->root);

    expect($status)->toBe(0)
        ->and($output)->toContain('created successfully')->not->toContain('rocket will not find')
        ->and(file_get_contents($this->root.'/Runner/Sketches/BlinkLed.php'))
            ->toContain('use Voyager\Sketches\Sketch;')
            ->toContain('class BlinkLed extends Sketch')
        ->and($found)->toBe([$class])
        ->and(get_parent_class($class))->toBe(Sketch::class)
        ->and(SketchRegistry::nameFor($class))->toBe('blink-led')
        ->and((new $class)->loop())->toBe(SketchLoopResult::CONTINUE);
});

it('prefers a path the app named over app/Console/Sketches, which setup() always scans', function () {
    $this->app->useAppPath($this->root);
    $this->app->make(SketchKernel::class)->addSketchPaths([$this->root.'/Console/Sketches', $this->root.'/Runner/Sketches']);

    [$status] = ($this->make)('Blink');

    expect($status)->toBe(0)
        ->and(is_file($this->root.'/Runner/Sketches/Blink.php'))->toBeTrue()
        ->and(is_dir($this->root.'/Console'))->toBeFalse();
});

it('extends the app\'s own abstract Sketch when the sketch namespace has one', function () {
    $this->app->make(SketchKernel::class)->addSketchPaths([$this->root.'/Runner/Sketches']);
    mkdir($this->root.'/Runner/Sketches', 0777, true);
    file_put_contents($this->root.'/Runner/Sketches/Sketch.php', sprintf(
        "<?php\n\nnamespace %sRunner\\Sketches;\n\nabstract class Sketch extends \\Voyager\\Sketches\\Sketch {}\n",
        $this->namespace,
    ));

    [$status] = ($this->make)('Nested/Pulse');

    $class = $this->namespace.'Runner\\Sketches\\Nested\\Pulse';
    $source = file_get_contents($this->root.'/Runner/Sketches/Nested/Pulse.php');

    expect($status)->toBe(0)
        ->and($source)->toContain('use '.$this->namespace.'Runner\Sketches\Sketch;')->toContain('class Pulse extends Sketch')
        ->and(get_parent_class($class))->toBe($this->namespace.'Runner\\Sketches\\Sketch');

    [$status] = ($this->make)('Wave');
    $source = file_get_contents($this->root.'/Runner/Sketches/Wave.php');

    expect($status)->toBe(0)
        ->and($source)->not->toContain('Runner\Sketches\Sketch;')->toContain('class Wave extends Sketch')
        ->and(get_parent_class($this->namespace.'Runner\\Sketches\\Wave'))->toBe($this->namespace.'Runner\\Sketches\\Sketch');
});

it('aliases the framework base when the sketch itself is named Sketch', function () {
    $this->app->make(SketchKernel::class)->addSketchPaths([$this->root.'/Sketches']);

    [$status] = ($this->make)('Sketch');

    $class = $this->namespace.'Sketches\\Sketch';

    expect($status)->toBe(0)
        ->and(file_get_contents($this->root.'/Sketches/Sketch.php'))
            ->toContain('use Voyager\Sketches\Sketch as BaseSketch;')
            ->toContain('class Sketch extends BaseSketch')
        ->and(get_parent_class($class))->toBe(Sketch::class);
});

it('falls back to Console/Sketches and says so when no scanned path is under the app', function () {
    [$status, $output] = ($this->make)('Orphan');

    expect($status)->toBe(0)
        ->and(is_file($this->root.'/Console/Sketches/Orphan.php'))->toBeTrue()
        ->and(preg_replace('/\s+/', ' ', $output))->toContain('rocket will not find')->toContain('withSketches()');
});

it('leaves an existing sketch untouched unless forced', function () {
    $this->app->make(SketchKernel::class)->addSketchPaths([$this->root.'/Sketches']);
    ($this->make)('Blink');
    file_put_contents($path = $this->root.'/Sketches/Blink.php', 'edited');

    [, $output] = ($this->make)('Blink');
    $kept = file_get_contents($path);
    ($this->make)('Blink', '--force');

    expect($output)->toContain('Sketch already exists.')
        ->and($kept)->toBe('edited')
        ->and(file_get_contents($path))->toContain('class Blink extends Sketch');
});
