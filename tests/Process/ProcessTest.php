<?php

use Voyager\Contracts\Process\ProcessResult;
use Voyager\Process\Exceptions\ProcessFailedException;
use Voyager\Process\Exceptions\ProcessTimedOutException;
use Voyager\Process\Factory;

/**
 * The real "ls" (or "dir" on Windows) invocation used to exercise actual
 * process execution against this test's own directory.
 */
function lsCommand(): string
{
    return windows_os() ? 'dir' : 'ls';
}

test('a successful process returns a successful result', function () {
    $factory = new Factory;
    $result = $factory->path(__DIR__)->run(lsCommand());

    expect($result)->toBeInstanceOf(ProcessResult::class);
    expect($result->successful())->toBeTrue();
    expect($result->failed())->toBeFalse();
    expect($result->exitCode())->toEqual(0);
    expect(str_contains($result->output(), 'ProcessTest.php'))->toBeTrue();
    expect($result->errorOutput())->toEqual('');

    $result->throw();
    $result->throwIf(true);
});

describe('process pools', function () {
    test('a pool of processes can run concurrently', function () {
        $factory = new Factory;

        $pool = $factory->pool(function ($pool) {
            return [
                $pool->path(__DIR__)->command(lsCommand()),
                $pool->path(__DIR__)->command(lsCommand()),
            ];
        });

        $results = $pool->start()->wait();

        expect($results[0]->successful())->toBeTrue();
        expect($results[1]->successful())->toBeTrue();

        expect(str_contains($results[0]->output(), 'ProcessTest.php'))->toBeTrue();
        expect(str_contains($results[1]->output(), 'ProcessTest.php'))->toBeTrue();

        expect($results->successful())->toBeTrue();
    });

    test('a pool reports overall failure when any process fails', function () {
        $factory = new Factory;

        $factory->fake([
            'cat *' => $factory->result(exitCode: 1),
        ]);

        $pool = $factory->pool(function ($pool) {
            return [
                $pool->path(__DIR__)->command(lsCommand()),
                $pool->path(__DIR__)->command('cat test'),
            ];
        });

        $results = $pool->start()->wait();

        expect($results[0]->successful())->toBeTrue();
        expect($results[1]->failed())->toBeTrue();

        expect($results->failed())->toBeTrue();
    });

    test('a started pool can be counted', function () {
        $factory = new Factory;

        $pool = $factory->pool(function ($pool) {
            return [
                $pool->path(__DIR__)->command(lsCommand()),
                $pool->path(__DIR__)->command(lsCommand()),
            ];
        })->start();

        expect($pool)->toHaveCount(2);
    });

    test('a pool can receive output for each process via the start method', function () {
        $factory = new Factory;

        $output = [];

        $pool = $factory->pool(function ($pool) {
            return [
                $pool->path(__DIR__)->command(lsCommand()),
                $pool->path(__DIR__)->command(lsCommand()),
            ];
        })->start(function ($type, $buffer, $key) use (&$output) {
            $output[$key][$type][] = $buffer;
        });

        $poolResults = $pool->wait();

        expect(count($output[0]['out']) > 0)->toBeTrue();
        expect(count($output[1]['out']) > 0)->toBeTrue();
        expect($poolResults[0])->toBeInstanceOf(ProcessResult::class);
        expect($poolResults[1])->toBeInstanceOf(ProcessResult::class);
        expect(str_contains($poolResults[0]->output(), 'ProcessTest.php'))->toBeTrue();
        expect(str_contains($poolResults[1]->output(), 'ProcessTest.php'))->toBeTrue();
    });

    test('pool results can be evaluated by name', function () {
        $factory = new Factory;

        $pool = $factory->pool(function ($pool) {
            return [
                $pool->as('first')->path(__DIR__)->command(lsCommand()),
                $pool->as('second')->path(__DIR__)->command(lsCommand()),
            ];
        })->wait();

        expect($pool['first']->successful())->toBeTrue();
        expect($pool['second']->successful())->toBeTrue();

        expect(str_contains($pool['first']->output(), 'ProcessTest.php'))->toBeTrue();
        expect(str_contains($pool['second']->output(), 'ProcessTest.php'))->toBeTrue();
    });
});

