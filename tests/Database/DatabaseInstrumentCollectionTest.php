<?php

namespace Tests\Database;

use Voyager\Database\Capsule\Manager as DB;
use Voyager\Database\Instrument\Builder;
use Voyager\Database\Instrument\Collection;
use Voyager\Database\Instrument\Model;
use Voyager\Database\Instrument\Model as Instrument;
use Voyager\Database\Instrument\ModelNotFoundException;
use Voyager\NutsAndBolts\Collection as BaseCollection;
use LogicException;
use Mockery as m;
use Composer\InstalledVersions;
use stdClass;

/**
 * Get a database connection instance.
 *
 * @return \Voyager\Database\ConnectionInterface
 */
function dbCollectionConnection()
{
    return Instrument::getConnectionResolver()->connection();
}

/**
 * Get a schema builder instance.
 *
 * @return \Voyager\Database\Schema\Builder
 */
function dbCollectionSchema()
{
    return dbCollectionConnection()->getSchemaBuilder();
}

function dbCollectionCreateSchema()
{
    dbCollectionSchema()->create('users', function ($table) {
        $table->increments('id');
        $table->string('email')->unique();
    });

    dbCollectionSchema()->create('articles', function ($table) {
        $table->increments('id');
        $table->integer('user_id');
        $table->string('title');
    });

    dbCollectionSchema()->create('comments', function ($table) {
        $table->increments('id');
        $table->integer('article_id');
        $table->string('content');
    });
}

function dbCollectionSeedData()
{
    InstrumentTestUserModel::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);

    InstrumentTestArticleModel::query()->insert([
        ['user_id' => 1, 'title' => 'Another title'],
        ['user_id' => 1, 'title' => 'Another title'],
        ['user_id' => 1, 'title' => 'Another title'],
    ]);

    InstrumentTestCommentModel::query()->insert([
        ['article_id' => 1, 'content' => 'Another comment'],
        ['article_id' => 2, 'content' => 'Another comment'],
    ]);
}

beforeEach(function () {
    $db = new DB;

    $db->addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    $db->bootInstrument();
    $db->setAsGlobal();

    dbCollectionCreateSchema();
});

afterEach(function () {
    dbCollectionSchema()->drop('users');
    dbCollectionSchema()->drop('articles');
    dbCollectionSchema()->drop('comments');
});

test('adding items to collection', function () {
    $c = new Collection(['foo']);
    $c->add('bar')->add('baz');

    expect($c->all())->toEqual(['foo', 'bar', 'baz']);
});

test('getting max items from collection', function () {
    $c = new Collection([(object) ['foo' => 10], (object) ['foo' => 20]]);

    expect($c->max('foo'))->toEqual(20);
});

test('getting min items from collection', function () {
    $c = new Collection([(object) ['foo' => 10], (object) ['foo' => 20]]);

    expect($c->min('foo'))->toEqual(10);
});

test('contains with multiple arguments', function () {
    $c = new Collection([['id' => 1], ['id' => 2]]);

    expect($c->contains('id', 1))->toBeTrue()
        ->and($c->contains('id', '>=', 2))->toBeTrue()
        ->and($c->contains('id', '>', 2))->toBeFalse()
        ->and($c->doesntContain('id', 1))->toBeFalse()
        ->and($c->doesntContain('id', '>=', 2))->toBeFalse()
        ->and($c->doesntContain('id', '>', 2))->toBeTrue();
});

test('contains indicates if model in array', function () {
    $mockModel = m::mock(Model::class);
    $mockModel->shouldReceive('is')->with($mockModel)->andReturn(true);
    $mockModel->shouldReceive('is')->andReturn(false);
    $mockModel2 = m::mock(Model::class);
    $mockModel2->shouldReceive('is')->with($mockModel2)->andReturn(true);
    $mockModel2->shouldReceive('is')->andReturn(false);
    $mockModel3 = m::mock(Model::class);
    $mockModel3->shouldReceive('is')->with($mockModel3)->andReturn(true);
    $mockModel3->shouldReceive('is')->andReturn(false);
    $c = new Collection([$mockModel, $mockModel2]);

    expect($c->contains($mockModel))->toBeTrue()
        ->and($c->contains($mockModel2))->toBeTrue()
        ->and($c->contains($mockModel3))->toBeFalse()
        ->and($c->doesntContain($mockModel))->toBeFalse()
        ->and($c->doesntContain($mockModel2))->toBeFalse()
        ->and($c->doesntContain($mockModel3))->toBeTrue();
});

