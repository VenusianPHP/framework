<?php

namespace Tests\Validation;

use Voyager\Vessel\Vessel;
use Voyager\Http\UploadedFile;
use Voyager\NutsAndBolts\DataObjects\Arr;
use Voyager\MagicAliases\MagicAlias;
use Voyager\Translation\ArrayLoader;
use Voyager\Translation\Translator;
use Voyager\Validation\Rule;
use Voyager\Validation\Rules\File;
use Voyager\Validation\ValidationServiceProvider;
use Voyager\Validation\Validator;
use PHPUnit\Framework\TestCase;

class ValidationImageFileRuleTest extends TestCase
{
    public function testDimensions()
    {
        $this->fails(
            File::image()->dimensions(Rule::dimensions()->width(100)->height(100)),
            UploadedFile::fake()->image('foo.png', 101, 101),
            ['validation.dimensions'],
        );

        $this->passes(
            File::image()->dimensions(Rule::dimensions()->width(100)->height(100)),
            UploadedFile::fake()->image('foo.png', 100, 100),
        );
    }

    public function testDimensionsWithCustomImageSizeMethod()
    {
        $this->fails(
            File::image()->dimensions(Rule::dimensions()->width(100)->height(100)),
            new UploadedFileWithCustomImageSizeMethod(stream_get_meta_data($tmpFile = tmpfile())['uri'], 'foo.png'),
            ['validation.dimensions'],
        );

        $this->passes(
            File::image()->dimensions(Rule::dimensions()->width(200)->height(200)),
            new UploadedFileWithCustomImageSizeMethod(stream_get_meta_data($tmpFile = tmpfile())['uri'], 'foo.png'),
        );
    }

    public function testDimensionWithTheRatioMethod()
    {
        $this->fails(
            File::image()->dimensions(Rule::dimensions()->ratio(1)),
            UploadedFile::fake()->image('foo.png', 105, 100),
            ['validation.dimensions'],
        );

        $this->passes(
            File::image()->dimensions(Rule::dimensions()->ratio(1)),
            UploadedFile::fake()->image('foo.png', 100, 100),
        );
    }

    public function testDimensionWithTheMinRatioMethod()
    {
        $this->fails(
            File::image()->dimensions(Rule::dimensions()->minRatio(1 / 2)),
            UploadedFile::fake()->image('foo.png', 100, 100),
            ['validation.dimensions'],
        );

        $this->passes(
            File::image()->dimensions(Rule::dimensions()->minRatio(1 / 2)),
            UploadedFile::fake()->image('foo.png', 100, 200),
        );
    }

    public function testDimensionWithTheMaxRatioMethod()
    {
        $this->fails(
            File::image()->dimensions(Rule::dimensions()->maxRatio(1 / 2)),
            UploadedFile::fake()->image('foo.png', 100, 300),
            ['validation.dimensions'],
        );

        $this->passes(
            File::image()->dimensions(Rule::dimensions()->maxRatio(1 / 2)),
            UploadedFile::fake()->image('foo.png', 100, 100),
        );
    }

    public function testDimensionWithTheRatioBetweenMethod()
    {
        $this->fails(
            File::image()->dimensions(Rule::dimensions()->ratioBetween(1 / 2, 1 / 3)),
            UploadedFile::fake()->image('foo.png', 100, 100),
            ['validation.dimensions'],
        );

        $this->passes(
            File::image()->dimensions(Rule::dimensions()->ratioBetween(1 / 2, 1 / 3)),
            UploadedFile::fake()->image('foo.png', 100, 200),
        );
    }

    protected function fails($rule, $values, $messages)
    {
        $this->assertValidationRules($rule, $values, false, $messages);
    }

    protected function assertValidationRules($rule, $values, $result, $messages)
    {
        $values = Arr::wrap($values);

        foreach ($values as $value) {
            $v = new Validator(
                resolve('translator'),
                ['my_file' => $value],
                ['my_file' => is_object($rule) ? clone $rule : $rule]
            );

            $this->assertSame($result, $v->passes());

            $this->assertSame(
                $result ? [] : ['my_file' => $messages],
                $v->messages()->toArray()
            );
        }
    }

    protected function passes($rule, $values)
    {
        $this->assertValidationRules($rule, $values, true, []);
    }

    protected function setUp(): void
    {
        $container = Vessel::getInstance();

        $container->bind('translator', function () {
            return new Translator(
                new ArrayLoader, 'en'
            );
        });

        MagicAlias::setMagicAliasApplication($container);

        (new ValidationServiceProvider($container))->register();
    }

    protected function tearDown(): void
    {
        Vessel::setInstance(null);

        MagicAlias::clearResolvedInstances();

        MagicAlias::setMagicAliasApplication(null);

        parent::tearDown();
    }
}

class UploadedFileWithCustomImageSizeMethod extends UploadedFile
{
    public function isValid(): bool
    {
        return true;
    }

    public function guessExtension(): string
    {
        return 'png';
    }

    public function dimensions()
    {
        return [200, 200];
    }
}
