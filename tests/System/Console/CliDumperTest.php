<?php

use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\VarDumper\Caster\ReflectionCaster;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Voyager\System\Console\CliDumper;

/** Dump a value through the CLI dumper and return what it wrote. */
function dumpToString($value): string
{
    $output = new BufferedOutput;
    $dumper = new CliDumper(
        $output,
        '/my-work-directory'
    );

    $cloner = tap(new VarCloner)->addCasters(ReflectionCaster::UNSET_CLOSURE_FILE_INFO);

    $dumper->dumpWithSource($cloner->cloneVar($value));

    return $output->fetch();
}

beforeEach(function () {
    CliDumper::resolveDumpSourceUsing(function () {
        return [
            '/my-work-director/app/routes/console.php',
            'app/routes/console.php',
            18,
        ];
    });
});

afterEach(function () {
    CliDumper::resolveDumpSourceUsing(null);
});

test('a scalar is dumped with its source appended', function ($value, $expected) {
    expect(dumpToString($value))->toBe($expected);
})->with([
    'string' => ['string', "\"string\" // app/routes/console.php:18\n"],
    'integer' => [1, "1 // app/routes/console.php:18\n"],
    'float' => [1.1, "1.1 // app/routes/console.php:18\n"],
    'boolean' => [true, "true // app/routes/console.php:18\n"],
    'null' => [null, "null // app/routes/console.php:18\n"],
]);

test('an array is dumped across lines with its source appended', function () {
    $output = dumpToString(['string', 1, 1.1, ['string', 1, 1.1]]);

    $expected = <<<'EOF'
    array:4 [
      0 => "string"
      1 => 1
      2 => 1.1
      3 => array:3 [
        0 => "string"
        1 => 1
        2 => 1.1
      ]
    ] // app/routes/console.php:18

    EOF;

    expect(str_replace("\r\n", "\n", $output))->toBe(str_replace("\r\n", "\n", $expected));
});

test('an object is dumped with its id and properties', function () {
    $user = new stdClass;
    $user->name = 'Guus';

    $output = dumpToString($user);

    $objectId = spl_object_id($user);

    $expected = <<<EOF
    {#$objectId
      +"name": "Guus"
    } // app/routes/console.php:18

    EOF;

    expect(str_replace("\r\n", "\n", $output))->toBe(str_replace("\r\n", "\n", $expected));
});

test('an unresolvable source appends nothing', function () {
    CliDumper::resolveDumpSourceUsing(fn () => null);

    expect(dumpToString('string'))->toBe("\"string\"\n");
});

test('an unresolvable line appends the file only', function () {
    CliDumper::resolveDumpSourceUsing(function () {
        return [
            '/my-work-directory/resources/views/welcome.blade.php',
            'resources/views/welcome.blade.php',
            null,
        ];
    });

    expect(dumpToString('hey from view'))->toBe("\"hey from view\" // resources/views/welcome.blade.php\n");
});
