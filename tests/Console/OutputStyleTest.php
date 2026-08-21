<?php

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Voyager\Console\OutputStyle;

/** An output style writing into a fresh buffer. */
function bufferedOutputStyle(): OutputStyle
{
    return new OutputStyle(new ArrayInput([]), new BufferedOutput);
}

test('newLine is detected', function () {
    $style = bufferedOutputStyle();

    expect($style->newLineWritten())->toBeFalse();

    $style->newLine();

    expect($style->newLineWritten())->toBeTrue();
});

test('a newline on the underlying output is detected', function () {
    $underlyingStyle = bufferedOutputStyle();
    $style = new OutputStyle(new ArrayInput([]), $underlyingStyle);

    $underlyingStyle->newLine();

    expect($style->newLineWritten())->toBeTrue();
});

test('write only reports a newline when it appends one', function () {
    $style = bufferedOutputStyle();

    $style->write('Foo');
    expect($style->newLineWritten())->toBeFalse();

    $style->write('Foo', true);
    expect($style->newLineWritten())->toBeTrue();
});

test('writeln always reports a newline', function () {
    $style = bufferedOutputStyle();

    $style->writeln('Foo');

    expect($style->newLineWritten())->toBeTrue();
});

test('a newline is only reported when the verbosity lets the line through', function () {
    $style = bufferedOutputStyle();

    $style->setVerbosity(OutputStyle::VERBOSITY_NORMAL);
    $style->writeln('Foo', OutputStyle::VERBOSITY_VERBOSE);
    expect($style->newLineWritten())->toBeFalse();

    $style->setVerbosity(OutputStyle::VERBOSITY_VERBOSE);
    $style->writeln('Foo', OutputStyle::VERBOSITY_VERBOSE);
    expect($style->newLineWritten())->toBeTrue();
});
