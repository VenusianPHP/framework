<?php

namespace Tests\Database;

use Voyager\Database\Capsule\Manager as DB;
use Voyager\Database\Instrument\Factories\Factory;
use Voyager\Database\Instrument\Factories\HasFactory;
use Voyager\Database\Instrument\Model;
use Voyager\Database\Instrument\Model as Instrument;
use Voyager\Database\Instrument\Relations\BelongsTo;
use Voyager\Database\Instrument\Relations\HasOne;

/**
 * Get a database connection instance.
 *
 * @return \Voyager\Database\Connection
 */
function hasOneInverseConnection($connection = 'default')
{
    return Instrument::getConnectionResolver()->connection($connection);
}

/**
 * Get a schema builder instance.
 *
 * @return \Voyager\Database\Schema\Builder
 */
function hasOneInverseSchema($connection = 'default')
{
    return hasOneInverseConnection($connection)->getSchemaBuilder();
}

function hasOneInverseCreateSchema()
{
    hasOneInverseSchema()->create('test_parent', function ($table) {
        $table->increments('id');
        $table->timestamps();
    });

    hasOneInverseSchema()->create('test_child', function ($table) {
        $table->increments('id');
        $table->foreignId('parent_id')->unique();
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

    hasOneInverseCreateSchema();
});

afterEach(function () {
    hasOneInverseSchema()->drop('test_parent');
    hasOneInverseSchema()->drop('test_child');
});

test('has one inverse relation is properly set to parent when lazy loaded', function () {
    HasOneInverseChildModel::factory(5)->create();
    $models = HasOneInverseParentModel::all();

    foreach ($models as $parent) {
        $this->assertFalse($parent->relationLoaded('child'));
        $child = $parent->child;
        $this->assertTrue($child->relationLoaded('parent'));
        $this->assertSame($parent, $child->parent);
    }
});

test('has one inverse relation is properly set to parent when eager loaded', function () {
    HasOneInverseChildModel::factory(5)->create();

    $models = HasOneInverseParentModel::with('child')->get();

    foreach ($models as $parent) {
        $child = $parent->child;

        $this->assertTrue($child->relationLoaded('parent'));
        $this->assertSame($parent, $child->parent);
    }
});

test('has one inverse relation is properly set to parent when making', function () {
    $parent = HasOneInverseParentModel::create();

    $child = $parent->child()->make();

    $this->assertTrue($child->relationLoaded('parent'));
    $this->assertSame($parent, $child->parent);
});

test('has one inverse relation is properly set to parent when creating', function () {
    $parent = HasOneInverseParentModel::create();

    $child = $parent->child()->create();

    $this->assertTrue($child->relationLoaded('parent'));
    $this->assertSame($parent, $child->parent);
});

test('has one inverse relation is properly set to parent when creating quietly', function () {
    $parent = HasOneInverseParentModel::create();

    $child = $parent->child()->createQuietly();

    $this->assertTrue($child->relationLoaded('parent'));
    $this->assertSame($parent, $child->parent);
});

test('has one inverse relation is properly set to parent when force creating', function () {
    $parent = HasOneInverseParentModel::create();

    $child = $parent->child()->forceCreate();

    $this->assertTrue($child->relationLoaded('parent'));
    $this->assertSame($parent, $child->parent);
});

test('has one inverse relation is properly set to parent when saving', function () {
    $parent = HasOneInverseParentModel::create();
    $child = HasOneInverseChildModel::make();

    $this->assertFalse($child->relationLoaded('parent'));
    $parent->child()->save($child);

    $this->assertTrue($child->relationLoaded('parent'));
    $this->assertSame($parent, $child->parent);
});

test('has one inverse relation is properly set to parent when saving quietly', function () {
    $parent = HasOneInverseParentModel::create();
    $child = HasOneInverseChildModel::make();

    $this->assertFalse($child->relationLoaded('parent'));
    $parent->child()->saveQuietly($child);

    $this->assertTrue($child->relationLoaded('parent'));
    $this->assertSame($parent, $child->parent);
});

test('has one inverse relation is properly set to parent when updating', function () {
    $parent = HasOneInverseParentModel::create();
    $child = HasOneInverseChildModel::factory()->create();

    $this->assertTrue($parent->isNot($child->parent));

    $parent->child()->save($child);

    $this->assertTrue($parent->is($child->parent));
    $this->assertSame($parent, $child->parent);
});

class HasOneInverseParentModel extends Model
{
    use HasFactory;

    protected $table = 'test_parent';

    protected $fillable = ['id'];

    protected static function newFactory()
    {
        return new HasOneInverseParentModelFactory();
    }

    public function child(): HasOne
    {
        return $this->hasOne(HasOneInverseChildModel::class, 'parent_id')->inverse('parent');
    }
}

class HasOneInverseParentModelFactory extends Factory
{
    protected $model = HasOneInverseParentModel::class;

    public function definition()
    {
        return [];
    }
}

class HasOneInverseChildModel extends Model
{
    use HasFactory;

    protected $table = 'test_child';
    protected $fillable = ['id', 'parent_id'];

    protected static function newFactory()
    {
        return new HasOneInverseChildModelFactory();
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(HasOneInverseParentModel::class, 'parent_id');
    }
}

class HasOneInverseChildModelFactory extends Factory
{
    protected $model = HasOneInverseChildModel::class;

    public function definition()
    {
        return [
            'parent_id' => HasOneInverseParentModel::factory(),
        ];
    }
}
