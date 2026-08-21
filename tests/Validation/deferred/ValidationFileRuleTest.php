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

function fileRuleAssertValidationRules($rule, $values, $result, $messages)
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

function fileRuleFails($rule, $values, $messages)
{
    fileRuleAssertValidationRules($rule, $values, false, $messages);
}

function fileRulePasses($rule, $values)
{
    fileRuleAssertValidationRules($rule, $values, true, []);
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

test('basic', function () {
        fileRuleFails(
            File::default(),
            'foo',
            ['validation.file'],
        );

        fileRulePasses(
            File::default(),
            UploadedFile::fake()->create('foo.bar'),
        );

        fileRulePasses(File::default(), null);
    });

test('single mimetype', function () {
        fileRuleFails(
            File::types('text/plain'),
            UploadedFile::fake()->createWithContent('foo.png', file_get_contents(__DIR__.'/fixtures/image.png')),
            ['validation.mimetypes']
        );

        fileRulePasses(
            File::types('image/png'),
            UploadedFile::fake()->createWithContent('foo.png', file_get_contents(__DIR__.'/fixtures/image.png')),
        );
    });

test('multiple mime types', function () {
        fileRuleFails(
            File::types(['text/plain', 'image/jpeg']),
            UploadedFile::fake()->createWithContent('foo.png', file_get_contents(__DIR__.'/fixtures/image.png')),
            ['validation.mimetypes']
        );

        fileRulePasses(
            File::types(['text/plain', 'image/png']),
            UploadedFile::fake()->createWithContent('foo.png', file_get_contents(__DIR__.'/fixtures/image.png')),
        );
    });

test('single mime', function () {
        fileRuleFails(
            File::types('txt'),
            UploadedFile::fake()->createWithContent('foo.png', file_get_contents(__DIR__.'/fixtures/image.png')),
            ['validation.mimes']
        );

        fileRulePasses(
            File::types('png'),
            UploadedFile::fake()->createWithContent('foo.png', file_get_contents(__DIR__.'/fixtures/image.png')),
        );
    });

test('multiple mimes', function () {
        fileRuleFails(
            File::types(['png', 'jpg', 'jpeg', 'svg']),
            UploadedFile::fake()->createWithContent('foo.txt', 'Hello World!'),
            ['validation.mimes']
        );

        fileRulePasses(
            File::types(['png', 'jpg', 'jpeg', 'svg']),
            [
                UploadedFile::fake()->createWithContent('foo.png', file_get_contents(__DIR__.'/fixtures/image.png')),
                UploadedFile::fake()->createWithContent('foo.svg', file_get_contents(__DIR__.'/fixtures/image.svg')),
            ]
        );
    });

test('mix of mimetypes and mimes', function () {
        fileRuleFails(
            File::types(['png', 'image/png']),
            UploadedFile::fake()->createWithContent('foo.txt', 'Hello World!'),
            ['validation.mimetypes', 'validation.mimes']
        );

        fileRulePasses(
            File::types(['png', 'image/png']),
            UploadedFile::fake()->createWithContent('foo.png', file_get_contents(__DIR__.'/fixtures/image.png')),
        );
    });

test('single extension', function () {
        fileRuleFails(
            File::default()->extensions('png'),
            UploadedFile::fake()->createWithContent('foo', file_get_contents(__DIR__.'/fixtures/image.png')),
            ['validation.extensions']
        );

        fileRuleFails(
            File::default()->extensions('png'),
            UploadedFile::fake()->createWithContent('foo.jpg', file_get_contents(__DIR__.'/fixtures/image.png')),
            ['validation.extensions']
        );

        fileRuleFails(
            File::default()->extensions('jpeg'),
            UploadedFile::fake()->createWithContent('foo.jpg', file_get_contents(__DIR__.'/fixtures/image.png')),
            ['validation.extensions']
        );

        fileRulePasses(
            File::default()->extensions('png'),
            UploadedFile::fake()->createWithContent('foo.png', file_get_contents(__DIR__.'/fixtures/image.png')),
        );
    });

test('multiple extensions', function () {
        fileRuleFails(
            File::default()->extensions(['png', 'jpeg', 'jpg']),
            UploadedFile::fake()->createWithContent('foo', file_get_contents(__DIR__.'/fixtures/image.png')),
            ['validation.extensions']
        );

        fileRuleFails(
            File::default()->extensions(['png', 'jpeg']),
            UploadedFile::fake()->createWithContent('foo.jpg', file_get_contents(__DIR__.'/fixtures/image.png')),
            ['validation.extensions']
        );

        fileRulePasses(
            File::default()->extensions(['png', 'jpeg', 'jpg']),
            UploadedFile::fake()->createWithContent('foo.png', file_get_contents(__DIR__.'/fixtures/image.png')),
        );
    });

test('image', function () {
        fileRuleFails(
            File::image(),
            UploadedFile::fake()->createWithContent('foo.txt', 'Hello World!'),
            ['validation.image']
        );

        fileRulePasses(
            File::image(),
            UploadedFile::fake()->image('foo.png'),
        );
    });

test('image fails on svg by default', function () {
        $maliciousSvgFileWithXSS = UploadedFile::fake()->createWithContent(
            name: 'foo.svg',
            content: <<<'XML'
                    <svg xmlns="http://www.w3.org/2000/svg" width="383" height="97" viewBox="0 0 383 97">
                        <text x="10" y="50" font-size="30" fill="black">XSS Logo</text>
                        <script>alert('XSS');</script>
                    </svg>
                    XML
        );

        fileRuleFails(
            File::image(),
            $maliciousSvgFileWithXSS,
            ['validation.image']
        );
        fileRuleFails(
            Rule::imageFile(),
            $maliciousSvgFileWithXSS,
            ['validation.image']
        );

        fileRulePasses(
            File::image(allowSvg: true),
            $maliciousSvgFileWithXSS
        );
        fileRulePasses(
            Rule::imageFile(allowSvg: true),
            $maliciousSvgFileWithXSS
        );
    });

test('size', function () {
        fileRuleFails(
            File::default()->size(1024),
            [
                UploadedFile::fake()->create('foo.txt', 1025),
                UploadedFile::fake()->create('foo.txt', 1023),
            ],
            ['validation.size.file']
        );

        fileRulePasses(
            File::default()->size(1024),
            UploadedFile::fake()->create('foo.txt', 1024),
        );
    });

test('between', function () {
        fileRuleFails(
            File::default()->between(1024, 2048),
            [
                UploadedFile::fake()->create('foo.txt', 1023),
                UploadedFile::fake()->create('foo.txt', 2049),
            ],
            ['validation.between.file']
        );

        fileRulePasses(
            File::default()->between(1024, 2048),
            [
                UploadedFile::fake()->create('foo.txt', 1024),
                UploadedFile::fake()->create('foo.txt', 2048),
                UploadedFile::fake()->create('foo.txt', 1025),
                UploadedFile::fake()->create('foo.txt', 2047),
            ]
        );
    });

test('min', function () {
        fileRuleFails(
            File::default()->min(1024),
            UploadedFile::fake()->create('foo.txt', 1023),
            ['validation.min.file']
        );

        fileRulePasses(
            File::default()->min(1024),
            [
                UploadedFile::fake()->create('foo.txt', 1024),
                UploadedFile::fake()->create('foo.txt', 1025),
                UploadedFile::fake()->create('foo.txt', 2048),
            ]
        );
    });

test('min with human readable size', function () {
        fileRuleFails(
            File::default()->min('1024kb'),
            UploadedFile::fake()->create('foo.txt', 1023),
            ['validation.min.file']
        );

        fileRulePasses(
            File::default()->min('1024kb'),
            [
                UploadedFile::fake()->create('foo.txt', 1024),
                UploadedFile::fake()->create('foo.txt', 1025),
                UploadedFile::fake()->create('foo.txt', 2048),
            ]
        );
    });

test('max', function () {
        fileRuleFails(
            File::default()->max(1024),
            UploadedFile::fake()->create('foo.txt', 1025),
            ['validation.max.file']
        );

        fileRulePasses(
            File::default()->max(1024),
            [
                UploadedFile::fake()->create('foo.txt', 1024),
                UploadedFile::fake()->create('foo.txt', 1023),
                UploadedFile::fake()->create('foo.txt', 512),
            ]
        );
    });

test('max with human readable size', function () {
        fileRuleFails(
            File::default()->max('1024kb'),
            UploadedFile::fake()->create('foo.txt', 1025),
            ['validation.max.file']
        );

        fileRulePasses(
            File::default()->max('1024kb'),
            [
                UploadedFile::fake()->create('foo.txt', 1024),
                UploadedFile::fake()->create('foo.txt', 1023),
                UploadedFile::fake()->create('foo.txt', 512),
            ]
        );
    });

test('max with human readable size and multiple value', function () {
        fileRuleFails(
            File::default()->max('1mb'),
            UploadedFile::fake()->create('foo.txt', 1025),
            ['validation.max.file']
        );

        fileRulePasses(
            File::default()->max('1mb'),
            [
                UploadedFile::fake()->create('foo.txt', 1000),
                UploadedFile::fake()->create('foo.txt', 999),
                UploadedFile::fake()->create('foo.txt', 512),
            ]
        );
    });

test('encoding', function () {
        // ASCII file containing UTF-8.
        fileRuleFails(
            File::default()->encoding('ascii'),
            UploadedFile::fake()->createWithContent('foo.txt', '✌️'),
            ['validation.encoding'],
        );

        // UTF-8 file containing invalid UTF-8 byte sequence.
        fileRuleFails(
            File::default()->encoding('utf-8'),
            UploadedFile::fake()->createWithContent('foo.txt', "\xf0\x28\x8c\x28"),
            ['validation.encoding'],
        );

        fileRulePasses(
            File::default()->encoding('utf-8'),
            UploadedFile::fake()->createWithContent('foo.txt', '✌️'),
        );

        fileRulePasses(
            File::default()->encoding('utf-8'),
            [
                UploadedFile::fake()->createWithContent('foo-1.txt', '✌️'),
                UploadedFile::fake()->createWithContent('foo-2.txt', '👍'),
            ]
        );
    });

test('encoding with invalid parameter', function () {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Validation rule encoding parameter [FOOBAR] is not a valid encoding.');

        // Invalid encoding.
        fileRuleFails(
            File::default()->encoding('FOOBAR'),
            UploadedFile::fake()->createWithContent('foo.txt', ''),
            ['validation.encoding'],
        );
    });

test('macro', function () {
        File::macro('toDocument', function () {
            return static::default()->rules('mimes:txt,csv');
        });

        fileRuleFails(
            File::toDocument(),
            UploadedFile::fake()->create('foo.png'),
            ['validation.mimes']
        );

        fileRulePasses(
            File::toDocument(),
            [
                UploadedFile::fake()->create('foo.txt'),
                UploadedFile::fake()->create('foo.csv'),
            ]
        );
    });

test('it uses the correct validation message for file', function () {
        file_put_contents($path = __DIR__.'/test.json', 'this-is-a-test');

        $file = new \Voyager\Http\File($path);

        fileRuleFails(
            ['max:0'],
            $file,
            ['validation.max.file']
        );

        unlink($path);
    });

test('it can set default using', function () {
        expect(File::default())->toBeInstanceOf(File::class);

        File::defaults(function () {
            return File::types('txt')->max(12 * 1024);
        });

        fileRuleFails(
            File::default(),
            UploadedFile::fake()->create('foo.png', 13 * 1024),
            [
                'validation.mimes',
                'validation.max.file',
            ]
        );

        File::defaults(File::image()->between(1024, 2048));

        fileRulePasses(
            File::default(),
            UploadedFile::fake()->create('foo.png', 1.5 * 1024),
        );
    });

test('file size conversion with different units', function () {
        fileRulePasses(
            File::image()->size('5MB'),
            UploadedFile::fake()->create('foo.png', 5000)
        );

        fileRulePasses(
            File::image()->size(' 2gb '),
            UploadedFile::fake()->create('foo.png', 2 * 1000000)
        );

        fileRulePasses(
            File::image()->size('1Tb'),
            UploadedFile::fake()->create('foo.png', 1000000000)
        );

        $this->expectException(\InvalidArgumentException::class);
        File::image()->size('10xyz');
    });

