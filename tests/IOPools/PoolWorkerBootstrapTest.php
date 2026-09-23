<?php

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