test('contains indicates if different model in array', function () {
    $mockModelFoo = m::namedMock('Foo', Model::class);
    $mockModelFoo->shouldReceive('is')->with($mockModelFoo)->andReturn(true);
    $mockModelFoo->shouldReceive('is')->andReturn(false);
    $mockModelBar = m::namedMock('Bar', Model::class);
    $mockModelBar->shouldReceive('is')->with($mockModelBar)->andReturn(true);
    $mockModelBar->shouldReceive('is')->andReturn(false);
    $c = new Collection([$mockModelFoo]);

    expect($c->contains($mockModelFoo))->toBeTrue()
        ->and($c->contains($mockModelBar))->toBeFalse()
        ->and($c->doesntContain($mockModelFoo))->toBeFalse()
        ->and($c->doesntContain($mockModelBar))->toBeTrue();
});

test('contains indicates if keyed model in array', function () {
    $mockModel = m::mock(Model::class);
    $mockModel->shouldReceive('getKey')->andReturn('1');
    $c = new Collection([$mockModel]);
    $mockModel2 = m::mock(Model::class);
    $mockModel2->shouldReceive('getKey')->andReturn('2');
    $c->add($mockModel2);

    expect($c->contains(1))->toBeTrue()
        ->and($c->contains(2))->toBeTrue()
        ->and($c->contains(3))->toBeFalse()
        ->and($c->doesntContain(1))->toBeFalse()
        ->and($c->doesntContain(2))->toBeFalse()
        ->and($c->doesntContain(3))->toBeTrue();
});

test('contains key and value indicates if model in array', function () {
    $mockModel1 = m::mock(Model::class);
    $mockModel1->shouldReceive('offsetExists')->with('name')->andReturn(true);
    $mockModel1->shouldReceive('offsetGet')->with('name')->andReturn('Taylor');
    $mockModel2 = m::mock(Model::class);
    $mockModel2->shouldReceive('offsetExists')->andReturn(true);
    $mockModel2->shouldReceive('offsetGet')->with('name')->andReturn('Abigail');
    $c = new Collection([$mockModel1, $mockModel2]);

    expect($c->contains('name', 'Taylor'))->toBeTrue()
        ->and($c->contains('name', 'Abigail'))->toBeTrue()
        ->and($c->contains('name', 'Dayle'))->toBeFalse()
        ->and($c->doesntContain('name', 'Taylor'))->toBeFalse()
        ->and($c->doesntContain('name', 'Abigail'))->toBeFalse()
        ->and($c->doesntContain('name', 'Dayle'))->toBeTrue();
});

test('contains closure indicates if model in array', function () {
    $mockModel1 = m::mock(Model::class);
    $mockModel1->shouldReceive('getKey')->andReturn(1);
    $mockModel2 = m::mock(Model::class);
    $mockModel2->shouldReceive('getKey')->andReturn(2);
    $c = new Collection([$mockModel1, $mockModel2]);

    expect($c->contains(function ($model) {
        return $model->getKey() < 2;
    }))->toBeTrue()
        ->and($c->contains(function ($model) {
            return $model->getKey() > 2;
        }))->toBeFalse()
        ->and($c->doesntContain(function ($model) {
            return $model->getKey() < 2;
        }))->toBeFalse()
        ->and($c->doesntContain(function ($model) {
            return $model->getKey() > 2;
        }))->toBeTrue();
});

test('find method finds model by id', function () {
    $mockModel = m::mock(Model::class);
    $mockModel->shouldReceive('getKey')->andReturn(1);
    $c = new Collection([$mockModel]);

    expect($c->find(1))->toBe($mockModel)
        ->and($c->find(2, 'taylor'))->toBe('taylor');
});

