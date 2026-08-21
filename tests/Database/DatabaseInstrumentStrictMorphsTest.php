<?php

namespace Tests\Database;

use Voyager\Database\ClassMorphViolationException;
use Voyager\Database\Instrument\Model;
use Voyager\Database\Instrument\Relations\Pivot;
use Voyager\Database\Instrument\Relations\Relation;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class DatabaseInstrumentStrictMorphsTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected function setUp(): void
    {
        parent::setUp();

        Relation::requireMorphMap();
    }

    public function testStrictModeThrowsAnExceptionOnClassMap()
    {
        $this->expectException(ClassMorphViolationException::class);

        $model = new TestModel;

        $model->getMorphClass();
    }

    public function testStrictModeDoesNotThrowExceptionWhenMorphMap()
    {
        $model = new TestModel;

        Relation::morphMap([
            'test' => TestModel::class,
        ]);

        $morphName = $model->getMorphClass();
        $this->assertSame('test', $morphName);
    }

    public function testMapsCanBeEnforcedInOneMethod()
    {
        $model = new TestModel;

        Relation::requireMorphMap(false);

        Relation::enforceMorphMap([
            'test' => TestModel::class,
        ]);

        $morphName = $model->getMorphClass();
        $this->assertSame('test', $morphName);
    }

    public function testMapIgnoreGenericPivotClass()
    {
        $this->expectNotToPerformAssertions();

        $pivotModel = new Pivot();

        $pivotModel->getMorphClass();
    }

    public function testMapCanBeEnforcedToCustomPivotClass()
    {
        $this->expectException(ClassMorphViolationException::class);

        $pivotModel = new TestPivotModel();

        $pivotModel->getMorphClass();
    }

    protected function tearDown(): void
    {
        Relation::morphMap([], false);
        Relation::requireMorphMap(false);

        parent::tearDown();
    }
}

class TestModel extends Model
{
}

class TestPivotModel extends Pivot
{
}
