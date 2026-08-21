<?php

namespace Tests\Database;

use Voyager\Database\Capsule\Manager as DB;
use Voyager\Database\Instrument\Model;
use Voyager\NutsAndBolts\DataObjects\Carbon;

function irregularPluralSchema()
{
    $connection = Model::getConnectionResolver()->connection();

    return $connection->getSchemaBuilder();
}

function irregularPluralCreateSchema()
{
    irregularPluralSchema()->create('irregular_plural_humans', function ($table) {
        $table->increments('id');
        $table->string('email')->unique();
        $table->timestamps();
    });

    irregularPluralSchema()->create('irregular_plural_tokens', function ($table) {
        $table->increments('id');
        $table->string('title');
    });

    irregularPluralSchema()->create('irregular_plural_human_irregular_plural_token', function ($table) {
        $table->integer('irregular_plural_human_id')->unsigned();
        $table->integer('irregular_plural_token_id')->unsigned();
    });

    irregularPluralSchema()->create('irregular_plural_mottoes', function ($table) {
        $table->increments('id');
        $table->string('name');
    });

    irregularPluralSchema()->create('cool_mottoes', function ($table) {
        $table->integer('irregular_plural_motto_id');
        $table->integer('cool_motto_id');
        $table->string('cool_motto_type');
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
    irregularPluralCreateSchema();
});

afterEach(function () {
    irregularPluralSchema()->drop('irregular_plural_tokens');
    irregularPluralSchema()->drop('irregular_plural_humans');
    irregularPluralSchema()->drop('irregular_plural_human_irregular_plural_token');

    Carbon::setTestNow(null);
});

test('it pluralizes the table name', function () {
    $model = new IrregularPluralHuman;

    $this->assertSame('irregular_plural_humans', $model->getTable());
});

test('it touches the parent with an irregular plural', function () {
    Carbon::setTestNow('2018-05-01 12:13:14');

    IrregularPluralHuman::create(['email' => 'taylorotwell@gmail.com']);

    IrregularPluralToken::insert([
        ['title' => 'The title'],
    ]);

    $human = IrregularPluralHuman::query()->first();

    $tokenIds = IrregularPluralToken::pluck('id');

    Carbon::setTestNow('2018-05-01 15:16:17');

    $human->irregularPluralTokens()->sync($tokenIds);

    $human->refresh();

    $this->assertSame('2018-05-01 12:13:14', (string) $human->created_at);
    $this->assertSame('2018-05-01 15:16:17', (string) $human->updated_at);
});

test('it pluralizes morph to many relationships', function () {
    $human = IrregularPluralHuman::create(['email' => 'bobby@example.com']);

    $human->mottoes()->create(['name' => 'Real eyes realize real lies']);

    $motto = IrregularPluralMotto::query()->first();

    $this->assertSame('Real eyes realize real lies', $motto->name);
});

class IrregularPluralHuman extends Model
{
    protected $guarded = [];

    public function irregularPluralTokens()
    {
        return $this->belongsToMany(
            IrregularPluralToken::class,
            'irregular_plural_human_irregular_plural_token',
            'irregular_plural_token_id',
            'irregular_plural_human_id'
        );
    }

    public function mottoes()
    {
        return $this->morphToMany(IrregularPluralMotto::class, 'cool_motto');
    }
}

class IrregularPluralToken extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    protected $touches = [
        'irregularPluralHumans',
    ];
}

class IrregularPluralMotto extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    public function irregularPluralHumans()
    {
        return $this->morphedByMany(IrregularPluralHuman::class, 'cool_motto');
    }
}