test('find method finds many models by id', function () {
    $model1 = (new TestInstrumentCollectionModel)->forceFill(['id' => 1]);
    $model2 = (new TestInstrumentCollectionModel)->forceFill(['id' => 2]);
    $model3 = (new TestInstrumentCollectionModel)->forceFill(['id' => 3]);

    $c = new Collection;

    expect($c->find([]))->toBeInstanceOf(Collection::class)
        ->and($c->find([1]))->toHaveCount(0);

    $c->push($model1);

    expect($c->find([1]))->toHaveCount(1)
        ->and($c->find([1])->first()->id)->toEqual(1)
        ->and($c->find([2]))->toHaveCount(0);

    $c->push($model2)->push($model3);

    expect($c->find([2]))->toHaveCount(1)
        ->and($c->find([2])->first()->id)->toEqual(2)
        ->and($c->find([2, 3, 4]))->toHaveCount(2)
        ->and($c->find(collect([2, 3, 4])))->toHaveCount(2)
        ->and($c->find(collect([2, 3, 4]))->pluck('id')->all())->toEqual([2, 3])
        ->and($c->find([2, 3, 4])->pluck('id')->all())->toEqual([2, 3]);
});

test('find or fail finds model by id', function () {
    $mockModel = m::mock(Model::class);
    $mockModel->shouldReceive('getKey')->andReturn(1);
    $c = new Collection([$mockModel]);

    expect($c->findOrFail(1))->toBe($mockModel);
});

test('find or fail finds many models by id', function () {
    $model1 = (new TestInstrumentCollectionModel)->forceFill(['id' => 1]);
    $model2 = (new TestInstrumentCollectionModel)->forceFill(['id' => 2]);

    $c = new Collection;

    expect($c->findOrFail([]))->toBeInstanceOf(Collection::class)
        ->and($c->findOrFail([]))->toHaveCount(0);

    $c->push($model1);

    expect($c->findOrFail([1]))->toHaveCount(1)
        ->and($c->findOrFail([1])->first()->id)->toEqual(1);

    $c->push($model2);

    expect($c->findOrFail([1, 2]))->toHaveCount(2);

    $c->findOrFail([1, 2, 3]);
})->throws(ModelNotFoundException::class, 'No query results for model [Tests\Database\TestInstrumentCollectionModel] 3');

test('find or fail throws exception with message when other models are present', function () {
    $model = (new TestInstrumentCollectionModel)->forceFill(['id' => 1]);

    $c = new Collection([$model]);

    $c->findOrFail(2);
})->throws(ModelNotFoundException::class, 'No query results for model [Tests\Database\TestInstrumentCollectionModel] 2');

test('find or fail throws exception without message when other models are not present', function () {
    $c = new Collection();

    $c->findOrFail(1);
})->throws(ModelNotFoundException::class, '');

test('load method eager loads given relationships', function () {
    $c = $this->getMockBuilder(Collection::class)->onlyMethods(['first'])->setConstructorArgs([['foo']])->getMock();
    $mockItem = m::mock(stdClass::class);
    $c->expects($this->once())->method('first')->willReturn($mockItem);
    $mockItem->shouldReceive('newQueryWithoutRelationships')->once()->andReturn($mockItem);
    $mockItem->shouldReceive('with')->with(['bar', 'baz'])->andReturn($mockItem);
    $mockItem->shouldReceive('eagerLoadRelations')->once()->with(['foo'])->andReturn(['results']);
    $c->load('bar', 'baz');

    expect($c->all())->toEqual(['results']);
});

test('collection dictionary returns model keys', function () {
    $one = m::mock(Model::class);
    $one->shouldReceive('getKey')->andReturn(1);

    $two = m::mock(Model::class);
    $two->shouldReceive('getKey')->andReturn(2);

    $three = m::mock(Model::class);
    $three->shouldReceive('getKey')->andReturn(3);

    $c = new Collection([$one, $two, $three]);

    expect($c->modelKeys())->toEqual([1, 2, 3]);
});

