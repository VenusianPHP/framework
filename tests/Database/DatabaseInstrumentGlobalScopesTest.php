<?php

namespace Tests\Database;

use Voyager\Database\Capsule\Manager as DB;
use Voyager\Database\Instrument\Attributes\ScopedBy;
use Voyager\Database\Instrument\Builder;
use Voyager\Database\Instrument\Model;
use Voyager\Database\Instrument\Scope;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class DatabaseInstrumentGlobalScopesTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected function setUp(): void
    {
        parent::setUp();

        tap(new DB)->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ])->bootInstrument();
    }

    protected function tearDown(): void
    {
        Model::unsetConnectionResolver();

        parent::tearDown();
    }

    public function testGlobalScopeIsApplied()
    {
        $model = new InstrumentGlobalScopesTestModel;
        $query = $model->newQuery();
        $this->assertSame('select * from "table" where "active" = ?', $query->toSql());
        $this->assertEquals([1], $query->getBindings());
    }

    public function testGlobalScopeCanBeRemoved()
    {
        $model = new InstrumentGlobalScopesTestModel;
        $query = $model->newQuery()->withoutGlobalScope(ActiveScope::class);
        $this->assertSame('select * from "table"', $query->toSql());
        $this->assertEquals([], $query->getBindings());
    }

    public function testClassNameGlobalScopeIsApplied()
    {
        $model = new InstrumentClassNameGlobalScopesTestModel;
        $query = $model->newQuery();
        $this->assertSame('select * from "table" where "active" = ?', $query->toSql());
        $this->assertEquals([1], $query->getBindings());
    }

    public function testGlobalScopeInAttributeIsApplied()
    {
        $model = new InstrumentGlobalScopeInAttributeTestModel;
        $query = $model->newQuery();
        $this->assertSame('select * from "table" where "active" = ?', $query->toSql());
        $this->assertEquals([1], $query->getBindings());
    }

    public function testGlobalScopeInInheritedAttributeIsApplied()
    {
        $model = new InstrumentGlobalScopeInInheritedAttributeTestModel;
        $query = $model->newQuery();
        $this->assertSame('select * from "table" where "active" = ?', $query->toSql());
        $this->assertEquals([1], $query->getBindings());
    }

    public function testClosureGlobalScopeIsApplied()
    {
        $model = new InstrumentClosureGlobalScopesTestModel;
        $query = $model->newQuery();
        $this->assertSame('select * from "table" where "active" = ? order by "name" asc', $query->toSql());
        $this->assertEquals([1], $query->getBindings());
    }

    public function testGlobalScopesCanBeRegisteredViaArray()
    {
        $model = new InstrumentGlobalScopesArrayTestModel;
        $query = $model->newQuery();
        $this->assertSame('select * from "table" where "active" = ? order by "name" asc', $query->toSql());
        $this->assertEquals([1], $query->getBindings());
    }

    public function testClosureGlobalScopeCanBeRemoved()
    {
        $model = new InstrumentClosureGlobalScopesTestModel;
        $query = $model->newQuery()->withoutGlobalScope('active_scope');
        $this->assertSame('select * from "table" order by "name" asc', $query->toSql());
        $this->assertEquals([], $query->getBindings());
    }

    public function testGlobalScopeCanBeRemovedAfterTheQueryIsExecuted()
    {
        $model = new InstrumentClosureGlobalScopesTestModel;
        $query = $model->newQuery();
        $this->assertSame('select * from "table" where "active" = ? order by "name" asc', $query->toSql());
        $this->assertEquals([1], $query->getBindings());

        $query->withoutGlobalScope('active_scope');
        $this->assertSame('select * from "table" order by "name" asc', $query->toSql());
        $this->assertEquals([], $query->getBindings());
    }

    public function testAllGlobalScopesCanBeRemoved()
    {
        $model = new InstrumentClosureGlobalScopesTestModel;
        $query = $model->newQuery()->withoutGlobalScopes();
        $this->assertSame('select * from "table"', $query->toSql());
        $this->assertEquals([], $query->getBindings());

        $query = InstrumentClosureGlobalScopesTestModel::withoutGlobalScopes();
        $this->assertSame('select * from "table"', $query->toSql());
        $this->assertEquals([], $query->getBindings());
    }

    public function testAllGlobalScopesCanBeRemovedExceptSpecified()
    {
        $model = new InstrumentClosureGlobalScopesTestModel;
        $query = $model->newQuery()->withoutGlobalScopesExcept(['active_scope']);
        $this->assertSame('select * from "table" where "active" = ?', $query->toSql());
        $this->assertEquals([1], $query->getBindings());

        $query = InstrumentClosureGlobalScopesTestModel::withoutGlobalScopesExcept(['active_scope']);
        $this->assertSame('select * from "table" where "active" = ?', $query->toSql());
        $this->assertEquals([1], $query->getBindings());
    }

    public function testGlobalScopesWithOrWhereConditionsAreNested()
    {
        $model = new InstrumentClosureGlobalScopesWithOrTestModel;

        $query = $model->newQuery();
        $this->assertSame('select "email", "password" from "table" where ("email" = ? or "email" = ?) and "active" = ? order by "name" asc', $query->toSql());
        $this->assertEquals(['taylor@gmail.com', 'someone@else.com', 1], $query->getBindings());

        $query = $model->newQuery()->where('col1', 'val1')->orWhere('col2', 'val2');
        $this->assertSame('select "email", "password" from "table" where ("col1" = ? or "col2" = ?) and ("email" = ? or "email" = ?) and "active" = ? order by "name" asc', $query->toSql());
        $this->assertEquals(['val1', 'val2', 'taylor@gmail.com', 'someone@else.com', 1], $query->getBindings());
    }

    public function testRegularScopesWithOrWhereConditionsAreNested()
    {
        $query = InstrumentClosureGlobalScopesTestModel::withoutGlobalScopes()->where('foo', 'foo')->orWhere('bar', 'bar')->approved();

        $this->assertSame('select * from "table" where ("foo" = ? or "bar" = ?) and ("approved" = ? or "should_approve" = ?)', $query->toSql());
        $this->assertEquals(['foo', 'bar', 1, 0], $query->getBindings());
    }

    public function testScopesStartingWithOrBooleanArePreserved()
    {
        $query = InstrumentClosureGlobalScopesTestModel::withoutGlobalScopes()->where('foo', 'foo')->orWhere('bar', 'bar')->orApproved();

        $this->assertSame('select * from "table" where ("foo" = ? or "bar" = ?) or ("approved" = ? or "should_approve" = ?)', $query->toSql());
        $this->assertEquals(['foo', 'bar', 1, 0], $query->getBindings());
    }

    public function testHasQueryWhereBothModelsHaveGlobalScopes()
    {
        $query = InstrumentGlobalScopesWithRelationModel::has('related')->where('bar', 'baz');

        $subQuery = 'select * from "table" where "table2"."id" = "table"."related_id" and "foo" = ? and "active" = ?';
        $mainQuery = 'select * from "table2" where exists ('.$subQuery.') and "bar" = ? and "active" = ? order by "name" asc';

        $this->assertEquals($mainQuery, $query->toSql());
        $this->assertEquals(['bar', 1, 'baz', 1], $query->getBindings());
    }
}

