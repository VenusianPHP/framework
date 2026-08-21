<?php

use Tests\Validation\deferred\Fixtures\ClassWithRequiredConstructorParameters;
use Tests\Validation\deferred\Fixtures\NoTableNameModel;
use Tests\Validation\deferred\Fixtures\User;
use Tests\Validation\deferred\Fixtures\UserWithConnection;
use Tests\Validation\deferred\Fixtures\UserWithPrefixedTable;
use Voyager\Database\Capsule\Manager as DB;
use Voyager\Database\Instrument\Model as Instrument;
use Voyager\Translation\ArrayLoader;
use Voyager\Translation\Translator;
use Voyager\Validation\DatabasePresenceVerifier;
use Voyager\Validation\Rules\Exists;
use Voyager\Validation\Validator;

function existsArrayTranslator()
{
    return new Translator(
        new ArrayLoader, 'en'
    );
}

function existsConnectionResolver()
{
    return Instrument::getConnectionResolver();
}

function existsConnection($connection = 'default')
{
    return existsConnectionResolver()->connection($connection);
}

function existsSchema($connection = 'default')
{
    return existsConnection($connection)->getSchemaBuilder();
}

beforeEach(function () {
    $db = new DB;

    $db->addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    $db->bootInstrument();
    $db->setAsGlobal();

    existsSchema('default')->create('users', function ($table) {
        $table->unsignedInteger('id');
        $table->string('type');
    });
});

afterEach(function () {
    existsSchema('default')->drop('users');
});

test('it correctly formats a string version of the rule', function () {
        $rule = new Exists('table');
        $rule->where('foo', 'bar');
        expect((string) $rule)->toBe('exists:table,NULL,foo,"bar"');

        $rule = new Exists(User::class);
        $rule->where('foo', 'bar');
        expect((string) $rule)->toBe('exists:users,NULL,foo,"bar"');

        $rule = new Exists(UserWithPrefixedTable::class);
        $rule->where('foo', 'bar');
        expect((string) $rule)->toBe('exists:'.UserWithPrefixedTable::class.',NULL,foo,"bar"');

        $rule = new Exists('table', 'column');
        $rule->where('foo', 'bar');
        expect((string) $rule)->toBe('exists:table,column,foo,"bar"');

        $rule = new Exists(User::class, 'column');
        $rule->where('foo', 'bar');
        expect((string) $rule)->toBe('exists:users,column,foo,"bar"');

        $rule = new Exists(UserWithConnection::class, 'column');
        $rule->where('foo', 'bar');
        expect((string) $rule)->toBe('exists:mysql.users,column,foo,"bar"');

        $rule = new Exists('Tests\Validation\deferred\Fixtures\User', 'column');
        $rule->where('foo', 'bar');
        expect((string) $rule)->toBe('exists:users,column,foo,"bar"');

        $rule = new Exists(NoTableNameModel::class, 'column');
        $rule->where('foo', 'bar');
        expect((string) $rule)->toBe('exists:no_table_name_models,column,foo,"bar"');

        $rule = new Exists(ClassWithRequiredConstructorParameters::class, 'column');
        $rule->where('foo', 'bar');
        expect((string) $rule)->toBe('exists:'.ClassWithRequiredConstructorParameters::class.',column,foo,"bar"');
    });

test('it chooses valid records using where in rule', function () {
        $rule = new Exists('users', 'id');
        $rule->whereIn('type', ['foo', 'bar']);

        User::create(['id' => '1', 'type' => 'foo']);
        User::create(['id' => '2', 'type' => 'bar']);
        User::create(['id' => '3', 'type' => 'baz']);
        User::create(['id' => '4', 'type' => 'other']);

        $trans = existsArrayTranslator();
        $v = new Validator($trans, [], ['id' => $rule]);
        $v->setPresenceVerifier(new DatabasePresenceVerifier(Instrument::getConnectionResolver()));

        $v->setData(['id' => 1]);
        expect($v->passes())->toBeTrue();
        $v->setData(['id' => 2]);
        expect($v->passes())->toBeTrue();
        $v->setData(['id' => 3]);
        expect($v->passes())->toBeFalse();
        $v->setData(['id' => 4]);
        expect($v->passes())->toBeFalse();

        // array values
        $v->setData(['id' => [1, 2]]);
        expect($v->passes())->toBeTrue();
        $v->setData(['id' => [3, 4]]);
        expect($v->passes())->toBeFalse();
    });

test('it chooses valid records using where not in rule', function () {
        $rule = new Exists('users', 'id');
        $rule->whereNotIn('type', ['foo', 'bar']);

        User::create(['id' => '1', 'type' => 'foo']);
        User::create(['id' => '2', 'type' => 'bar']);
        User::create(['id' => '3', 'type' => 'baz']);
        User::create(['id' => '4', 'type' => 'other']);

        $trans = existsArrayTranslator();
        $v = new Validator($trans, [], ['id' => $rule]);
        $v->setPresenceVerifier(new DatabasePresenceVerifier(Instrument::getConnectionResolver()));

        $v->setData(['id' => 1]);
        expect($v->passes())->toBeFalse();
        $v->setData(['id' => 2]);
        expect($v->passes())->toBeFalse();
        $v->setData(['id' => 3]);
        expect($v->passes())->toBeTrue();
        $v->setData(['id' => 4]);
        expect($v->passes())->toBeTrue();

        // array values
        $v->setData(['id' => [1, 2]]);
        expect($v->passes())->toBeFalse();
        $v->setData(['id' => [3, 4]]);
        expect($v->passes())->toBeTrue();
    });