describe('output callbacks', function () {
    test('output can be retrieved via the start callback', function () {
        $factory = new Factory;

        $output = [];

        $process = $factory->path(__DIR__)->start(lsCommand(), function ($type, $buffer) use (&$output) {
            $output[] = $buffer;
        });

        $process->wait();

        expect(str_contains(implode('', $output), 'ProcessTest.php'))->toBeTrue();
    });

    test('output can be retrieved via the wait callback', function () {
        $factory = new Factory;

        $output = [];

        $process = $factory->path(__DIR__)->start(lsCommand());

        $process->wait(function ($type, $buffer) use (&$output) {
            $output[] = $buffer;
        });

        expect(str_contains(implode('', $output), 'ProcessTest.php'))->toBeTrue();
    });
});

describe('process faking', function () {
    test('a basic process fake returns an empty successful result', function () {
        $factory = new Factory;
        $factory->fake();

        $result = $factory->run('ls -la');

        expect($result->output())->toEqual('');
        expect($result->errorOutput())->toEqual('');
        expect($result->exitCode())->toEqual(0);
        expect($result->successful())->toBeTrue();
    });

    test('a basic process fake matches a multi-line command', function () {
        $factory = new Factory;

        $factory->preventStrayProcesses();

        $factory->fake([
            '*' => $expectedOutput = 'The output',
        ]);

        $result = $factory->run(<<<'COMMAND'
        git clone --depth 1 \
              --single-branch \
              --branch main \
              git://some-url .
        COMMAND);

        expect($result->exitCode())->toBe(0);
        expect($result->output())->toBe("$expectedOutput\n");
    });

    test('a process fake with an overlapping pattern matches the most specific fake', function () {
        $factory = new Factory;

        $factory->preventStrayProcesses();

        $factory->fake([
            '*--branch main*' => 'not this one',
            '*--branch develop*' => $expectedOutput = 'yes thank you',
        ]);

        $result = $factory->run(<<<'COMMAND'
        git clone --depth 1 \
              --single-branch \
              --branch develop \
              git://some-url .
        COMMAND);

        expect($result->exitCode())->toBe(0);
        expect($result->output())->toBe("$expectedOutput\n");
    });

    test('a process fake can set the exit code via a factory result', function () {
        $factory = new Factory;
        $factory->fake(fn () => $factory->result('test output', exitCode: 1));

        $result = $factory->run('ls -la');
        expect($result->successful())->toBeFalse();
    });

    test('a process fake exit code shorthand sets the exit code', function () {
        $factory = new Factory;
        $factory->fake(['ls -la' => 1]);

        $result = $factory->run('ls -la');
        expect($result->exitCode())->toBe(1);
        expect($result->successful())->toBeFalse();
    });

    test('a basic process fake accepts custom output in several shapes', function () {
        $factory = new Factory;
        $factory->fake(fn () => $factory->result('test output'));

        $result = $factory->run('ls -la');
        expect($result->output())->toEqual("test output\n");

        // Array of output...
        $factory = new Factory;
        $factory->fake(fn () => $factory->result(['line 1', 'line 2']));

        $result = $factory->run('ls -la');
        expect($result->output())->toEqual("line 1\nline 2\n");

        // Array of output with empty line...
        $factory = new Factory;
        $factory->fake(fn () => $factory->result(['line 1', '', 'line 2']));

        $result = $factory->run('ls -la');
        expect($result->output())->toEqual("line 1\n\nline 2\n");

        // Plain string...
        $factory = new Factory;
        $factory->fake(fn () => 'test output');

        $result = $factory->run('ls -la');
        expect($result->output())->toEqual("test output\n");

        // Plain array...
        $factory = new Factory;
        $factory->fake(fn () => ['line 1', 'line 2']);

        $result = $factory->run('ls -la');
        expect($result->output())->toEqual("line 1\nline 2\n");

        // Plain array with empty line...
        $factory = new Factory;
        $factory->fake(fn () => ['line 1', '', 'line 2']);

        $result = $factory->run('ls -la');
        expect($result->output())->toEqual("line 1\n\nline 2\n");

        // Process description...
        $factory = new Factory;
        $factory->fake(fn () => $factory->describe()->output('line 1')->output('line 2'));

        $result = $factory->run('ls -la');
        expect($result->output())->toEqual("line 1\nline 2\n");

        // Process description with empty line...
        $factory = new Factory;
        $factory->fake(fn () => $factory->describe()->output('line 1')->output('')->output('line 2'));

        $result = $factory->run('ls -la');
        expect($result->output())->toEqual("line 1\n\nline 2\n");
    });

    test('a process fake can set error output in several shapes', function () {
        $factory = new Factory;
        $factory->fake(fn () => $factory->result('standard output', 'error output'));

        $result = $factory->run('ls -la');
        expect($result->output())->toEqual("standard output\n");
        expect($result->errorOutput())->toEqual("error output\n");

        // Array of error output...
        $factory = new Factory;
        $factory->fake(fn () => $factory->result('standard output', ['line 1', 'line 2']));

        $result = $factory->run('ls -la');
        expect($result->output())->toEqual("standard output\n");
        expect($result->errorOutput())->toEqual("line 1\nline 2\n");

        // Using process description...
        $factory = new Factory;
        $factory->fake(fn () => $factory->describe()->output('standard output')->errorOutput('error output'));

        $result = $factory->run('ls -la');
        expect($result->output())->toEqual("standard output\n");
        expect($result->errorOutput())->toEqual("error output\n");
    });

    test('fakes can be customized per command', function () {
        $factory = new Factory;

        $factory->fake([
            'ls *' => 'ls command',
            'cat *' => 'cat command',
        ]);

        $result = $factory->run('ls -la');
        expect($result->output())->toEqual("ls command\n");

        $result = $factory->run('cat composer.json');
        expect($result->output())->toEqual("cat command\n");
    });

    test('process fake sequences are consumed in order', function () {
        $factory = new Factory;

        $factory->fake([
            'ls *' => $factory->sequence()
                ->push('ls command 1')
                ->push('ls command 2'),
            'cat *' => 'cat command',
        ]);

        $result = $factory->run('ls -la');
        expect($result->output())->toEqual("ls command 1\n");

        $result = $factory->run('ls -la');
        expect($result->output())->toEqual("ls command 2\n");

        $result = $factory->run('cat composer.json');
        expect($result->output())->toEqual("cat command\n");
    });

    test('process fake sequences can return empty results when the sequence is empty', function () {
        $factory = new Factory;

        $factory->fake([
            'ls *' => $factory->sequence()
                ->push('ls command 1')
                ->push('ls command 2')
                ->dontFailWhenEmpty(),
        ]);

        $result = $factory->run('ls -la');
        expect($result->output())->toEqual("ls command 1\n");

        $result = $factory->run('ls -la');
        expect($result->output())->toEqual("ls command 2\n");

        $result = $factory->run('ls -la');
        expect($result->output())->toEqual('');
    });

    test('process fake sequences can throw when the sequence is empty', function () {
        $factory = new Factory;

        $factory->fake([
            'ls *' => $factory->sequence()
                ->push('ls command 1')
                ->push('ls command 2'),
        ]);

        $result = $factory->run('ls -la');
        expect($result->output())->toEqual("ls command 1\n");

        $result = $factory->run('ls -la');
        expect($result->output())->toEqual("ls command 2\n");

        $result = $factory->run('ls -la');
    })->throws(OutOfBoundsException::class);
});

