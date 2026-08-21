<?php

namespace Tests\Database;

use Voyager\Database\Capsule\Manager as DB;
use Voyager\Database\Instrument\Model as Instrument;
use Voyager\Database\Query\Expression;

/**
 * Get a database connection instance.
 *
 * @return \Voyager\Database\ConnectionInterface
 */
function dbBtmAggregateConnection()
{
    return Instrument::getConnectionResolver()->connection();
}

/**
 * Get a schema builder instance.
 *
 * @return \Voyager\Database\Schema\Builder
 */
function dbBtmAggregateSchema()
{
    return dbBtmAggregateConnection()->getSchemaBuilder();
}

/**
 * Setup the database schema.
 *
 * @return void
 */
function dbBtmAggregateCreateSchema()
{
    dbBtmAggregateSchema()->create('orders', function ($table) {
        $table->increments('id');
    });

    dbBtmAggregateSchema()->create('products', function ($table) {
        $table->increments('id');
    });

    dbBtmAggregateSchema()->create('order_product', function ($table) {
        $table->integer('order_id')->unsigned();
        $table->foreign('order_id')->references('id')->on('orders');
        $table->integer('product_id')->unsigned();
        $table->foreign('product_id')->references('id')->on('products');
        $table->integer('quantity')->unsigned();
    });

    dbBtmAggregateSchema()->create('transactions', function ($table) {
        $table->increments('id');
        $table->integer('value')->unsigned();
    });

    dbBtmAggregateSchema()->create('allocations', function ($table) {
        $table->integer('from_id')->unsigned();
        $table->foreign('from_id')->references('id')->on('transactions');
        $table->integer('to_id')->unsigned();
        $table->foreign('to_id')->references('id')->on('transactions');
        $table->integer('amount')->unsigned();
    });
}

/**
 * Helpers...
 */
function dbBtmAggregateSeedData()
{
    $order = BelongsToManyAggregateTestTestOrder::create(['id' => 1]);

    BelongsToManyAggregateTestTestProduct::query()->insert([
        ['id' => 1],
        ['id' => 2],
        ['id' => 3],
    ]);

    $order->products()->sync([
        1 => ['quantity' => 3],
        2 => ['quantity' => 4],
        3 => ['quantity' => 5],
    ]);

    $transaction = BelongsToManyAggregateTestTestTransaction::create(['id' => 1, 'value' => 1200]);

    BelongsToManyAggregateTestTestTransaction::query()->insert([
        ['id' => 2, 'value' => -300],
        ['id' => 3, 'value' => -400],
        ['id' => 4, 'value' => -500],
    ]);

    $transaction->allocatedTo()->sync([
        2 => ['amount' => 300],
        3 => ['amount' => 400],
        4 => ['amount' => 500],
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

    dbBtmAggregateCreateSchema();
});

afterEach(function () {
    dbBtmAggregateSchema()->drop('orders');
    dbBtmAggregateSchema()->drop('products');
});

test('with sum different tables', function () {
    dbBtmAggregateSeedData();

    $order = BelongsToManyAggregateTestTestOrder::query()
        ->withSum('products as total_products', 'order_product.quantity')
        ->first();

    expect($order->total_products)->toEqual(12);
});

test('with sum same table', function () {
    dbBtmAggregateSeedData();

    $order = BelongsToManyAggregateTestTestTransaction::query()
        ->withSum('allocatedTo as total_allocated', 'allocations.amount')
        ->first();

    expect($order->total_allocated)->toEqual(1200);
});

test('with sum expression', function () {
    dbBtmAggregateSeedData();

    $order = BelongsToManyAggregateTestTestTransaction::query()
        ->withSum('allocatedTo as total_allocated', new Expression('allocations.amount * 2'))
        ->first();

    expect($order->total_allocated)->toEqual(2400);
});

class BelongsToManyAggregateTestTestOrder extends Instrument
{
    protected $table = 'orders';
    protected $fillable = ['id'];
    public $timestamps = false;

    public function products()
    {
        return $this
            ->belongsToMany(BelongsToManyAggregateTestTestProduct::class, 'order_product', 'order_id', 'product_id')
            ->withPivot('quantity');
    }
}

class BelongsToManyAggregateTestTestProduct extends Instrument
{
    protected $table = 'products';
    protected $fillable = ['id'];
    public $timestamps = false;
}

class BelongsToManyAggregateTestTestTransaction extends Instrument
{
    protected $table = 'transactions';
    protected $fillable = ['id', 'value'];
    public $timestamps = false;

    public function allocatedTo()
    {
        return $this
            ->belongsToMany(BelongsToManyAggregateTestTestTransaction::class, 'allocations', 'from_id', 'to_id')
            ->withPivot('quantity');
    }
}