test('it chooses valid records using conditional modifiers', function () {
        $rule = new Exists('users', 'id');
        $rule->when(true, function ($rule) {
            $rule->whereNotIn('type', ['foo', 'bar']);
        });
        $rule->unless(true, function ($rule) {
            $rule->whereNotIn('type', ['baz', 'other']);
        });

        User::create(['id' => '1', 'type' => 'foo']);
        User::create(['id' => '2', 'type' => 'bar']);
        User::create(['id' => '3', 'type' => 'baz']);
        User::create(['id' => '4', 'type' => 'other']);

        $trans = existsArrayTranslator();
        $v = new Validator($trans, [], ['id' => $rule]);
        $v->setPresenceVerifier(new DatabasePresenceVerifier(Instrument::getConnectionResolver()));

        $v->setData(['id' => 1]);
        expect($v->passes())->toBeFalse();
        $v->setData(['id' => 2]);
        expect($v->passes())->toBeFalse();
        $v->setData(['id' => 3]);
        expect($v->passes())->toBeTrue();
        $v->setData(['id' => 4]);
        expect($v->passes())->toBeTrue();

        // array values
        $v->setData(['id' => [1, 2]]);
        expect($v->passes())->toBeFalse();
        $v->setData(['id' => [3, 4]]);
        expect($v->passes())->toBeTrue();
    });

test('it chooses valid records using where not in and where not in rules together', function () {
        $rule = new Exists('users', 'id');
        $rule->whereIn('type', ['foo', 'bar', 'baz'])->whereNotIn('type', ['foo', 'bar']);

        User::create(['id' => '1', 'type' => 'foo']);
        User::create(['id' => '2', 'type' => 'bar']);
        User::create(['id' => '3', 'type' => 'baz']);
        User::create(['id' => '4', 'type' => 'other']);
        User::create(['id' => '5', 'type' => 'baz']);

        $trans = existsArrayTranslator();
        $v = new Validator($trans, [], ['id' => $rule]);
        $v->setPresenceVerifier(new DatabasePresenceVerifier(Instrument::getConnectionResolver()));

        $v->setData(['id' => 1]);
        expect($v->passes())->toBeFalse();
        $v->setData(['id' => 2]);
        expect($v->passes())->toBeFalse();
        $v->setData(['id' => 3]);
        expect($v->passes())->toBeTrue();
        $v->setData(['id' => 4]);
        expect($v->passes())->toBeFalse();
        $v->setData(['id' => 5]);
        expect($v->passes())->toBeTrue();

        // array values
        $v->setData(['id' => [1, 2, 4]]);
        expect($v->passes())->toBeFalse();
        $v->setData(['id' => [3, 5]]);
        expect($v->passes())->toBeTrue();
    });

test('it chooses valid records using where not rule', function () {
        $rule = new Exists('users', 'id');

        $rule->whereNot('type', 'baz');

        User::create(['id' => '1', 'type' => 'foo']);
        User::create(['id' => '2', 'type' => 'bar']);
        User::create(['id' => '3', 'type' => 'baz']);
        User::create(['id' => '4', 'type' => 'other']);
        User::create(['id' => '5', 'type' => 'baz']);

        $trans = existsArrayTranslator();
        $v = new Validator($trans, [], ['id' => $rule]);
        $v->setPresenceVerifier(new DatabasePresenceVerifier(Instrument::getConnectionResolver()));

        $v->setData(['id' => 3]);
        expect($v->passes())->toBeFalse();

        $v->setData(['id' => 4]);
        expect($v->passes())->toBeTrue();
    });

test('it ignores soft deletes', function () {
        $rule = new Exists('table');
        $rule->withoutTrashed();
        expect((string) $rule)->toBe('exists:table,NULL,deleted_at,"NULL"');

        $rule = new Exists('table');
        $rule->withoutTrashed('softdeleted_at');
        expect((string) $rule)->toBe('exists:table,NULL,softdeleted_at,"NULL"');
    });

test('it only trashed soft deletes', function () {
        $rule = new Exists('table');
        $rule->onlyTrashed();
        expect((string) $rule)->toBe('exists:table,NULL,deleted_at,"NOT_NULL"');

        $rule = new Exists('table');
        $rule->onlyTrashed('softdeleted_at');
        expect((string) $rule)->toBe('exists:table,NULL,softdeleted_at,"NOT_NULL"');
    });

test('it is a part of list rules', function () {
        $rule = new Exists('users', 'id');

        User::create(['id' => '1', 'type' => 'foo']);
        User::create(['id' => '2', 'type' => 'bar']);
        User::create(['id' => '3', 'type' => 'baz']);

        $trans = existsArrayTranslator();
        $v = new Validator($trans, [], ['id' => ['required', $rule]]);
        $v->setPresenceVerifier(new DatabasePresenceVerifier(Instrument::getConnectionResolver()));

        $v->setData(['id' => 1]);
        expect($v->passes())->toBeTrue();
        $v->setData(['id' => 2]);
        expect($v->passes())->toBeTrue();
    });