test('collection merges with given collection', function () {
    $one = m::mock(Model::class);
    $one->shouldReceive('getKey')->andReturn(1);

    $two = m::mock(Model::class);
    $two->shouldReceive('getKey')->andReturn(2);

    $three = m::mock(Model::class);
    $three->shouldReceive('getKey')->andReturn(3);

    $c1 = new Collection([$one, $two]);
    $c2 = new Collection([$two, $three]);

    expect($c1->merge($c2))->toEqual(new Collection([$one, $two, $three]));
});

test('map', function () {
    $one = m::mock(Model::class);
    $two = m::mock(Model::class);

    $c = new Collection([$one, $two]);

    $cAfterMap = $c->map(function ($item) {
        return $item;
    });

    expect($cAfterMap->all())->toEqual($c->all())
        ->and($cAfterMap)->toBeInstanceOf(Collection::class);
});

test('mapping to non models returns a base collection', function () {
    $one = m::mock(Model::class);
    $two = m::mock(Model::class);

    $c = (new Collection([$one, $two]))->map(function ($item) {
        return 'not-a-model';
    });

    expect(get_class($c))->toEqual(BaseCollection::class);
});

test('map with keys', function () {
    $one = m::mock(Model::class);
    $two = m::mock(Model::class);

    $c = new Collection([$one, $two]);

    $key = 0;
    $cAfterMap = $c->mapWithKeys(function ($item) use (&$key) {
        return [$key++ => $item];
    });

    expect($cAfterMap->all())->toEqual($c->all())
        ->and($cAfterMap)->toBeInstanceOf(Collection::class);
});

test('map with keys to non models returns a base collection', function () {
    $one = m::mock(Model::class);
    $two = m::mock(Model::class);

    $key = 0;
    $c = (new Collection([$one, $two]))->mapWithKeys(function ($item) use (&$key) {
        return [$key++ => 'not-a-model'];
    });

    expect(get_class($c))->toEqual(BaseCollection::class);
});

test('collection diffs with given collection', function () {
    $one = m::mock(Model::class);
    $one->shouldReceive('getKey')->andReturn(1);

    $two = m::mock(Model::class);
    $two->shouldReceive('getKey')->andReturn(2);

    $three = m::mock(Model::class);
    $three->shouldReceive('getKey')->andReturn(3);

    $c1 = new Collection([$one, $two]);
    $c2 = new Collection([$two, $three]);

    expect($c1->diff($c2))->toEqual(new Collection([$one]));
});

test('collection returns duplicate based only on keys', function () {
    $one = new TestInstrumentCollectionModel;
    $two = new TestInstrumentCollectionModel;
    $three = new TestInstrumentCollectionModel;
    $four = new TestInstrumentCollectionModel;
    $one->id = 1;
    $one->someAttribute = '1';
    $two->id = 1;
    $two->someAttribute = '2';
    $three->id = 1;
    $three->someAttribute = '3';
    $four->id = 2;
    $four->someAttribute = '4';

    $duplicates = Collection::make([$one, $two, $three, $four])->duplicates()->all();
    expect($duplicates)->toBe([1 => $two, 2 => $three]);

    $duplicates = Collection::make([$one, $two, $three, $four])->duplicatesStrict()->all();
    expect($duplicates)->toBe([1 => $two, 2 => $three]);
});

test('collection intersect with null', function () {
    $one = m::mock(Model::class);
    $one->shouldReceive('getKey')->andReturn(1);

    $two = m::mock(Model::class);
    $two->shouldReceive('getKey')->andReturn(2);

    $three = m::mock(Model::class);
    $three->shouldReceive('getKey')->andReturn(3);

    $c1 = new Collection([$one, $two, $three]);

    expect($c1->intersect(null)->all())->toEqual([]);
});

test('collection intersects with given collection', function () {
    $one = m::mock(Model::class);
    $one->shouldReceive('getKey')->andReturn(1);

    $two = m::mock(Model::class);
    $two->shouldReceive('getKey')->andReturn(2);

    $three = m::mock(Model::class);
    $three->shouldReceive('getKey')->andReturn(3);

    $c1 = new Collection([$one, $two]);
    $c2 = new Collection([$two, $three]);

    expect($c1->intersect($c2))->toEqual(new Collection([$two]));
});

