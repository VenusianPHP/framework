<?php

use Tests\Filesystem\Fixtures\StringableObjecty;
use Tests\Filesystem\Fixtures\StringableZero;

use function Voyager\Filesystem\join_paths;

test('it can merge paths for unix', function (string $expected, string $given) {
    expect($given)->toBe($expected);
})->skip(fn () => ! in_array(PHP_OS_FAMILY, ['Linux', 'Darwin'], true), 'Requires Linux or Darwin.')
    ->with([
        'merges basic path segments' => ['very/Basic/Functionality.php', join_paths('very', 'Basic', 'Functionality.php')],
        'ltrims directory separators from segments' => ['segments/get/ltrimed/by_directory/separator.php', join_paths('segments', '/get/ltrimed', '/by_directory/separator.php')],
        'preserves backslashes as literal characters on unix' => ['only/\\os_separator\\/\\get_ltrimmed.php', join_paths('only', '\\os_separator\\', '\\get_ltrimmed.php')],
        'does not trim the base path itself' => ['/base_path//does_not/get_trimmed.php', join_paths('/base_path/', '/does_not', '/get_trimmed.php')],
        'drops empty, null, and falsy segments' => ['Empty/0/1/Segments/00/Get_removed.php', join_paths('Empty', '', '0', null, 0, false, [], '1', 'Segments', '00', 'Get_removed.php')],
        'returns an empty string when every segment is empty' => ['', join_paths(null, null, '')],
        'accepts numeric segments' => ['1/2/3', join_paths(1, 0, 2, 3)],
        'accepts a Stringable object as a segment' => ['app/objecty', join_paths('app', new StringableObjecty)],
        'accepts a Stringable object that renders as zero' => ['app/0', join_paths('app', new StringableZero)],
    ]);

test('it can merge paths for windows', function (string $expected, string $given) {
    expect($given)->toBe($expected);
})->skip(fn () => PHP_OS_FAMILY !== 'Windows', 'Requires Windows.')
    ->with([
        'merges basic path segments' => ['app\Basic\Functionality.php', join_paths('app', 'Basic', 'Functionality.php')],
        'ltrims directory separators from segments' => ['segments\get\ltrimed\by_directory\separator.php', join_paths('segments', '\get\ltrimed', '\by_directory\separator.php')],
        'preserves forward slashes as literal characters on windows' => ['only\\/os_separator/\\/get_ltrimmed.php', join_paths('only', '/os_separator/', '/get_ltrimmed.php')],
        'does not trim the base path itself' => ['\base_path\\\\does_not\get_trimmed.php', join_paths('\\base_path\\', '\does_not', '\get_trimmed.php')],
        'drops empty, null, and falsy segments' => ['Empty\0\1\Segments\00\Get_removed.php', join_paths('Empty', '', '0', null, 0, false, [], '1', 'Segments', '00', 'Get_removed.php')],
        'returns an empty string when every segment is empty' => ['', join_paths(null, null, '')],
        'accepts numeric segments' => ['1\2\3', join_paths(1, 2, 3)],
        'accepts a Stringable object as a segment' => ['app\\objecty', join_paths('app', new StringableObjecty)],
        'accepts a Stringable object that renders as zero' => ['app\\0', join_paths('app', new StringableZero)],
    ]);