class InstrumentClosureGlobalScopesTestModel extends Model
{
    protected $table = 'table';

    public static function boot()
    {
        static::addGlobalScope(function ($query) {
            $query->orderBy('name');
        });

        static::addGlobalScope('active_scope', function ($query) {
            $query->where('active', 1);
        });

        parent::boot();
    }

    public function scopeApproved($query)
    {
        return $query->where('approved', 1)->orWhere('should_approve', 0);
    }

    public function scopeOrApproved($query)
    {
        return $query->orWhere('approved', 1)->orWhere('should_approve', 0);
    }
}

class InstrumentGlobalScopesWithRelationModel extends InstrumentClosureGlobalScopesTestModel
{
    protected $table = 'table2';

    public function related()
    {
        return $this->hasMany(InstrumentGlobalScopesTestModel::class, 'related_id')->where('foo', 'bar');
    }
}

class InstrumentClosureGlobalScopesWithOrTestModel extends InstrumentClosureGlobalScopesTestModel
{
    public static function boot()
    {
        static::addGlobalScope('or_scope', function ($query) {
            $query->where('email', 'taylor@gmail.com')->orWhere('email', 'someone@else.com');
        });

        static::addGlobalScope(function ($query) {
            $query->select('email', 'password');
        });

        parent::boot();
    }
}

class InstrumentGlobalScopesTestModel extends Model
{
    protected $table = 'table';

    public static function boot()
    {
        static::addGlobalScope(new ActiveScope);

        parent::boot();
    }
}

class InstrumentClassNameGlobalScopesTestModel extends Model
{
    protected $table = 'table';

    public static function boot()
    {
        static::addGlobalScope(ActiveScope::class);

        parent::boot();
    }
}

class InstrumentGlobalScopesArrayTestModel extends Model
{
    protected $table = 'table';

    public static function boot()
    {
        static::addGlobalScopes([
            'active_scope' => new ActiveScope,
            fn ($query) => $query->orderBy('name'),
        ]);

        parent::boot();
    }
}

#[ScopedBy(ActiveScope::class)]
class InstrumentGlobalScopeInAttributeTestModel extends Model
{
    protected $table = 'table';
}

class ActiveScope implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        return $builder->where('active', 1);
    }
}

#[ScopedBy(ActiveScope::class)]
trait InstrumentGlobalScopeInInheritedAttributeTestTrait
{
    //
}

class InstrumentGlobalScopeInInheritedAttributeTestModel extends Model
{
    use InstrumentGlobalScopeInInheritedAttributeTestTrait;

    protected $table = 'table';
}