test('collection returns unique items', function () {
    $one = m::mock(Model::class);
    $one->shouldReceive('getKey')->andReturn(1);

    $two = m::mock(Model::class);
    $two->shouldReceive('getKey')->andReturn(2);

    $c = new Collection([$one, $two, $two]);

    expect($c->unique())->toEqual(new Collection([$one, $two]));
});

test('collection returns unique strict based on keys only', function () {
    $one = new TestInstrumentCollectionModel;
    $two = new TestInstrumentCollectionModel;
    $three = new TestInstrumentCollectionModel;
    $four = new TestInstrumentCollectionModel;
    $one->id = 1;
    $one->someAttribute = '1';
    $two->id = 1;
    $two->someAttribute = '2';
    $three->id = 1;
    $three->someAttribute = '3';
    $four->id = 2;
    $four->someAttribute = '4';

    $uniques = Collection::make([$one, $two, $three, $four])->unique()->all();
    expect($uniques)->toBe([$three, $four]);

    $uniques = Collection::make([$one, $two, $three, $four])->unique(null, true)->all();
    expect($uniques)->toBe([$three, $four]);
});

test('only returns collection with given model keys', function () {
    $one = m::mock(Model::class);
    $one->shouldReceive('getKey')->andReturn(1);

    $two = m::mock(Model::class);
    $two->shouldReceive('getKey')->andReturn(2);

    $three = m::mock(Model::class);
    $three->shouldReceive('getKey')->andReturn(3);

    $c = new Collection([$one, $two, $three]);

    expect($c->only(null))->toEqual($c)
        ->and($c->only(1))->toEqual(new Collection([$one]))
        ->and($c->only([2, 3]))->toEqual(new Collection([$two, $three]));
});

test('except returns collection without given model keys', function () {
    $one = m::mock(Model::class);
    $one->shouldReceive('getKey')->andReturn(1);

    $two = m::mock(Model::class);
    $two->shouldReceive('getKey')->andReturn(2);

    $three = m::mock(Model::class);
    $three->shouldReceive('getKey')->andReturn(3);

    $c = new Collection([$one, $two, $three]);

    expect($c->except(null))->toEqual($c)
        ->and($c->except(2))->toEqual(new Collection([$one, $three]))
        ->and($c->except([2, 3]))->toEqual(new Collection([$one]));
});

test('make hidden adds hidden on entire collection', function () {
    $c = new Collection([new TestInstrumentCollectionModel]);
    $c = $c->makeHidden(['visible']);

    expect($c[0]->getHidden())->toEqual(['hidden', 'visible']);
});

test('make visible removes hidden from entire collection', function () {
    $c = new Collection([new TestInstrumentCollectionModel]);
    $c = $c->makeVisible(['hidden']);

    expect($c[0]->getHidden())->toEqual([]);
});

test('merge hidden adds hidden on entire collection', function () {
    $c = new Collection([new TestInstrumentCollectionModel]);
    $c = $c->mergeHidden(['merged']);

    expect($c[0]->getHidden())->toEqual(['hidden', 'merged']);
});

test('merge visible removes hidden from entire collection', function () {
    $c = new Collection([new TestInstrumentCollectionModel]);
    $c = $c->mergeVisible(['merged']);

    expect($c[0]->getVisible())->toEqual(['visible', 'merged']);
});

test('set visible replaces visible on entire collection', function () {
    $c = new Collection([new TestInstrumentCollectionModel]);
    $c = $c->setVisible(['hidden']);

    expect($c[0]->getVisible())->toEqual(['hidden']);
});

test('set hidden replaces hidden on entire collection', function () {
    $c = new Collection([new TestInstrumentCollectionModel]);
    $c = $c->setHidden(['visible']);

    expect($c[0]->getHidden())->toEqual(['visible']);
});

test('appends adds test on entire collection', function () {
    $c = new Collection([new TestInstrumentCollectionModel]);
    $c = $c->makeVisible('test');
    $c = $c->append('test');

    expect($c[0]->toArray())->toEqual(['test' => 'test']);
});

test('set appends sets appended properties on entire collection', function () {
    $c = new Collection([new InstrumentAppendingTestUserModel]);
    $c->setAppends(['other_appended_field']);

    expect($c->toArray())->toEqual([['other_appended_field' => 'bye']]);
});

