<?php

namespace Tests\Database;

use Closure;
use Voyager\Contracts\Events\Dispatcher as DispatcherContract;
use Voyager\Database\Capsule\Manager as DB;
use Voyager\Database\Console\PruneCommand;
use Voyager\Database\Events\ModelPruningFinished;
use Voyager\Database\Events\ModelPruningStarting;
use Voyager\Database\Events\ModelsPruned;
use Voyager\Events\Dispatcher;
use Voyager\System\Application;
use Mockery as m;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

function pruneCommandArtisan($arguments)
{
    $input = new ArrayInput($arguments);
    $output = new BufferedOutput;

    tap(new PruneCommand())
        ->setVenusian(Application::getInstance())
        ->run($input, $output);

    return $output;
}

beforeEach(function () {
    Application::setInstance($container = new Application(__DIR__.'/Pruning'));

    Closure::bind(
        fn () => $this->namespace = 'Voyager\\Tests\\Database\\Pruning\\',
        $container,
        Application::class,
    )();

    $container->useAppPath(__DIR__.'/Pruning');

    $container->singleton(DispatcherContract::class, function () {
        return new Dispatcher();
    });

    $container->alias(DispatcherContract::class, 'events');
});

afterEach(function () {
    Application::setInstance(null);
});

test('prunable model and except with each other', function () {
    pruneCommandArtisan([
        '--model' => Pruning\Models\PrunableTestModelWithPrunableRecords::class,
        '--except' => Pruning\Models\PrunableTestModelWithPrunableRecords::class,
    ]);
})->throws(\InvalidArgumentException::class, 'The --models and --except options cannot be combined.');

test('prunable model with prunable records', function () {
    $output = pruneCommandArtisan(['--model' => Pruning\Models\PrunableTestModelWithPrunableRecords::class]);

    $output = $output->fetch();

    $this->assertStringContainsString(
        'Tests\Database\Pruning\Models\PrunableTestModelWithPrunableRecords',
        $output,
    );

    $this->assertStringContainsString(
        '10 records',
        $output,
    );

    $this->assertStringContainsString(
        'Tests\Database\Pruning\Models\PrunableTestModelWithPrunableRecords',
        $output,
    );

    $this->assertStringContainsString(
        '20 records',
        $output,
    );
});

test('prunable test model without prunable records', function () {
    $output = pruneCommandArtisan(['--model' => Pruning\Models\PrunableTestModelWithoutPrunableRecords::class]);

    $this->assertStringContainsString(
        'No prunable [Tests\Database\Pruning\Models\PrunableTestModelWithoutPrunableRecords] records found.',
        $output->fetch()
    );
});

test('prunable soft deleted model with prunable records', function () {
    $db = new DB;
    $db->addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);
    $db->bootInstrument();
    $db->setAsGlobal();
    DB::connection('default')->getSchemaBuilder()->create('prunables', function ($table) {
        $table->string('value')->nullable();
        $table->datetime('deleted_at')->nullable();
    });
    DB::connection('default')->table('prunables')->insert([
        ['value' => 1, 'deleted_at' => null],
        ['value' => 2, 'deleted_at' => '2021-12-01 00:00:00'],
        ['value' => 3, 'deleted_at' => null],
        ['value' => 4, 'deleted_at' => '2021-12-02 00:00:00'],
    ]);

    $output = pruneCommandArtisan(['--model' => Pruning\Models\PrunableTestSoftDeletedModelWithPrunableRecords::class]);

    $output = $output->fetch();

    $this->assertStringContainsString(
        'Tests\Database\Pruning\Models\PrunableTestSoftDeletedModelWithPrunableRecords',
        $output,
    );

    $this->assertStringContainsString(
        '2 records',
        $output,
    );

    $this->assertEquals(2, Pruning\Models\PrunableTestSoftDeletedModelWithPrunableRecords::withTrashed()->count());
});

test('non prunable test', function () {
    $output = pruneCommandArtisan(['--model' => Pruning\Models\NonPrunableTestModel::class]);

    $this->assertStringContainsString(
        'No prunable [Tests\Database\Pruning\Models\NonPrunableTestModel] records found.',
        $output->fetch(),
    );
});

