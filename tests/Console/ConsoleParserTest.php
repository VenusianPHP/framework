<?php

use Voyager\Console\Parser;

test('a bare signature yields just the command name', function () {
    $results = Parser::parse('command:name');

    expect($results[0])->toBe('command:name');
});

test('an argument and a valueless option are parsed', function () {
    $results = Parser::parse('command:name {argument} {--option}');

    expect($results[0])->toBe('command:name')
        ->and($results[1][0]->getName())->toBe('argument')
        ->and($results[2][0]->getName())->toBe('option')
        ->and($results[2][0]->acceptValue())->toBeFalse();
});

test('a required array argument and a value option are parsed', function () {
    $results = Parser::parse('command:name {argument*} {--option=}');

    expect($results[0])->toBe('command:name')
        ->and($results[1][0]->getName())->toBe('argument')
        ->and($results[1][0]->isArray())->toBeTrue()
        ->and($results[1][0]->isRequired())->toBeTrue()
        ->and($results[2][0]->getName())->toBe('option')
        ->and($results[2][0]->acceptValue())->toBeTrue();
});

test('an optional array argument and an array option are parsed', function () {
    $results = Parser::parse('command:name {argument?*} {--option=*}');

    expect($results[0])->toBe('command:name')
        ->and($results[1][0]->getName())->toBe('argument')
        ->and($results[1][0]->isArray())->toBeTrue()
        ->and($results[1][0]->isRequired())->toBeFalse()
        ->and($results[2][0]->getName())->toBe('option')
        ->and($results[2][0]->acceptValue())->toBeTrue()
        ->and($results[2][0]->isArray())->toBeTrue();
});

test('descriptions are parsed off arguments and options', function () {
    $results = Parser::parse('command:name {argument?* : The argument description.}    {--option=* : The option description.}');

    expect($results[0])->toBe('command:name')
        ->and($results[1][0]->getName())->toBe('argument')
        ->and($results[1][0]->getDescription())->toBe('The argument description.')
        ->and($results[1][0]->isArray())->toBeTrue()
        ->and($results[1][0]->isRequired())->toBeFalse()
        ->and($results[2][0]->getName())->toBe('option')
        ->and($results[2][0]->getDescription())->toBe('The option description.')
        ->and($results[2][0]->acceptValue())->toBeTrue()
        ->and($results[2][0]->isArray())->toBeTrue();
});

test('a multiline signature is parsed the same way', function () {
    $results = Parser::parse('command:name
            {argument?* : The argument description.}
            {--option=* : The option description.}');

    expect($results[0])->toBe('command:name')
        ->and($results[1][0]->getName())->toBe('argument')
        ->and($results[1][0]->getDescription())->toBe('The argument description.')
        ->and($results[1][0]->isArray())->toBeTrue()
        ->and($results[1][0]->isRequired())->toBeFalse()
        ->and($results[2][0]->getName())->toBe('option')
        ->and($results[2][0]->getDescription())->toBe('The option description.')
        ->and($results[2][0]->acceptValue())->toBeTrue()
        ->and($results[2][0]->isArray())->toBeTrue();
});

describe('shortcut names', function () {
    test('a valueless option carries its shortcut', function () {
        $results = Parser::parse('command:name {--o|option}');

        expect($results[2][0]->getShortcut())->toBe('o')
            ->and($results[2][0]->getName())->toBe('option')
            ->and($results[2][0]->acceptValue())->toBeFalse();
    });

    test('a value option carries its shortcut', function () {
        $results = Parser::parse('command:name {--o|option=}');

        expect($results[2][0]->getShortcut())->toBe('o')
            ->and($results[2][0]->getName())->toBe('option')
            ->and($results[2][0]->acceptValue())->toBeTrue();
    });

    test('an array option carries its shortcut', function () {
        $results = Parser::parse('command:name {--o|option=*}');

        expect($results[0])->toBe('command:name')
            ->and($results[2][0]->getShortcut())->toBe('o')
            ->and($results[2][0]->getName())->toBe('option')
            ->and($results[2][0]->acceptValue())->toBeTrue()
            ->and($results[2][0]->isArray())->toBeTrue();
    });

    test('a described array option carries its shortcut', function () {
        $results = Parser::parse('command:name {--o|option=* : The option description.}');

        expect($results[0])->toBe('command:name')
            ->and($results[2][0]->getShortcut())->toBe('o')
            ->and($results[2][0]->getName())->toBe('option')
            ->and($results[2][0]->getDescription())->toBe('The option description.')
            ->and($results[2][0]->acceptValue())->toBeTrue()
            ->and($results[2][0]->isArray())->toBeTrue();
    });

    test('a shortcut survives a multiline signature', function () {
        $results = Parser::parse('command:name
            {--o|option=* : The option description.}');

        expect($results[0])->toBe('command:name')
            ->and($results[2][0]->getShortcut())->toBe('o')
            ->and($results[2][0]->getName())->toBe('option')
            ->and($results[2][0]->getDescription())->toBe('The option description.')
            ->and($results[2][0]->acceptValue())->toBeTrue()
            ->and($results[2][0]->isArray())->toBeTrue();
    });
});

describe('default values', function () {
    test('scalar defaults are parsed off an argument and an option', function () {
        $results = Parser::parse('command:name {argument=defaultArgumentValue} {--option=defaultOptionValue}');

        expect($results[1][0]->isRequired())->toBeFalse()
            ->and($results[1][0]->getDefault())->toBe('defaultArgumentValue')
            ->and($results[2][0]->acceptValue())->toBeTrue()
            ->and($results[2][0]->getDefault())->toBe('defaultOptionValue');
    });

    test('comma separated array defaults are parsed', function () {
        $results = Parser::parse('command:name {argument=*defaultArgumentValue1,defaultArgumentValue2} {--option=*defaultOptionValue1,defaultOptionValue2}');

        expect($results[1][0]->isArray())->toBeTrue()
            ->and($results[1][0]->isRequired())->toBeFalse()
            ->and($results[1][0]->getDefault())->toEqual(['defaultArgumentValue1', 'defaultArgumentValue2'])
            ->and($results[2][0]->acceptValue())->toBeTrue()
            ->and($results[2][0]->isArray())->toBeTrue()
            ->and($results[2][0]->getDefault())->toEqual(['defaultOptionValue1', 'defaultOptionValue2']);
    });

    test('an argument with a description keeps its default, or null', function () {
        expect(Parser::parse('command:name {argument= : The argument description.}')[1][0]->getDefault())->toBeNull()
            ->and(Parser::parse('command:name {argument=default : The argument description.}')[1][0]->getDefault())->toBe('default');
    });

    test('an option with a description keeps its default, or null', function () {
        expect(Parser::parse('command:name {--option= : The option description.}')[2][0]->getDefault())->toBeNull()
            ->and(Parser::parse('command:name {--option=default : The option description.}')[2][0]->getDefault())->toBe('default');
    });
});

describe('an undeterminable name', function () {
    test('whitespace only', function () {
        Parser::parse(" \t\n\r\x0B\f");
    })->throws(InvalidArgumentException::class, 'Unable to determine command name from signature.');

    test('an empty string', function () {
        Parser::parse('');
    })->throws(InvalidArgumentException::class, 'Unable to determine command name from signature.');
});