test('without appends removes appends on entire collection', function () {
    dbCollectionSeedData();
    $c = InstrumentAppendingTestUserModel::query()->get();
    expect($c->toArray()[0]['appended_field'])->toEqual('hello');

    $c = $c->withoutAppends();
    expect($c->toArray()[0])->not->toHaveKey('appended_field');
});

test('non model related methods', function () {
    $a = new Collection([['foo' => 'bar'], ['foo' => 'baz']]);
    $b = new Collection(['a', 'b', 'c']);

    expect(get_class($a->pluck('foo')))->toEqual(BaseCollection::class)
        ->and(get_class($a->keys()))->toEqual(BaseCollection::class)
        ->and(get_class($a->collapse()))->toEqual(BaseCollection::class)
        ->and(get_class($a->flatten()))->toEqual(BaseCollection::class)
        ->and(get_class($a->zip(['a', 'b'], ['c', 'd'])))->toEqual(BaseCollection::class)
        ->and(get_class($a->countBy('foo')))->toEqual(BaseCollection::class)
        ->and(get_class($b->flip()))->toEqual(BaseCollection::class)
        ->and(get_class($a->partition('foo', '=', 'bar')))->toEqual(BaseCollection::class)
        ->and(get_class($a->partition('foo', 'bar')))->toEqual(BaseCollection::class);
});

test('make visible removes hidden and includes visible', function () {
    $c = new Collection([new TestInstrumentCollectionModel]);
    $c = $c->makeVisible('hidden');

    expect($c[0]->getHidden())->toEqual([])
        ->and($c[0]->getVisible())->toEqual(['visible', 'hidden']);
});

test('multiply', function () {
    $a = new TestInstrumentCollectionModel();
    $b = new TestInstrumentCollectionModel();

    $c = new Collection([$a, $b]);

    expect($c->multiply(-1)->all())->toEqual([])
        ->and($c->multiply(0)->all())->toEqual([])
        ->and($c->multiply(1)->all())->toEqual([$a, $b])
        ->and($c->multiply(3)->all())->toEqual([$a, $b, $a, $b, $a, $b]);
});

test('queueable collection implementation', function () {
    $c = new Collection([new TestInstrumentCollectionModel, new TestInstrumentCollectionModel]);

    expect($c->getQueueableClass())->toEqual(TestInstrumentCollectionModel::class);
});

test('queueable collection implementation throws exception on multiple model types', function () {
    $c = new Collection([new TestInstrumentCollectionModel, (object) ['id' => 'something']]);
    $c->getQueueableClass();
})->throws(LogicException::class, 'Queueing collections with multiple model types is not supported.');

test('queueable relationships returns only relations common to all models', function () {
    // This is needed to prevent loading non-existing relationships on polymorphic model collections (#26126)
    $c = new Collection([
        new class
        {
            public function getQueueableRelations()
            {
                return ['user'];
            }
        },
        new class
        {
            public function getQueueableRelations()
            {
                return ['user', 'comments'];
            }
        },
    ]);

    expect($c->getQueueableRelations())->toEqual(['user']);
});

test('queueable relationships ignore collection keys', function () {
    $c = new Collection([
        'foo' => new class
        {
            public function getQueueableRelations()
            {
                return [];
            }
        },
        'bar' => new class
        {
            public function getQueueableRelations()
            {
                return [];
            }
        },
    ]);

    expect($c->getQueueableRelations())->toEqual([]);
});

test('empty collection stay empty on fresh', function () {
    $c = new Collection;

    expect($c->fresh())->toEqual($c);
});

test('can convert collection of models to instrument query builder', function () {
    $one = m::mock(Model::class);
    $one->shouldReceive('getKey')->andReturn(1);

    $two = m::mock(Model::class);
    $two->shouldReceive('getKey')->andReturn(2);

    $c = new Collection([$one, $two]);

    $mocBuilder = m::mock(Builder::class);
    $one->shouldReceive('newModelQuery')->once()->andReturn($mocBuilder);
    $mocBuilder->shouldReceive('whereKey')->once()->with($c->modelKeys())->andReturn($mocBuilder);

    expect($c->toQuery())->toBeInstanceOf(Builder::class);
});

