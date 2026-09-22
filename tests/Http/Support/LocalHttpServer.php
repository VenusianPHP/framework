<?php

namespace Venusian\Tests\Http\Support;

use Symfony\Component\Process\Process;

final class LocalHttpServer
{
    private Process $process;
    public readonly int $port;

    public function __construct()
    {
        $sock = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        $name = stream_socket_get_name($sock, false);
        $this->port = (int) substr($name, strrpos($name, ':') + 1);
        fclose($sock);

        $this->process = new Process([PHP_BINARY, '-S', "127.0.0.1:{$this->port}", __DIR__.'/router.php'], null, ['PHP_CLI_SERVER_WORKERS' => '4']);
        $this->process->start();

        // php -S announces itself on stderr once it listens; false means it exited first
        if (! $this->process->waitUntil(fn (string $type, string $out) => str_contains($out, 'started')))
        {
            throw new \RuntimeException('fixture server did not start: '.$this->process->getErrorOutput());
        }
    }

    public function url(string $path): string { return "http://127.0.0.1:{$this->port}{$path}"; }

    public function stop(): void { $this->process->stop(0); }
}