describe('preventing stray processes', function () {
    test('stray processes can be prevented with a string command', function () {
        $factory = new Factory;

        $factory->preventStrayProcesses();

        $factory->fake([
            'ls *' => 'ls command',
        ]);

        $result = $factory->run('cat composer.json');
    })->throws(RuntimeException::class, '] without a matching fake.');

    test('stray processes can be prevented with an array command', function () {
        $factory = new Factory;

        $factory->preventStrayProcesses();

        $factory->fake([
            'ls *' => 'ls command',
        ]);

        $result = $factory->run(['cat composer.json']);
    })->throws(RuntimeException::class, '] without a matching fake.');

    test('stray processes actually run by default', function () {
        $factory = new Factory;

        $factory->fake([
            'cat *' => 'cat command',
        ]);

        $result = $factory->path(__DIR__)->run(lsCommand());
        expect(str_contains($result->output(), 'ProcessTest.php'))->toBeTrue();
    });
});

describe('fake process exceptions', function () {
    test('the process fake throw shorthand throws the given exception', function () {
        $factory = new Factory;

        $factory->fake(['cat me' => new RuntimeException('fake exception message')]);

        $factory->run('cat me');
    })->throws(RuntimeException::class, 'fake exception message');

    test('fake processes can throw', function () {
        $factory = new Factory;

        $factory->fake(fn () => $factory->result(exitCode: 1));

        $result = $factory->path(__DIR__)->run(lsCommand());
        $result->throw();
    })->throws(ProcessFailedException::class);

    test('fake processes throw if true', function () {
        $factory = new Factory;

        $factory->fake(fn () => $factory->result(exitCode: 1));

        $result = $factory->path(__DIR__)->run(lsCommand());
        $result->throwIf(true);
    })->throws(ProcessFailedException::class);

    test('fake processes dont throw if false', function () {
        $factory = new Factory;

        $factory->fake(fn () => $factory->result(exitCode: 1));

        $result = $factory->path(__DIR__)->run(lsCommand());
        $result->throwIf(false);

        expect(true)->toBeTrue();
    });
});

