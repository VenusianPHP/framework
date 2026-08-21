<?php

use Voyager\Http\UploadedFile;
use Voyager\MagicAliases\MagicAlias;
use Voyager\NutsAndBolts\DataObjects\Arr;
use Voyager\Translation\ArrayLoader;
use Voyager\Translation\Translator;
use Voyager\Validation\Rule;
use Voyager\Validation\Rules\File;
use Voyager\Validation\ValidationServiceProvider;
use Voyager\Validation\Validator;
use Voyager\Vessel\Vessel;

function imageFileAssertValidationRules($rule, $values, $result, $messages)
{
    $values = Arr::wrap($values);

    foreach ($values as $value) {
        $v = new Validator(
            resolve('translator'),
            ['my_file' => $value],
            ['my_file' => is_object($rule) ? clone $rule : $rule]
        );

        expect($v->passes())->toBe($result);

        expect($v->messages()->toArray())->toBe(
            $result ? [] : ['my_file' => $messages]
        );
    }
}

function imageFileFails($rule, $values, $messages)
{
    imageFileAssertValidationRules($rule, $values, false, $messages);
}

function imageFilePasses($rule, $values)
{
    imageFileAssertValidationRules($rule, $values, true, []);
}

beforeEach(function () {
    $container = Vessel::getInstance();

    $container->bind('translator', function () {
        return new Translator(
            new ArrayLoader, 'en'
        );
    });

    MagicAlias::setMagicAliasApplication($container);

    (new ValidationServiceProvider($container))->register();
});

afterEach(function () {
    Vessel::setInstance(null);

    MagicAlias::clearResolvedInstances();

    MagicAlias::setMagicAliasApplication(null);
});

test('dimensions', function () {
        imageFileFails(
            File::image()->dimensions(Rule::dimensions()->width(100)->height(100)),
            UploadedFile::fake()->image('foo.png', 101, 101),
            ['validation.dimensions'],
        );

        imageFilePasses(
            File::image()->dimensions(Rule::dimensions()->width(100)->height(100)),
            UploadedFile::fake()->image('foo.png', 100, 100),
        );
    });

test('dimensions with custom image size method', function () {
        imageFileFails(
            File::image()->dimensions(Rule::dimensions()->width(100)->height(100)),
            new UploadedFileWithCustomImageSizeMethod(stream_get_meta_data($tmpFile = tmpfile())['uri'], 'foo.png'),
            ['validation.dimensions'],
        );

        imageFilePasses(
            File::image()->dimensions(Rule::dimensions()->width(200)->height(200)),
            new UploadedFileWithCustomImageSizeMethod(stream_get_meta_data($tmpFile = tmpfile())['uri'], 'foo.png'),
        );
    });

test('dimension with the ratio method', function () {
        imageFileFails(
            File::image()->dimensions(Rule::dimensions()->ratio(1)),
            UploadedFile::fake()->image('foo.png', 105, 100),
            ['validation.dimensions'],
        );

        imageFilePasses(
            File::image()->dimensions(Rule::dimensions()->ratio(1)),
            UploadedFile::fake()->image('foo.png', 100, 100),
        );
    });

test('dimension with the min ratio method', function () {
        imageFileFails(
            File::image()->dimensions(Rule::dimensions()->minRatio(1 / 2)),
            UploadedFile::fake()->image('foo.png', 100, 100),
            ['validation.dimensions'],
        );

        imageFilePasses(
            File::image()->dimensions(Rule::dimensions()->minRatio(1 / 2)),
            UploadedFile::fake()->image('foo.png', 100, 200),
        );
    });

test('dimension with the max ratio method', function () {
        imageFileFails(
            File::image()->dimensions(Rule::dimensions()->maxRatio(1 / 2)),
            UploadedFile::fake()->image('foo.png', 100, 300),
            ['validation.dimensions'],
        );

        imageFilePasses(
            File::image()->dimensions(Rule::dimensions()->maxRatio(1 / 2)),
            UploadedFile::fake()->image('foo.png', 100, 100),
        );
    });

test('dimension with the ratio between method', function () {
        imageFileFails(
            File::image()->dimensions(Rule::dimensions()->ratioBetween(1 / 2, 1 / 3)),
            UploadedFile::fake()->image('foo.png', 100, 100),
            ['validation.dimensions'],
        );

        imageFilePasses(
            File::image()->dimensions(Rule::dimensions()->ratioBetween(1 / 2, 1 / 3)),
            UploadedFile::fake()->image('foo.png', 100, 200),
        );
    });


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
