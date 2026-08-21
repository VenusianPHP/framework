<?php

namespace Tests\Queue;

use Voyager\Queue\Listener;
use Voyager\Queue\ListenerOptions;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use Composer\InstalledVersions;
use Symfony\Component\Process\Process;

use function Voyager\NutsAndBolts\computer_binary;
use function Voyager\NutsAndBolts\php_binary;

class QueueListenerTest extends TestCase
{
    public function testRunProcessCallsProcess()
    {
        $process = m::mock(Process::class)->makePartial();
        $process->shouldReceive('run')->once();
        $listener = m::mock(Listener::class)->makePartial();
        $listener->shouldReceive('memoryExceeded')->once()->with(1)->andReturn(false);

        $listener->runProcess($process, 1);
    }

    public function testListenerStopsWhenMemoryIsExceeded()
    {
        $process = m::mock(Process::class)->makePartial();
        $process->shouldReceive('run')->once();
        $listener = m::mock(Listener::class)->makePartial();
        $listener->shouldReceive('memoryExceeded')->once()->with(1)->andReturn(true);
        $listener->shouldReceive('stop')->once();

        $listener->runProcess($process, 1);
    }

    public function testMakeProcessCorrectlyFormatsCommandLine()
    {
        $listener = new Listener(__DIR__);
        $options = new ListenerOptions;
        $options->backoff = 1;
        $options->memory = 2;
        $options->timeout = 3;
        $process = $listener->makeProcess('connection', 'queue', $options);
        $escape = $escapeMsys = '\\' === DIRECTORY_SEPARATOR ? '' : '\'';

        if (static::symfonyProcessAtLeast('7.4.5') && windows_os()) {
            $escapeMsys = '"';
        }

        $computerBinary = computer_binary();

        $this->assertInstanceOf(Process::class, $process);
        $this->assertEquals(__DIR__, $process->getWorkingDirectory());
        $this->assertEquals(3, $process->getTimeout());
        $this->assertEquals($escape.php_binary().$escape." {$escape}{$computerBinary}{$escape} {$escape}queue:work{$escape} {$escape}connection{$escape} {$escape}--once{$escape} {$escapeMsys}--name=default{$escapeMsys} {$escapeMsys}--queue=queue{$escapeMsys} {$escapeMsys}--backoff=1{$escapeMsys} {$escapeMsys}--memory=2{$escapeMsys} {$escapeMsys}--sleep=3{$escapeMsys} {$escapeMsys}--tries=1{$escapeMsys}", $process->getCommandLine());
    }

    public function testMakeProcessCorrectlyFormatsCommandLineWithAnEnvironmentSpecified()
    {
        $listener = new Listener(__DIR__);
        $options = new ListenerOptions('default', 'test');
        $options->backoff = 1;
        $options->memory = 2;
        $options->timeout = 3;
        $process = $listener->makeProcess('connection', 'queue', $options);
        $escape = $escapeMsys = '\\' === DIRECTORY_SEPARATOR ? '' : '\'';

        if (static::symfonyProcessAtLeast('7.4.5') && windows_os()) {
            $escapeMsys = '"';
        }

        $computerBinary = computer_binary();

        $this->assertInstanceOf(Process::class, $process);
        $this->assertEquals(__DIR__, $process->getWorkingDirectory());
        $this->assertEquals(3, $process->getTimeout());
        $this->assertEquals($escape.php_binary().$escape." {$escape}{$computerBinary}{$escape} {$escape}queue:work{$escape} {$escape}connection{$escape} {$escape}--once{$escape} {$escapeMsys}--name=default{$escapeMsys} {$escapeMsys}--queue=queue{$escapeMsys} {$escapeMsys}--backoff=1{$escapeMsys} {$escapeMsys}--memory=2{$escapeMsys} {$escapeMsys}--sleep=3{$escapeMsys} {$escapeMsys}--tries=1{$escapeMsys} {$escapeMsys}--env=test{$escapeMsys}", $process->getCommandLine());
    }

    public function testMakeProcessCorrectlyFormatsCommandLineWhenTheConnectionIsNotSpecified()
    {
        $listener = new Listener(__DIR__);
        $options = new ListenerOptions('default', 'test');
        $options->backoff = 1;
        $options->memory = 2;
        $options->timeout = 3;
        $process = $listener->makeProcess(null, 'queue', $options);
        $escape = $escapeMsys = '\\' === DIRECTORY_SEPARATOR ? '' : '\'';

        if (static::symfonyProcessAtLeast('7.4.5') && windows_os()) {
            $escapeMsys = '"';
        }

        $computerBinary = computer_binary();

        $this->assertInstanceOf(Process::class, $process);
        $this->assertEquals(__DIR__, $process->getWorkingDirectory());
        $this->assertEquals(3, $process->getTimeout());
        $this->assertEquals($escape.php_binary().$escape." {$escape}{$computerBinary}{$escape} {$escape}queue:work{$escape} {$escape}--once{$escape} {$escapeMsys}--name=default{$escapeMsys} {$escapeMsys}--queue=queue{$escapeMsys} {$escapeMsys}--backoff=1{$escapeMsys} {$escapeMsys}--memory=2{$escapeMsys} {$escapeMsys}--sleep=3{$escapeMsys} {$escapeMsys}--tries=1{$escapeMsys} {$escapeMsys}--env=test{$escapeMsys}", $process->getCommandLine());
    }

    /**
     * Determine whether the installed symfony/process is at least the given version.
     *
     * Laravel reads this through Testbench, which this port does not depend on.
     */
    protected static function symfonyProcessAtLeast(string $version): bool
    {
        return version_compare(
            InstalledVersions::getPrettyVersion('symfony/process') ?? '0', $version, '>='
        );
    }
}