describe('process failure output', function () {
    test('real processes can have error output', function () {
        $factory = new Factory;
        $result = $factory->path(__DIR__)->run('echo "Hello World" >&2; exit 1;');

        expect($result->successful())->toBeFalse();
        expect($result->output())->toEqual('');
        expect($result->errorOutput())->toEqual("Hello World\n");
    })->skip(preg_match('/Linux|DAR/i', PHP_OS) === 0, 'Operating system Linux|DAR is required.');

    test('fake processes can throw without output', function () {
        $factory = new Factory;
        $factory->fake(fn () => $factory->result(exitCode: 1));
        $result = $factory->path(__DIR__)->run('exit 1;');

        $result->throw();
    })->throws(ProcessFailedException::class, <<<'EOT'
        The command "exit 1;" failed.

        Exit Code: 1
        EOT
    );

    test('real processes can throw without output', function () {
        $factory = new Factory;
        $result = $factory->path(__DIR__)->run('exit 1;');

        $result->throw();
    })->skip(preg_match('/Linux|DAR/i', PHP_OS) === 0, 'Operating system Linux|DAR is required.')
        ->throws(ProcessFailedException::class, <<<'EOT'
        The command "exit 1;" failed.

        Exit Code: 1
        EOT
        );

    test('fake processes can throw with error output', function () {
        $factory = new Factory;
        $factory->fake(fn () => $factory->result(errorOutput: 'Hello World', exitCode: 1));
        $result = $factory->path(__DIR__)->run('echo "Hello World" >&2; exit 1;');

        $result->throw();
    })->throws(ProcessFailedException::class, <<<'EOT'
        The command "echo "Hello World" >&2; exit 1;" failed.

        Exit Code: 1

        Error Output:
        ================
        Hello World
        EOT
    );

    test('real processes can throw with error output', function () {
        $factory = new Factory;
        $result = $factory->path(__DIR__)->run('echo "Hello World" >&2; exit 1;');

        $result->throw();
    })->skip(preg_match('/Linux|DAR/i', PHP_OS) === 0, 'Operating system Linux|DAR is required.')
        ->throws(ProcessFailedException::class, <<<'EOT'
        The command "echo "Hello World" >&2; exit 1;" failed.

        Exit Code: 1

        Error Output:
        ================
        Hello World
        EOT
        );

    test('fake processes can throw with output', function () {
        $factory = new Factory;
        $factory->fake(fn () => $factory->result(output: 'Hello World', exitCode: 1));
        $result = $factory->path(__DIR__)->run('echo "Hello World" >&1; exit 1;');

        $result->throw();
    })->throws(ProcessFailedException::class, <<<'EOT'
        The command "echo "Hello World" >&1; exit 1;" failed.

        Exit Code: 1

        Output:
        ================
        Hello World
        EOT
    );

    test('real processes can throw with output', function () {
        $factory = new Factory;
        $result = $factory->path(__DIR__)->run('echo "Hello World" >&1; exit 1;');

        $result->throw();
    })->skip(preg_match('/Linux|DAR/i', PHP_OS) === 0, 'Operating system Linux|DAR is required.')
        ->throws(ProcessFailedException::class, <<<'EOT'
        The command "echo "Hello World" >&1; exit 1;" failed.

        Exit Code: 1

        Output:
        ================
        Hello World
        EOT
        );
});

