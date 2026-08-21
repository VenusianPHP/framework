<?php

use Voyager\Database\Migrations\MigrationCreator;
use Voyager\Filesystem\Filesystem;
use Mockery as m;

function migrationCreatorGetCreatorClosure()
{
    return function () {
        $files = m::mock(Filesystem::class);
        $customStubs = 'stubs';

        return $this->getMockBuilder(MigrationCreator::class)
            ->onlyMethods(['getDatePrefix'])
            ->setConstructorArgs([$files, $customStubs])
            ->getMock();
    };
}

test('basic create method stores migration file', function () {
    $creator = migrationCreatorGetCreatorClosure()->call($this);

    $creator->expects($this->any())->method('getDatePrefix')->willReturn('foo');
    $creator->getFilesystem()->shouldReceive('exists')->once()->with('stubs/migration.stub')->andReturn(false);
    $creator->getFilesystem()->shouldReceive('get')->once()->with($creator->stubPath().'/migration.stub')->andReturn('return new class');
    $creator->getFilesystem()->shouldReceive('ensureDirectoryExists')->once()->with('foo');
    $creator->getFilesystem()->shouldReceive('put')->once()->with('foo/foo_create_bar.php', 'return new class');
    $creator->getFilesystem()->shouldReceive('glob')->once()->with('foo/*.php')->andReturn(['foo/foo_create_bar.php']);
    $creator->getFilesystem()->shouldReceive('requireOnce')->once()->with('foo/foo_create_bar.php');

    $creator->create('create_bar', 'foo');
});

test('basic create method calls post create hooks', function () {
    $table = 'baz';

    $creator = migrationCreatorGetCreatorClosure()->call($this);
    unset($_SERVER['__migration.creator.table'], $_SERVER['__migration.creator.path']);
    $creator->afterCreate(function ($table, $path) {
        $_SERVER['__migration.creator.table'] = $table;
        $_SERVER['__migration.creator.path'] = $path;
    });

    $creator->expects($this->any())->method('getDatePrefix')->willReturn('foo');
    $creator->getFilesystem()->shouldReceive('exists')->once()->with('stubs/migration.update.stub')->andReturn(false);
    $creator->getFilesystem()->shouldReceive('get')->once()->with($creator->stubPath().'/migration.update.stub')->andReturn('return new class DummyTable');
    $creator->getFilesystem()->shouldReceive('ensureDirectoryExists')->once()->with('foo');
    $creator->getFilesystem()->shouldReceive('put')->once()->with('foo/foo_create_bar.php', 'return new class baz');
    $creator->getFilesystem()->shouldReceive('glob')->once()->with('foo/*.php')->andReturn(['foo/foo_create_bar.php']);
    $creator->getFilesystem()->shouldReceive('requireOnce')->once()->with('foo/foo_create_bar.php');

    $creator->create('create_bar', 'foo', $table);

    expect($_SERVER['__migration.creator.table'])->toEqual($table);
    expect($_SERVER['__migration.creator.path'])->toEqual('foo/foo_create_bar.php');

    unset($_SERVER['__migration.creator.table'], $_SERVER['__migration.creator.path']);
});

test('table update migration stores migration file', function () {
    $creator = migrationCreatorGetCreatorClosure()->call($this);
    $creator->expects($this->any())->method('getDatePrefix')->willReturn('foo');
    $creator->getFilesystem()->shouldReceive('exists')->once()->with('stubs/migration.update.stub')->andReturn(false);
    $creator->getFilesystem()->shouldReceive('get')->once()->with($creator->stubPath().'/migration.update.stub')->andReturn('return new class DummyTable');
    $creator->getFilesystem()->shouldReceive('ensureDirectoryExists')->once()->with('foo');
    $creator->getFilesystem()->shouldReceive('put')->once()->with('foo/foo_create_bar.php', 'return new class baz');
    $creator->getFilesystem()->shouldReceive('glob')->once()->with('foo/*.php')->andReturn(['foo/foo_create_bar.php']);
    $creator->getFilesystem()->shouldReceive('requireOnce')->once()->with('foo/foo_create_bar.php');

    $creator->create('create_bar', 'foo', 'baz');
});

test('table creation migration stores migration file', function () {
    $creator = migrationCreatorGetCreatorClosure()->call($this);
    $creator->expects($this->any())->method('getDatePrefix')->willReturn('foo');
    $creator->getFilesystem()->shouldReceive('exists')->once()->with('stubs/migration.create.stub')->andReturn(false);
    $creator->getFilesystem()->shouldReceive('get')->once()->with($creator->stubPath().'/migration.create.stub')->andReturn('return new class DummyTable');
    $creator->getFilesystem()->shouldReceive('ensureDirectoryExists')->once()->with('foo');
    $creator->getFilesystem()->shouldReceive('put')->once()->with('foo/foo_create_bar.php', 'return new class baz');
    $creator->getFilesystem()->shouldReceive('glob')->once()->with('foo/*.php')->andReturn(['foo/foo_create_bar.php']);
    $creator->getFilesystem()->shouldReceive('requireOnce')->once()->with('foo/foo_create_bar.php');

    $creator->create('create_bar', 'foo', 'baz', true);
});

test('table update migration wont create duplicate class', function () {
    $creator = migrationCreatorGetCreatorClosure()->call($this);

    $creator->getFilesystem()->shouldReceive('glob')->once()->with('foo/*.php')->andReturn(['foo/foo_create_bar.php']);
    $creator->getFilesystem()->shouldReceive('requireOnce')->once()->with('foo/foo_create_bar.php');

    $creator->create('migration_creator_fake_migration', 'foo');
})->throws(InvalidArgumentException::class, 'A MigrationCreatorFakeMigration class already exists.');
