<?php

namespace Tests\Database;

use Voyager\Database\Capsule\Manager as DB;
use Voyager\Database\Instrument\Attributes\ScopedBy;
use Voyager\Database\Instrument\Builder;
use Voyager\Database\Instrument\Model;
use Voyager\Database\Instrument\Scope;

beforeEach(function () {
    tap(new DB)->addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ])->bootInstrument();
});

afterEach(function () {
    Model::unsetConnectionResolver();
});

test('global scope is applied', function () {
    $model = new InstrumentGlobalScopesTestModel;
    $query = $model->newQuery();

    expect($query->toSql())->toBe('select * from "table" where "active" = ?')
        ->and($query->getBindings())->toEqual([1]);
});

test('global scope can be removed', function () {
    $model = new InstrumentGlobalScopesTestModel;
    $query = $model->newQuery()->withoutGlobalScope(ActiveScope::class);

    expect($query->toSql())->toBe('select * from "table"')
        ->and($query->getBindings())->toEqual([]);
});

test('class name global scope is applied', function () {
    $model = new InstrumentClassNameGlobalScopesTestModel;
    $query = $model->newQuery();

    expect($query->toSql())->toBe('select * from "table" where "active" = ?')
        ->and($query->getBindings())->toEqual([1]);
});

test('global scope in attribute is applied', function () {
    $model = new InstrumentGlobalScopeInAttributeTestModel;
    $query = $model->newQuery();

    expect($query->toSql())->toBe('select * from "table" where "active" = ?')
        ->and($query->getBindings())->toEqual([1]);
});

test('global scope in inherited attribute is applied', function () {
    $model = new InstrumentGlobalScopeInInheritedAttributeTestModel;
    $query = $model->newQuery();

    expect($query->toSql())->toBe('select * from "table" where "active" = ?')
        ->and($query->getBindings())->toEqual([1]);
});

test('closure global scope is applied', function () {
    $model = new InstrumentClosureGlobalScopesTestModel;
    $query = $model->newQuery();

    expect($query->toSql())->toBe('select * from "table" where "active" = ? order by "name" asc')
        ->and($query->getBindings())->toEqual([1]);
});

test('global scopes can be registered via array', function () {
    $model = new InstrumentGlobalScopesArrayTestModel;
    $query = $model->newQuery();

    expect($query->toSql())->toBe('select * from "table" where "active" = ? order by "name" asc')
        ->and($query->getBindings())->toEqual([1]);
});

test('closure global scope can be removed', function () {
    $model = new InstrumentClosureGlobalScopesTestModel;
    $query = $model->newQuery()->withoutGlobalScope('active_scope');

    expect($query->toSql())->toBe('select * from "table" order by "name" asc')
        ->and($query->getBindings())->toEqual([]);
});

test('global scope can be removed after the query is executed', function () {
    $model = new InstrumentClosureGlobalScopesTestModel;
    $query = $model->newQuery();

    expect($query->toSql())->toBe('select * from "table" where "active" = ? order by "name" asc')
        ->and($query->getBindings())->toEqual([1]);

    $query->withoutGlobalScope('active_scope');

    expect($query->toSql())->toBe('select * from "table" order by "name" asc')
        ->and($query->getBindings())->toEqual([]);
});

test('all global scopes can be removed', function () {
    $model = new InstrumentClosureGlobalScopesTestModel;
    $query = $model->newQuery()->withoutGlobalScopes();

    expect($query->toSql())->toBe('select * from "table"')
        ->and($query->getBindings())->toEqual([]);

    $query = InstrumentClosureGlobalScopesTestModel::withoutGlobalScopes();

    expect($query->toSql())->toBe('select * from "table"')
        ->and($query->getBindings())->toEqual([]);
});

test('all global scopes can be removed except specified', function () {
    $model = new InstrumentClosureGlobalScopesTestModel;
    $query = $model->newQuery()->withoutGlobalScopesExcept(['active_scope']);

    expect($query->toSql())->toBe('select * from "table" where "active" = ?')
        ->and($query->getBindings())->toEqual([1]);

    $query = InstrumentClosureGlobalScopesTestModel::withoutGlobalScopesExcept(['active_scope']);

    expect($query->toSql())->toBe('select * from "table" where "active" = ?')
        ->and($query->getBindings())->toEqual([1]);
});

test('global scopes with or where conditions are nested', function () {
    $model = new InstrumentClosureGlobalScopesWithOrTestModel;

    $query = $model->newQuery();

    expect($query->toSql())->toBe('select "email", "password" from "table" where ("email" = ? or "email" = ?) and "active" = ? order by "name" asc')
        ->and($query->getBindings())->toEqual(['taylor@gmail.com', 'someone@else.com', 1]);

    $query = $model->newQuery()->where('col1', 'val1')->orWhere('col2', 'val2');

    expect($query->toSql())->toBe('select "email", "password" from "table" where ("col1" = ? or "col2" = ?) and ("email" = ? or "email" = ?) and "active" = ? order by "name" asc')
        ->and($query->getBindings())->toEqual(['val1', 'val2', 'taylor@gmail.com', 'someone@else.com', 1]);
});

test('regular scopes with or where conditions are nested', function () {
    $query = InstrumentClosureGlobalScopesTestModel::withoutGlobalScopes()->where('foo', 'foo')->orWhere('bar', 'bar')->approved();

    expect($query->toSql())->toBe('select * from "table" where ("foo" = ? or "bar" = ?) and ("approved" = ? or "should_approve" = ?)')
        ->and($query->getBindings())->toEqual(['foo', 'bar', 1, 0]);
});

test('scopes starting with or boolean are preserved', function () {
    $query = InstrumentClosureGlobalScopesTestModel::withoutGlobalScopes()->where('foo', 'foo')->orWhere('bar', 'bar')->orApproved();

    expect($query->toSql())->toBe('select * from "table" where ("foo" = ? or "bar" = ?) or ("approved" = ? or "should_approve" = ?)')
        ->and($query->getBindings())->toEqual(['foo', 'bar', 1, 0]);
});

test('has query where both models have global scopes', function () {
    $query = InstrumentGlobalScopesWithRelationModel::has('related')->where('bar', 'baz');

    $subQuery = 'select * from "table" where "table2"."id" = "table"."related_id" and "foo" = ? and "active" = ?';
    $mainQuery = 'select * from "table2" where exists ('.$subQuery.') and "bar" = ? and "active" = ? order by "name" asc';

    expect($query->toSql())->toEqual($mainQuery)
        ->and($query->getBindings())->toEqual(['bar', 1, 'baz', 1]);
});

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
