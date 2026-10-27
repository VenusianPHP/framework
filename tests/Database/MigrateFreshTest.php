<?php

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Venusian\Tests\Database\Fixtures\TestingHost;
use Venusian\Tests\Database\Fixtures\WidgetSeeder;
use Voyager\Contracts\Console\Kernel;

beforeEach(function () {
    $this->file = sys_get_temp_dir().'/venusian-fresh-'.bin2hex(random_bytes(5)).'.sqlite';
    touch($this->file);
    $this->host = (new class($this->file) extends TestingHost {})->begin();
    TestingHost::$migrated = 0;
});

afterEach(function () {
    $this->host->end($this);
    @unlink($this->file);
});

/** Runs a console command through the kernel; returns [exit status, output]. */
function freshCommand($app, array $input): array
{
    $input = new ArrayInput(['--force' => true, ...$input]);
    $input->setInteractive(false);
    $status = $app->make(Kernel::class)->handle($input, $output = new BufferedOutput);

    return [$status, $output->fetch()];
}

it('drops everything and migrates again on a database that already has tables', function () {
    $app = $this->host->app;
    expect(freshCommand($app, ['command' => 'migrate'])[0])->toBe(0);
    $this->host->db()->table('widgets')->insert(['name' => 'old']);
    $this->host->db()->getSchemaBuilder()->create('stray', fn ($table) => $table->id());

    [$status, $output] = freshCommand($app, ['command' => 'migrate:fresh']);

    expect($status)->toBe(0, $output)
        ->and(TestingHost::$migrated)->toBe(2)
        ->and($this->host->db()->table('widgets')->count())->toBe(0)
        ->and($this->host->db()->getSchemaBuilder()->hasTable('stray'))->toBeFalse();
});

it('seeds after migrating fresh', function () {
    [$status, $output] = freshCommand($this->host->app, ['command' => 'migrate:fresh', '--seeder' => WidgetSeeder::class]);

    expect($status)->toBe(0, $output)
        ->and($this->host->db()->table('widgets')->pluck('name')->all())->toBe(['seeded']);
});

it('runs db:seed on its own', function () {
    freshCommand($this->host->app, ['command' => 'migrate']);

    [$status, $output] = freshCommand($this->host->app, ['command' => 'db:seed', '--class' => WidgetSeeder::class]);

    expect($status)->toBe(0, $output)
        ->and($this->host->db()->table('widgets')->count())->toBe(1);
});