describe('timeouts and conditional throwing', function () {
    test('real processes can timeout', function () {
        $factory = new Factory;
        $result = $factory->timeout(1)->path(__DIR__)->run('sleep 2; exit 1;');

        $result->throw();
    })->skip(preg_match('/Linux|DAR/i', PHP_OS) === 0, 'Operating system Linux|DAR is required.')
        ->throws(
            ProcessTimedOutException::class,
            'The process "sleep 2; exit 1;" exceeded the timeout of 1 seconds.'
        );

    test('real processes can throw if true', function () {
        $factory = new Factory;
        $result = $factory->path(__DIR__)->run('echo "Hello World" >&2; exit 1;');

        $result->throwIf(true);
    })->skip(preg_match('/Linux|DAR/i', PHP_OS) === 0, 'Operating system Linux|DAR is required.')
        ->throws(ProcessFailedException::class);

    test('real processes dont throw if false', function () {
        $factory = new Factory;
        $result = $factory->path(__DIR__)->run('echo "Hello World" >&2; exit 1;');

        $result->throwIf(false);

        expect(true)->toBeTrue();
    })->skip(preg_match('/Linux|DAR/i', PHP_OS) === 0, 'Operating system Linux|DAR is required.');

    test('real processes can use standard input', function () {
        $factory = new Factory;
        $result = $factory->input('foobar')->run('cat');

        expect($result->output())->toBe('foobar');
    })->skip(preg_match('/Linux|DAR/i', PHP_OS) === 0, 'Operating system Linux|DAR is required.');
});

describe('process pipes', function () {
    test('a process pipe pipes output between commands', function () {
        $factory = new Factory;
        $factory->fake([
            'cat *' => "Hello, world\nfoo\nbar",
        ]);

        $pipe = $factory->pipe(function ($pipe) {
            $pipe->command('cat test');
            $pipe->command('grep -i "foo"');
        });

        expect($pipe->output())->toBe("foo\n");
    })->skip(preg_match('/Linux|DAR/i', PHP_OS) === 0, 'Operating system Linux|DAR is required.');

    test('a process pipe reports failure', function () {
        $factory = new Factory;
        $factory->fake([
            'cat *' => $factory->result(exitCode: 1),
        ]);

        $pipe = $factory->pipe(function ($pipe) {
            $pipe->command('cat test');
            $pipe->command('grep -i "foo"');
        });

        expect($pipe->failed())->toBeTrue();
    })->skip(preg_match('/Linux|DAR/i', PHP_OS) === 0, 'Operating system Linux|DAR is required.');

    test('a simple process pipe pipes output between commands', function () {
        $factory = new Factory;
        $factory->fake([
            'cat *' => "Hello, world\nfoo\nbar",
        ]);

        $pipe = $factory->pipe([
            'cat test',
            'grep -i "foo"',
        ]);

        expect($pipe->output())->toBe("foo\n");
    })->skip(preg_match('/Linux|DAR/i', PHP_OS) === 0, 'Operating system Linux|DAR is required.');

    test('a simple process pipe reports failure', function () {
        $factory = new Factory;
        $factory->fake([
            'cat *' => $factory->result(exitCode: 1),
        ]);

        $pipe = $factory->pipe([
            'cat test',
            'grep -i "foo"',
        ]);

        expect($pipe->failed())->toBeTrue();
    })->skip(preg_match('/Linux|DAR/i', PHP_OS) === 0, 'Operating system Linux|DAR is required.');
});

