<?php

namespace Tests\Database;

use Voyager\Database\Capsule\Manager as DB;
use Voyager\Database\Instrument\Collection;
use Voyager\Database\Instrument\Model as Instrument;
use Voyager\Database\Instrument\Relations\Relation;

/**
 * Get a database connection instance.
 *
 * @return \Voyager\Database\Connection
 */
function tablePrefixConnection($connection = 'default')
{
    return Instrument::getConnectionResolver()->connection($connection);
}

/**
 * Get a schema builder instance.
 *
 * @return \Voyager\Database\Schema\Builder
 */
function tablePrefixSchema($connection = 'default')
{
    return tablePrefixConnection($connection)->getSchemaBuilder();
}

function tablePrefixCreateSchema()
{
    tablePrefixSchema('default')->create('users', function ($table) {
        $table->increments('id');
        $table->string('email');
        $table->timestamps();
    });

    tablePrefixSchema('default')->create('friends', function ($table) {
        $table->integer('user_id');
        $table->integer('friend_id');
    });

    tablePrefixSchema('default')->create('posts', function ($table) {
        $table->increments('id');
        $table->integer('user_id');
        $table->integer('parent_id')->nullable();
        $table->string('name');
        $table->timestamps();
    });

    tablePrefixSchema('default')->create('photos', function ($table) {
        $table->increments('id');
        $table->morphs('imageable');
        $table->string('name');
        $table->timestamps();
    });
}

beforeEach(function () {
    $db = new DB;

    $db->addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    $db->bootInstrument();
    $db->setAsGlobal();

    Instrument::getConnectionResolver()->connection()->setTablePrefix('prefix_');

    tablePrefixCreateSchema();
});

afterEach(function () {
    foreach (['default'] as $connection) {
        tablePrefixSchema($connection)->drop('users');
        tablePrefixSchema($connection)->drop('friends');
        tablePrefixSchema($connection)->drop('posts');
        tablePrefixSchema($connection)->drop('photos');
    }

    Relation::morphMap([], false);
});

test('basic model hydration', function () {
    InstrumentTestUser::create(['email' => 'taylorotwell@gmail.com']);
    InstrumentTestUser::create(['email' => 'abigailotwell@gmail.com']);

    $models = InstrumentTestUser::fromQuery('SELECT * FROM prefix_users WHERE email = ?', ['abigailotwell@gmail.com']);

    $this->assertInstanceOf(Collection::class, $models);
    $this->assertInstanceOf(InstrumentTestUser::class, $models[0]);
    $this->assertSame('abigailotwell@gmail.com', $models[0]->email);
    $this->assertCount(1, $models);
});

test('table prefix with cloned connection', function () {
    $originalConnection = tablePrefixConnection();
    $originalPrefix = $originalConnection->getTablePrefix();

    $clonedConnection = clone $originalConnection;
    $clonedConnection->setTablePrefix('cloned_');

    $this->assertSame($originalPrefix, $originalConnection->getTablePrefix());
    $this->assertSame('cloned_', $clonedConnection->getTablePrefix());

    $clonedConnection->getSchemaBuilder()->create('test_table', function ($table) {
        $table->increments('id');
        $table->string('name');
    });

    $this->assertTrue($clonedConnection->getSchemaBuilder()->hasTable('test_table'));
    $query = $clonedConnection->table('test_table')->toSql();
    $this->assertStringContainsString('cloned_test_table', $query);

    $clonedConnection->getSchemaBuilder()->drop('test_table');
});

test('query grammar uses correct prefix after cloning', function () {
    $originalConnection = tablePrefixConnection();

    $clonedConnection = clone $originalConnection;
    $clonedConnection->setTablePrefix('new_prefix_');

    $selectSql = $clonedConnection->table('users')->toSql();
    $this->assertStringContainsString('new_prefix_users', $selectSql);

    $insertSql = $clonedConnection->table('users')->toSql();
    $this->assertStringContainsString('new_prefix_users', $insertSql);

    $updateSql = $clonedConnection->table('users')->where('id', 1)->toSql();
    $this->assertStringContainsString('new_prefix_users', $updateSql);

    $deleteSql = $clonedConnection->table('users')->where('id', 1)->toSql();
    $this->assertStringContainsString('new_prefix_users', $deleteSql);

    $originalSql = $originalConnection->table('users')->toSql();
    $this->assertStringContainsString('prefix_users', $originalSql);
    $this->assertStringNotContainsString('new_prefix_users', $originalSql);
});