test('converting empty collection to query throws exception', function () {
    $c = new Collection;
    $c->toQuery();
})->throws(LogicException::class);

test('load exists should cast bool', function () {
    dbCollectionSeedData();
    $user = InstrumentTestUserModel::with('articles')->first();
    $user->articles->loadExists('comments');
    $commentsExists = $user->articles->pluck('comments_exists')->toArray();

    if (version_compare(InstalledVersions::getPrettyVersion('phpunit/phpunit') ?? '0', '11.5.0', '<')) {
        $this->assertContainsOnly('bool', $commentsExists);
    } else {
        $this->assertContainsOnlyBool($commentsExists);
    }
});

test('with non scalar key', function () {
    $fooKey = new InstrumentTestKey('foo');
    $foo = m::mock(Model::class);
    $foo->shouldReceive('getKey')->andReturn($fooKey);

    $barKey = new InstrumentTestKey('bar');
    $bar = m::mock(Model::class);
    $bar->shouldReceive('getKey')->andReturn($barKey);

    $collection = new Collection([$foo, $bar]);

    expect($collection->only([$fooKey]))->toHaveCount(1)
        ->and($collection->only($fooKey)->first())->toBe($foo)
        ->and($collection->except([$fooKey]))->toHaveCount(1)
        ->and($collection->except($fooKey)->first())->toBe($bar);
});

test('pluck', function () {
    $model1 = (new TestInstrumentCollectionModel)->forceFill(['id' => 1, 'name' => 'John', 'country' => 'US']);
    $model2 = (new TestInstrumentCollectionModel)->forceFill(['id' => 2, 'name' => 'Jane', 'country' => 'NL']);
    $model3 = (new TestInstrumentCollectionModel)->forceFill(['id' => 3, 'name' => 'Taylor', 'country' => 'US']);

    $c = new Collection;

    $c->push($model1)->push($model2)->push($model3);

    expect($c->pluck('id'))->toBeInstanceOf(BaseCollection::class)
        ->and($c->pluck('id')->all())->toEqual([1, 2, 3])
        ->and($c->pluck('id', 'id'))->toBeInstanceOf(BaseCollection::class)
        ->and($c->pluck('id', 'id')->all())->toEqual([1 => 1, 2 => 2, 3 => 3])
        ->and($c->pluck('test'))->toBeInstanceOf(BaseCollection::class)
        ->and($c->pluck(fn (TestInstrumentCollectionModel $model) => "{$model->name} ({$model->country})")->all())->toEqual(['John (US)', 'Jane (NL)', 'Taylor (US)']);
});

class TestInstrumentCollectionModel extends Model
{
    protected $visible = ['visible'];
    protected $hidden = ['hidden'];

    public function getTestAttribute()
    {
        return 'test';
    }
}

class InstrumentTestUserModel extends Model
{
    protected $table = 'users';
    protected $guarded = [];
    public $timestamps = false;

    public function articles()
    {
        return $this->hasMany(InstrumentTestArticleModel::class, 'user_id');
    }
}

class InstrumentTestArticleModel extends Model
{
    protected $table = 'articles';
    protected $guarded = [];
    public $timestamps = false;

    public function comments()
    {
        return $this->hasMany(InstrumentTestCommentModel::class, 'article_id');
    }
}

class InstrumentTestCommentModel extends Model
{
    protected $table = 'comments';
    protected $guarded = [];
    public $timestamps = false;
}

class InstrumentTestKey
{
    public function __construct(private readonly string $key)
    {
    }

    public function __toString()
    {
        return $this->key;
    }
}

class InstrumentAppendingTestUserModel extends Model
{
    protected $table = 'users';
    protected $guarded = [];
    public $timestamps = false;
    protected $appends = ['appended_field'];

    public function getAppendedFieldAttribute()
    {
        return 'hello';
    }

    public function getOtherAppendedFieldAttribute()
    {
        return 'bye';
    }

    public function articles()
    {
        return $this->hasMany(InstrumentTestArticleModel::class, 'user_id');
    }
}
