<?php

use Voyager\Contracts\Translation\Translator as TranslatorInterface;
use Voyager\Validation\Factory;
use Voyager\Validation\PresenceVerifierInterface;
use Voyager\Validation\Validator;
use Voyager\Vessel\Vessel;

test('make method creates valid validator', function () {
        $translator = Mockery::mock(TranslatorInterface::class);
        $factory = new Factory($translator);
        $validator = $factory->make(['foo' => 'bar'], ['baz' => 'boom']);
        expect($validator->getTranslator())->toEqual($translator);
        expect($validator->getData())->toEqual(['foo' => 'bar']);
        expect($validator->getRules())->toEqual(['baz' => ['boom']]);

        $presence = Mockery::mock(PresenceVerifierInterface::class);
        $noop1 = function () {
            //
        };
        $noop2 = function () {
            //
        };
        $noop3 = function () {
            //
        };
        $factory->extend('foo', $noop1);
        $factory->extendImplicit('implicit', $noop2);
        $factory->extendDependent('dependent', $noop3);
        $factory->replacer('replacer', $noop3);
        $factory->setPresenceVerifier($presence);
        $validator = $factory->make([], []);
        expect($validator->extensions)->toEqual(['foo' => $noop1, 'implicit' => $noop2, 'dependent' => $noop3]);
        expect($validator->replacers)->toEqual(['replacer' => $noop3]);
        expect($validator->getPresenceVerifier())->toEqual($presence);

        $presence = Mockery::mock(PresenceVerifierInterface::class);
        $factory->extend('foo', $noop1, 'foo!');
        $factory->extendImplicit('implicit', $noop2, 'implicit!');
        $factory->extendImplicit('dependent', $noop3, 'dependent!');
        $factory->setPresenceVerifier($presence);
        $validator = $factory->make([], []);
        expect($validator->extensions)->toEqual(['foo' => $noop1, 'implicit' => $noop2, 'dependent' => $noop3]);
        expect($validator->fallbackMessages)->toEqual(['foo' => 'foo!', 'implicit' => 'implicit!', 'dependent' => 'dependent!']);
        expect($validator->getPresenceVerifier())->toEqual($presence);
    });

test('validate calls validate on the validator', function () {
        $validator = Mockery::mock(Validator::class);
        $translator = Mockery::mock(TranslatorInterface::class);
        $factory = Mockery::mock(Factory::class.'[make]', [$translator]);

        $factory->shouldReceive('make')->once()
            ->with(['foo' => 'bar', 'baz' => 'boom'], ['foo' => 'required'], [], [])
            ->andReturn($validator);

        $validator->shouldReceive('validate')->once()->andReturn(['foo' => 'bar']);

        $validated = $factory->validate(
            ['foo' => 'bar', 'baz' => 'boom'],
            ['foo' => 'required']
        );

        expect($validated)->toEqual(['foo' => 'bar']);
    });

test('custom resolver is called', function () {
        unset($_SERVER['__validator.factory']);
        $translator = Mockery::mock(TranslatorInterface::class);
        $factory = new Factory($translator);
        $factory->resolver(function ($translator, $data, $rules) {
            $_SERVER['__validator.factory'] = true;

            return new Validator($translator, $data, $rules);
        });
        $validator = $factory->make(['foo' => 'bar'], ['baz' => 'boom']);

        expect($_SERVER['__validator.factory'])->toBeTrue();
        expect($validator->getTranslator())->toEqual($translator);
        expect($validator->getData())->toEqual(['foo' => 'bar']);
        expect($validator->getRules())->toEqual(['baz' => ['boom']]);
        unset($_SERVER['__validator.factory']);
    });

test('validate method can be called publicly', function () {
        $translator = Mockery::mock(TranslatorInterface::class);
        $factory = new Factory($translator);
        $factory->extend('foo', function ($attribute, $value, $parameters, $validator) {
            return $validator->validateArray($attribute, $value);
        });

        $validator = $factory->make(['bar' => ['baz']], ['bar' => 'foo']);
        expect($validator->passes())->toBeTrue();
    });

test('exclude and include unvalidated array keys', function () {
        $translator = Mockery::mock(TranslatorInterface::class);

        $factory = new Factory($translator);
        // check the default behaviour.
        $validator1 = $factory->make(['key' => ['val']], ['key' => 'required']);
        expect($validator1->excludeUnvalidatedArrayKeys)->toBeTrue();

        $factory->excludeUnvalidatedArrayKeys();
        $validator2 = $factory->make(['key' => ['val']], ['key' => 'required']);
        expect($validator2->excludeUnvalidatedArrayKeys)->toBeTrue();

        $factory->includeUnvalidatedArrayKeys();
        $validator3 = $factory->make(['key' => ['val']], ['key' => 'required']);
        expect($validator3->excludeUnvalidatedArrayKeys)->toBeFalse();

        // checks it does not switch behaviour automatically.
        $validator4 = $factory->make(['key' => ['val']], ['key' => 'required']);
        expect($validator4->excludeUnvalidatedArrayKeys)->toBeFalse();

        // checks it can switch.
        $factory->excludeUnvalidatedArrayKeys();
        $validator5 = $factory->make(['key' => ['val']], ['key' => 'required']);
        expect($validator5->excludeUnvalidatedArrayKeys)->toBeTrue();

        // checks switching does not affect previously created validator objects.
        expect($validator1->excludeUnvalidatedArrayKeys)->toBeTrue();
        expect($validator2->excludeUnvalidatedArrayKeys)->toBeTrue();
        expect($validator3->excludeUnvalidatedArrayKeys)->toBeFalse();
        expect($validator4->excludeUnvalidatedArrayKeys)->toBeFalse();
    });

test('set container', function () {
        $translator = Mockery::mock(TranslatorInterface::class);
        $container = new Vessel;
        $factory = new Factory($translator);

        expect($factory->getContainer())->toBeNull();

        expect($factory->setContainer($container)->getContainer())->toBe($container);
    });

