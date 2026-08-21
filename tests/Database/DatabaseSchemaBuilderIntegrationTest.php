<?php

use Voyager\Vessel\Vessel;
use Voyager\Database\Capsule\Manager as DB;
use Voyager\Database\Schema\Blueprint;
use Voyager\MagicAliases\MagicAlias;

function databaseSchemaBuilderIntegrationSchemaBuilder($db)
{
    return $db->connection()->getSchemaBuilder();
}

beforeEach(function () {
    $this->db = $db = new DB;

    $db->addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    $db->setAsGlobal();

    $container = new Vessel;
    $container->instance('db', $db->getDatabaseManager());
    MagicAlias::setMagicAliasApplication($container);
});

afterEach(function () {
    MagicAlias::clearResolvedInstances();
    MagicAlias::setMagicAliasApplication(null);
});

test('has column with table prefix', function () {
    $this->db->connection()->setTablePrefix('test_');

    $this->db->connection()->getSchemaBuilder()->create('table1', function (Blueprint $table) {
        $table->integer('id');
        $table->string('name');
    });

    $this->assertTrue($this->db->connection()->getSchemaBuilder()->hasColumn('table1', 'name'));
});

test('has column and index with prefix index disabled', function () {
    $this->db->addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => 'example_',
        'prefix_indexes' => false,
    ]);

    databaseSchemaBuilderIntegrationSchemaBuilder($this->db)->create('table1', function (Blueprint $table) {
        $table->integer('id');
        $table->string('name')->index();
    });

    $this->assertTrue(databaseSchemaBuilderIntegrationSchemaBuilder($this->db)->hasIndex('table1', 'table1_name_index'));
});

test('has column and index with prefix index enabled', function () {
    $this->db->addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => 'example_',
        'prefix_indexes' => true,
    ]);

    databaseSchemaBuilderIntegrationSchemaBuilder($this->db)->create('table1', function (Blueprint $table) {
        $table->integer('id');
        $table->string('name')->index();
    });

    $this->assertTrue(databaseSchemaBuilderIntegrationSchemaBuilder($this->db)->hasIndex('table1', 'example_table1_name_index'));
});

test('drop column with table prefix', function () {
    $this->db->connection()->setTablePrefix('test_');

    databaseSchemaBuilderIntegrationSchemaBuilder($this->db)->create('pandemic_table', function (Blueprint $table) {
        $table->integer('id');
        $table->string('stay_home');
        $table->string('covid19');
        $table->string('wear_mask');
    });

    // drop single columns
    $this->assertTrue(databaseSchemaBuilderIntegrationSchemaBuilder($this->db)->hasColumn('pandemic_table', 'stay_home'));
    databaseSchemaBuilderIntegrationSchemaBuilder($this->db)->dropColumns('pandemic_table', 'stay_home');
    $this->assertFalse(databaseSchemaBuilderIntegrationSchemaBuilder($this->db)->hasColumn('pandemic_table', 'stay_home'));

    // drop multiple columns
    $this->assertTrue(databaseSchemaBuilderIntegrationSchemaBuilder($this->db)->hasColumn('pandemic_table', 'covid19'));
    databaseSchemaBuilderIntegrationSchemaBuilder($this->db)->dropColumns('pandemic_table', ['covid19', 'wear_mask']);
    $this->assertFalse(databaseSchemaBuilderIntegrationSchemaBuilder($this->db)->hasColumn('pandemic_table', 'wear_mask'));
    $this->assertFalse(databaseSchemaBuilderIntegrationSchemaBuilder($this->db)->hasColumn('pandemic_table', 'covid19'));
});