describe('invoked process streaming', function () {
    test('a fake invoked process reports latest output alongside accumulated output', function () {
        $factory = new Factory;

        $factory->fake(function () use ($factory) {
            return $factory->describe()
                ->output('ONE')
                ->output('TWO')
                ->output('THREE')
                ->runsFor(iterations: 3);
        });

        $process = $factory->start('echo "ONE"; sleep 1; echo "TWO"; sleep 1; echo "THREE"; sleep 1;');

        $latestOutput = [];
        $output = [];

        while ($process->running()) {
            $latestOutput[] = $process->latestOutput();
            $output[] = $process->output();
        }

        expect($latestOutput[0])->toEqual("ONE\n");
        expect($output[0])->toEqual("ONE\nTWO\n");

        expect($latestOutput[1])->toEqual("THREE\n");
        expect($output[1])->toEqual("ONE\nTWO\nTHREE\n");

        expect($latestOutput[2])->toEqual('');
        expect($output[2])->toEqual("ONE\nTWO\nTHREE\n");
    });

    test('a fake invoked process can wait until a condition is met', function () {
        $factory = new Factory;

        $factory->fake(function () use ($factory) {
            return $factory->describe()
                ->output('WAITING')
                ->output('READY')
                ->output('DONE')
                ->runsFor(iterations: 3);
        });

        $process = $factory->start('echo "WAITING"; sleep 1; echo "READY"; sleep 1; echo "DONE";');

        $callbackInvoked = [];

        $result = $process->waitUntil(function ($type, $buffer) use (&$callbackInvoked) {
            $callbackInvoked[] = $buffer;

            return str_contains($buffer, 'READY');
        });

        expect($result)->toBeInstanceOf(ProcessResult::class);
        expect($result->successful())->toBeTrue();
        expect($callbackInvoked)->toContain("WAITING\n");
        expect($callbackInvoked)->toContain("READY\n");
    });

    test('waitUntil with no callback simply waits for completion', function () {
        $factory = new Factory;

        $factory->fake(function () use ($factory) {
            return $factory->describe()
                ->output('OUTPUT');
        });

        $process = $factory->start('echo "OUTPUT"');

        $result = $process->waitUntil();

        expect($result)->toBeInstanceOf(ProcessResult::class);
        expect($result->successful())->toBeTrue();
        expect($result->output())->toEqual("OUTPUT\n");
    });

    test('waitUntil sees both standard and error output', function () {
        $factory = new Factory;

        $factory->fake(function () use ($factory) {
            return $factory->describe()
                ->output('STDOUT')
                ->errorOutput('ERROR1')
                ->errorOutput('TARGET_ERROR')
                ->output('MORE_STDOUT')
                ->runsFor(iterations: 4);
        });

        $process = $factory->start('echo "STDOUT"; echo "ERROR1" >&2; echo "TARGET_ERROR" >&2; echo "MORE_STDOUT";');

        $callbackInvoked = [];

        $result = $process->waitUntil(function ($type, $buffer) use (&$callbackInvoked) {
            $callbackInvoked[] = [$type, $buffer];

            return str_contains($buffer, 'TARGET_ERROR');
        });

        expect($result)->toBeInstanceOf(ProcessResult::class);
        expect($result->successful())->toBeTrue();
        expect($callbackInvoked)->toContain(['out', "STDOUT\n"]);
        expect($callbackInvoked)->toContain(['err', "ERROR1\n"]);
        expect($callbackInvoked)->toContain(['err', "TARGET_ERROR\n"]);
    });

    test('waitUntil can be called twice, resuming from where it left off', function () {
        $factory = new Factory;

        $factory->fake(function () use ($factory) {
            return $factory->describe()
                ->output('FIRST')
                ->output('SECOND')
                ->output('THIRD')
                ->output('FOURTH')
                ->runsFor(iterations: 4);
        });

        $process = $factory->start('echo "FIRST"; echo "SECOND"; echo "THIRD"; echo "FOURTH";');

        $firstCallbackInvoked = [];
        $secondCallbackInvoked = [];

        $firstResult = $process->waitUntil(function ($type, $buffer) use (&$firstCallbackInvoked) {
            $firstCallbackInvoked[] = $buffer;

            return str_contains($buffer, 'SECOND');
        });

        expect($firstResult)->toBeInstanceOf(ProcessResult::class);
        expect($firstResult->successful())->toBeTrue();
        expect($firstCallbackInvoked)->toContain("FIRST\n");
        expect($firstCallbackInvoked)->toContain("SECOND\n");
        expect($firstCallbackInvoked)->toHaveCount(2);

        $secondResult = $process->waitUntil(function ($type, $buffer) use (&$secondCallbackInvoked) {
            $secondCallbackInvoked[] = $buffer;

            return str_contains($buffer, 'FOURTH');
        });

        expect($secondResult)->toBeInstanceOf(ProcessResult::class);
        expect($secondResult->successful())->toBeTrue();
        expect($secondCallbackInvoked)->toContain("THIRD\n");
        expect($secondCallbackInvoked)->toContain("FOURTH\n");
        expect($secondCallbackInvoked)->toHaveCount(2);
    });

    test('waitUntil that never matches drains all output', function () {
        $factory = new Factory;

        $factory->fake(function () use ($factory) {
            return $factory->describe()
                ->output('LINE1')
                ->output('LINE2')
                ->output('LINE3')
                ->runsFor(iterations: 3);
        });

        $process = $factory->start('echo "LINE1"; echo "LINE2"; echo "LINE3";');

        $callbackInvoked = [];

        $result = $process->waitUntil(function ($type, $buffer) use (&$callbackInvoked) {
            $callbackInvoked[] = $buffer;

            return str_contains($buffer, 'NEVER_MATCHES');
        });

        expect($result)->toBeInstanceOf(ProcessResult::class);
        expect($result->successful())->toBeTrue();
        expect($callbackInvoked)->toHaveCount(3);
        expect($callbackInvoked)->toContain("LINE1\n");
        expect($callbackInvoked)->toContain("LINE2\n");
        expect($callbackInvoked)->toContain("LINE3\n");
    });

    test('waitUntil followed by wait only sees the remaining output', function () {
        $factory = new Factory;

        $factory->fake(function () use ($factory) {
            return $factory->describe()
                ->output('FIRST')
                ->output('SECOND')
                ->output('THIRD')
                ->runsFor(iterations: 3);
        });

        $process = $factory->start('echo "FIRST"; echo "SECOND"; echo "THIRD";');

        $waitUntilCallbacks = [];
        $waitCallbacks = [];

        $process->waitUntil(function ($type, $buffer) use (&$waitUntilCallbacks) {
            $waitUntilCallbacks[] = $buffer;

            return str_contains($buffer, 'FIRST');
        });

        $result = $process->wait(function ($type, $buffer) use (&$waitCallbacks) {
            $waitCallbacks[] = $buffer;
        });

        expect($result)->toBeInstanceOf(ProcessResult::class);
        expect($result->successful())->toBeTrue();
        expect($waitUntilCallbacks)->toHaveCount(1);
        expect($waitUntilCallbacks[0])->toEqual("FIRST\n");
        expect($waitCallbacks)->toHaveCount(2);
        expect($waitCallbacks)->toContain("SECOND\n");
        expect($waitCallbacks)->toContain("THIRD\n");
    });

    test('wait can be called twice, and the second call sees nothing new', function () {
        $factory = new Factory;

        $factory->fake(function () use ($factory) {
            return $factory->describe()
                ->output('FIRST')
                ->output('SECOND')
                ->output('THIRD')
                ->runsFor(iterations: 3);
        });

        $process = $factory->start('echo "FIRST"; echo "SECOND"; echo "THIRD";');

        $firstCallbackInvoked = [];
        $secondCallbackInvoked = [];

        $firstResult = $process->wait(function ($type, $buffer) use (&$firstCallbackInvoked) {
            $firstCallbackInvoked[] = $buffer;
        });

        expect($firstResult)->toBeInstanceOf(ProcessResult::class);
        expect($firstResult->successful())->toBeTrue();
        expect($firstCallbackInvoked)->toHaveCount(3);
        expect($firstCallbackInvoked)->toContain("FIRST\n");
        expect($firstCallbackInvoked)->toContain("SECOND\n");
        expect($firstCallbackInvoked)->toContain("THIRD\n");

        $secondResult = $process->wait(function ($type, $buffer) use (&$secondCallbackInvoked) {
            $secondCallbackInvoked[] = $buffer;
        });

        expect($secondResult)->toBeInstanceOf(ProcessResult::class);
        expect($secondResult->successful())->toBeTrue();
        expect($secondCallbackInvoked)->toBeEmpty();
    });

    test('wait followed by waitUntil only sees the remaining output', function () {
        $factory = new Factory;

        $factory->fake(function () use ($factory) {
            return $factory->describe()
                ->output('FIRST')
                ->output('SECOND')
                ->output('THIRD')
                ->runsFor(iterations: 3);
        });

        $process = $factory->start('echo "FIRST"; echo "SECOND"; echo "THIRD";');

        $waitCallbacks = [];
        $waitUntilCallbacks = [];

        $process->wait(function ($type, $buffer) use (&$waitCallbacks) {
            $waitCallbacks[] = $buffer;
        });

        $result = $process->waitUntil(function ($type, $buffer) use (&$waitUntilCallbacks) {
            $waitUntilCallbacks[] = $buffer;

            return str_contains($buffer, 'THIRD');
        });

        expect($result)->toBeInstanceOf(ProcessResult::class);
        expect($result->successful())->toBeTrue();
        expect($waitCallbacks)->toHaveCount(3);
        expect($waitUntilCallbacks)->toBeEmpty();
    });
});