test('non prunable test with a trait', function () {
    $output = pruneCommandArtisan(['--model' => Pruning\Models\NonPrunableTrait::class]);

    $this->assertStringContainsString(
        'No prunable models found.',
        $output->fetch(),
    );
});

test('non model files are ignored test', function () {
    $output = pruneCommandArtisan(['--path' => 'Models']);

    $output = $output->fetch();

    $this->assertStringNotContainsString(
        'No prunable [Tests\Database\Pruning\Models\AbstractPrunableModel] records found.',
        $output,
    );

    $this->assertStringNotContainsString(
        'No prunable [Tests\Database\Pruning\Models\SomeClass] records found.',
        $output,
    );

    $this->assertStringNotContainsString(
        'No prunable [Tests\Database\Pruning\Models\SomeEnum] records found.',
        $output,
    );
});

test('the command may be pretended', function () {
    $db = new DB;
    $db->addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);
    $db->bootInstrument();
    $db->setAsGlobal();
    DB::connection('default')->getSchemaBuilder()->create('prunables', function ($table) {
        $table->string('name')->nullable();
        $table->string('value')->nullable();
    });
    DB::connection('default')->table('prunables')->insert([
        ['name' => 'zain', 'value' => 1],
        ['name' => 'patrice', 'value' => 2],
        ['name' => 'amelia', 'value' => 3],
        ['name' => 'stuart', 'value' => 4],
        ['name' => 'bello', 'value' => 5],
    ]);

    $output = pruneCommandArtisan([
        '--model' => Pruning\Models\PrunableTestModelWithPrunableRecords::class,
        '--pretend' => true,
    ]);

    $this->assertStringContainsString(
        '3 [Tests\Database\Pruning\Models\PrunableTestModelWithPrunableRecords] records will be pruned.',
        $output->fetch(),
    );

    $this->assertEquals(5, Pruning\Models\PrunableTestModelWithPrunableRecords::count());
});

test('the command may be pretended on soft deleted model', function () {
    $db = new DB;
    $db->addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);
    $db->bootInstrument();
    $db->setAsGlobal();
    DB::connection('default')->getSchemaBuilder()->create('prunables', function ($table) {
        $table->string('value')->nullable();
        $table->datetime('deleted_at')->nullable();
    });
    DB::connection('default')->table('prunables')->insert([
        ['value' => 1, 'deleted_at' => null],
        ['value' => 2, 'deleted_at' => '2021-12-01 00:00:00'],
        ['value' => 3, 'deleted_at' => null],
        ['value' => 4, 'deleted_at' => '2021-12-02 00:00:00'],
    ]);

    $output = pruneCommandArtisan([
        '--model' => Pruning\Models\PrunableTestSoftDeletedModelWithPrunableRecords::class,
        '--pretend' => true,
    ]);

    $this->assertStringContainsString(
        '2 [Tests\Database\Pruning\Models\PrunableTestSoftDeletedModelWithPrunableRecords] records will be pruned.',
        $output->fetch(),
    );

    $this->assertEquals(4, Pruning\Models\PrunableTestSoftDeletedModelWithPrunableRecords::withTrashed()->count());
});

test('the command dispatches events', function () {
    $dispatcher = m::mock(DispatcherContract::class);

    $dispatcher->shouldReceive('dispatch')->once()->withArgs(function ($event) {
        return get_class($event) === ModelPruningStarting::class &&
            $event->models === [Pruning\Models\PrunableTestModelWithPrunableRecords::class];
    });
    $dispatcher->shouldReceive('listen')->once()->with(ModelsPruned::class, m::type(Closure::class));
    $dispatcher->shouldReceive('dispatch')->twice()->with(m::type(ModelsPruned::class));
    $dispatcher->shouldReceive('dispatch')->once()->withArgs(function ($event) {
        return get_class($event) === ModelPruningFinished::class &&
            $event->models === [Pruning\Models\PrunableTestModelWithPrunableRecords::class];
    });
    $dispatcher->shouldReceive('forget')->once()->with(ModelsPruned::class);

    Application::getInstance()->instance(DispatcherContract::class, $dispatcher);

    pruneCommandArtisan(['--model' => Pruning\Models\PrunableTestModelWithPrunableRecords::class]);
});
