<?php

use Voyager\IOPools\ProcessPoolFrame;

it('writes a bootstrap failure to stderr, not the protocol pipe', function () {
    $root = sys_get_temp_dir().'/vf-pw-'.getmypid();
    mkdir($root, 0777, true);

    $cmd = [
        PHP_BINARY,
        dirname(__DIR__, 2).'/src/Voyager/IOPools/bin/pool-worker',
        dirname(__DIR__, 2).'/vendor/autoload.php',
        $root,
    ];

    $proc = proc_open($cmd, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
    fclose($pipes[0]);

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    $exitCode = proc_close($proc);

    expect($stderr)->toContain('bootstrap/cache')
        ->and($stdout)->not->toContain('bootstrap/cache')
        ->and($exitCode)->toBe(1);

    @rmdir($root);
});

it('says hello and nothing else on stdout once it boots', function () {
    $root = dirname(__DIR__, 2);

    $proc = proc_open(
        [PHP_BINARY, $root.'/src/Voyager/IOPools/bin/pool-worker', $root.'/vendor/autoload.php', $root],
        [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
        $pipes,
    );
    fclose($pipes[0]);                                  // EOF on stdin: the worker boots, says hello, leaves

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    $exitCode = proc_close($proc);

    // a warning printed before the first frame lands here and fails take()
    $hello = ProcessPoolFrame::take($stdout);

    expect($hello)->toHaveKey('hello')
        ->and($stdout)->toBe('')
        ->and($stderr)->toBe('')
        ->and($exitCode)->toBe(0);
});