describe('fake assertions', function () {
    test('basic fake assertions', function () {
        $factory = new Factory;

        $factory->fake();

        $result = $factory->run('ls -la');

        $factory->assertRan(function ($process, $result) {
            return $process->command == 'ls -la';
        });

        $factory->assertRanTimes(function ($process, $result) {
            return $process->command == 'ls -la';
        }, 1);

        $factory->assertNotRan(function ($process, $result) {
            return $process->command == 'cat foo';
        });
    });

    test('asserting that nothing ran', function () {
        $factory = new Factory;

        $factory->fake();

        $factory->assertNothingRan();
    });

    test('a process can run with multiple environment variables across a fake sequence', function () {
        $factory = new Factory;

        $factory->fake([
            'printenv TEST_VAR OTHER_VAR' => $factory->sequence()
                ->push("test_value\nother_value")
                ->push("new_test_value\nnew_other_value"),
        ]);

        $result = $factory->env([
            'TEST_VAR' => 'test_value',
            'OTHER_VAR' => 'other_value',
        ])->run('printenv TEST_VAR OTHER_VAR');

        expect($result->successful())->toBeTrue();
        expect($result->output())->toEqual("test_value\nother_value\n");

        $result = $factory->env([
            'TEST_VAR' => 'new_test_value',
            'OTHER_VAR' => 'new_other_value',
        ])->run('printenv TEST_VAR OTHER_VAR');

        expect($result->successful())->toBeTrue();
        expect($result->output())->toEqual("new_test_value\nnew_other_value\n");

        $factory->assertRanTimes(function ($process) {
            return str_contains($process->command, 'printenv TEST_VAR OTHER_VAR');
        }, 2);
    });
});
