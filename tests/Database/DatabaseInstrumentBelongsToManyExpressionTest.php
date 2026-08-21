<?php

namespace Tests\Database;

use Exception;
use Voyager\Database\Capsule\Manager as DB;
use Voyager\Database\Instrument\Model as Instrument;
use Voyager\Database\Instrument\Relations\MorphToMany;
use Voyager\Database\Query\Expression;
use Voyager\Database\Schema\Blueprint;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class DatabaseInstrumentBelongsToManyExpressionTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected function setUp(): void
    {
        $db = new DB;

        $db->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        $db->bootInstrument();
        $db->setAsGlobal();

        $this->createSchema();
    }

    public function testAmbiguousColumnsExpression(): void
    {
        $this->seedData();

        $tags = DatabaseInstrumentBelongsToManyExpressionTestTestPost::findOrFail(1)
            ->tags()
            ->wherePivotNotIn(new Expression("tag_id || '_' || type"), ['1_t1'])
            ->get();

        $this->assertCount(1, $tags);
        $this->assertEquals(2, $tags->first()->getKey());
    }

    public function testQualifiedColumnExpression(): void
    {
        $this->seedData();

        $tags = DatabaseInstrumentBelongsToManyExpressionTestTestPost::findOrFail(2)
            ->tags()
            ->wherePivotNotIn(new Expression("taggables.tag_id || '_' || taggables.type"), ['2_t2'])
            ->get();

        $this->assertCount(1, $tags);
        $this->assertEquals(3, $tags->first()->getKey());
    }

    public function testGlobalScopesAreAppliedToBelongsToManyRelation(): void
    {
        $this->seedData();
        $post = DatabaseInstrumentBelongsToManyExpressionTestTestPost::query()->firstOrFail();
        DatabaseInstrumentBelongsToManyExpressionTestTestTag::addGlobalScope(
            'default',
            static fn () => throw new Exception('Default global scope.')
        );

        $this->expectExceptionMessage('Default global scope.');
        $post->tags()->get();
    }

    public function testGlobalScopesCanBeRemovedFromBelongsToManyRelation(): void
    {
        $this->seedData();
        $post = DatabaseInstrumentBelongsToManyExpressionTestTestPost::query()->firstOrFail();
        DatabaseInstrumentBelongsToManyExpressionTestTestTag::addGlobalScope(
            'default',
            static fn () => throw new Exception('Default global scope.')
        );

        $this->assertNotEmpty($post->tags()->withoutGlobalScopes()->get());
    }

    /**
     * Setup the database schema.
     *
     * @return void
     */
    public function createSchema()
    {
        $this->schema()->create('posts', fn (Blueprint $t) => $t->id());
        $this->schema()->create('tags', fn (Blueprint $t) => $t->id());
        $this->schema()->create('taggables', function (Blueprint $t) {
            $t->unsignedBigInteger('tag_id');
            $t->unsignedBigInteger('taggable_id');
            $t->string('type', 10);
            $t->string('taggable_type');
        }
        );
    }

    /**
     * Tear down the database schema.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $this->schema()->drop('posts');
        $this->schema()->drop('tags');
        $this->schema()->drop('taggables');

        parent::tearDown();
    }

    /**
     * Helpers...
     */
    protected function seedData(): void
    {
        $p1 = DatabaseInstrumentBelongsToManyExpressionTestTestPost::query()->create();
        $p2 = DatabaseInstrumentBelongsToManyExpressionTestTestPost::query()->create();
        $t1 = DatabaseInstrumentBelongsToManyExpressionTestTestTag::query()->create();
        $t2 = DatabaseInstrumentBelongsToManyExpressionTestTestTag::query()->create();
        $t3 = DatabaseInstrumentBelongsToManyExpressionTestTestTag::query()->create();

        $p1->tags()->sync([
            $t1->getKey() => ['type' => 't1'],
            $t2->getKey() => ['type' => 't2'],
        ]);
        $p2->tags()->sync([
            $t2->getKey() => ['type' => 't2'],
            $t3->getKey() => ['type' => 't3'],
        ]);
    }

    /**
     * Get a database connection instance.
     *
     * @return \Voyager\Database\ConnectionInterface
     */
    protected function connection()
    {
        return Instrument::getConnectionResolver()->connection();
    }

    /**
     * Get a schema builder instance.
     *
     * @return \Voyager\Database\Schema\Builder
     */
    protected function schema()
    {
        return $this->connection()->getSchemaBuilder();
    }
}

class DatabaseInstrumentBelongsToManyExpressionTestTestPost extends Instrument
{
    protected $table = 'posts';
    protected $fillable = ['id'];
    public $timestamps = false;

    public function tags(): MorphToMany
    {
        return  $this->morphToMany(
            DatabaseInstrumentBelongsToManyExpressionTestTestTag::class,
            'taggable',
            'taggables',
            'taggable_id',
            'tag_id',
            'id',
            'id',
        );
    }
}

class DatabaseInstrumentBelongsToManyExpressionTestTestTag extends Instrument
{
    protected $table = 'tags';
    protected $fillable = ['id'];
    public $timestamps = false;
}
