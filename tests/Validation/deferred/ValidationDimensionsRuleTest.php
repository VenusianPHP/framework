<?php

use Voyager\Http\UploadedFile;
use Voyager\Translation\ArrayLoader;
use Voyager\Translation\Translator;
use Voyager\Validation\Rule;
use Voyager\Validation\Rules\Dimensions;
use Voyager\Validation\Validator;

test('it correctly formats a string version of the rule', function () {
        $rule = new Dimensions(['min_width' => 100, 'min_height' => 100]);

        expect((string) $rule)->toBe('dimensions:min_width=100,min_height=100');

        $rule = Rule::dimensions()->width(200)->height(100);

        expect((string) $rule)->toBe('dimensions:width=200,height=100');

        $rule = Rule::dimensions()->maxWidth(1000)->maxHeight(500)->ratio(3 / 2);

        expect((string) $rule)->toBe('dimensions:max_width=1000,max_height=500,ratio=1.5');

        $rule = new Dimensions(['ratio' => '2/3']);

        expect((string) $rule)->toBe('dimensions:ratio=2/3');

        $rule = Rule::dimensions()->minWidth(300)->minHeight(400);

        expect((string) $rule)->toBe('dimensions:min_width=300,min_height=400');

        $rule = Rule::dimensions()
            ->when(true, function ($rule) {
                $rule->height('100');
            })
            ->unless(true, function ($rule) {
                $rule->width('200');
            });
        expect((string) $rule)->toBe('dimensions:height=100');

        $rule = Rule::dimensions()
            ->minRatio(1 / 2)
            ->maxRatio(1 / 3);
        expect((string) $rule)->toBe('dimensions:min_ratio=0.5,max_ratio=0.33333333333333');

        $rule = Rule::dimensions()
            ->ratioBetween(min: 1 / 2, max: 1 / 3);
        expect((string) $rule)->toBe('dimensions:min_ratio=0.5,max_ratio=0.33333333333333');
    });

test('it correctly formats with special values', function () {
        $rule = new Dimensions();

        expect((string) $rule)->toBe('dimensions:');

        $rule = Rule::dimensions()->width(-100)->height(-200);

        expect((string) $rule)->toBe('dimensions:width=-100,height=-200');

        $rule = Rule::dimensions()->width('300')->height('400');

        expect((string) $rule)->toBe('dimensions:width=300,height=400');
    });

test('dimensions rule maintains correct order', function () {
        $rule = Rule::dimensions()->minWidth(100)->width(200)->maxWidth(300);

        expect((string) $rule)->toBe('dimensions:min_width=100,width=200,max_width=300');
    });

test('overriding values', function () {
        $rule = Rule::dimensions()->width(100)->width(500);

        expect((string) $rule)->toBe('dimensions:width=500');
    });

test('ratio between overrides min and max ratio', function () {
        $rule = Rule::dimensions()->minRatio(0.5)->maxRatio(2.0)->ratioBetween(1, 1.5);

        expect((string) $rule)->toBe('dimensions:min_ratio=1,max_ratio=1.5');
    });

test('generates the correct validation messages', function () {
        $rule = Rule::dimensions()
            ->width(100)->height(100)
            ->ratioBetween(min: 1 / 2, max: 2 / 5);

        $trans = new Translator(new ArrayLoader, 'en');

        $image = UploadedFile::fake();

        $validator = new Validator(
            $trans,
            ['image' => $image],
            ['image' => $rule]
        );

        expect($validator->errors()->first('image'))->toBe($trans->get('validation.dimensions', ['width' => 100, 'height' => 100, 'min_ratio' => 0.5, 'max_ratio' => 0.4]));

        $validator = new Validator(
            $trans,
            ['image' => $image],
            ['image' => [$rule]]
        );

        expect($validator->errors()->first('image'))->toBe($trans->get('validation.dimensions', ['width' => 100, 'height' => 100, 'min_ratio' => 0.5, 'max_ratio' => 0.4]));
    });

