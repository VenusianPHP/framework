<?php

use Voyager\Database\Instrument\Model;
use Voyager\Database\Instrument\SoftDeletes;
use Voyager\NutsAndBolts\DataObjects\Carbon;

test('deleted at is added to casts as default type', function () {
    $model = new SoftDeletingModel;

    $this->assertArrayHasKey('deleted_at', $model->getCasts());
    $this->assertSame('datetime', $model->getCasts()['deleted_at']);
});

test('deleted at is cast to carbon instance', function () {
    $expected = Carbon::createFromFormat('Y-m-d H:i:s', '2018-12-29 13:59:39');
    $model = new SoftDeletingModel(['deleted_at' => $expected->format('Y-m-d H:i:s')]);

    expect($model->deleted_at)->toBeInstanceOf(Carbon::class);
    $this->assertTrue($expected->eq($model->deleted_at));
});

test('existing cast overrides added date cast', function () {
    $model = new class(['deleted_at' => '2018-12-29 13:59:39']) extends SoftDeletingModel
    {
        protected $casts = ['deleted_at' => 'bool'];
    };

    expect($model->deleted_at)->toBeTrue();
});

test('existing mutator overrides added date cast', function () {
    $model = new class(['deleted_at' => '2018-12-29 13:59:39']) extends SoftDeletingModel
    {
        protected function getDeletedAtAttribute()
        {
            return 'expected';
        }
    };

    expect($model->deleted_at)->toBe('expected');
});

test('casting to string overrides automatic date casting to retain previous behaviour', function () {
    $model = new class(['deleted_at' => '2018-12-29 13:59:39']) extends SoftDeletingModel
    {
        protected $casts = ['deleted_at' => 'string'];
    };

    expect($model->deleted_at)->toBe('2018-12-29 13:59:39');
});

class SoftDeletingModel extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $dateFormat = 'Y-m-d H:i:s';
}
