<?php

// usage: server.php <port>
//
// The Http tests' server. It forks a child for every connection, so requests really are answered
// side by side: `php -S` workers each run one event loop, and a worker can accept several
// connections before it runs the first, then serve them one after another. Routes:
//   /delay?ms=N         answers after N milliseconds
//   /echo               answers with the method, query and body it got
//   /flaky?key=K&fail=N answers 503 for the first N requests with key K, then 200
//   /keep/<route>       <route>, on a connection kept open for the client's next request

$server = stream_socket_server('tcp://127.0.0.1:'.(int) $argv[1], $errno, $errstr);

if ($server === false) {
    fwrite(STDERR, "Could not listen: {$errstr}\n");
    exit(1);
}

// The children are never waited on: ignoring SIGCHLD has the kernel reap them.
pcntl_signal(SIGCHLD, SIG_IGN);

while (true) {
    $client = @stream_socket_accept($server, -1);

    if ($client === false) {
        continue;
    }

    if (pcntl_fork() === 0) {
        fclose($server);
        serve($client);
        exit(0);
    }

    fclose($client);
}

/**
 * Answer requests on one connection: one, unless the client asks for a /keep/ route, which
 * keeps the connection for the next request until the client closes it.
 * @param resource $client
 */
function serve($client): void
{
    stream_set_timeout($client, 5);

    while (answer($client)) {
        // kept alive: wait for the client's next request on this connection
    }

    fclose($client);
}

/**
 * @param resource $client
 * @return bool Whether the connection stays open for another request.
 */
function answer($client): bool
{
    $head = '';

    while (! str_contains($head, "\r\n\r\n") && ($line = fgets($client)) !== false) {
        $head .= $line;
    }

    if ($head === '') {
        return false;
    }

    [$method, $target] = explode(' ', strtok($head, "\r\n")) + ['GET', '/'];

    if (preg_match('/^expect:\s*100-continue/mi', $head)) {
        fwrite($client, "HTTP/1.1 100 Continue\r\n\r\n");
    }

    $length = preg_match('/^content-length:\s*(\d+)/mi', $head, $match) ? (int) $match[1] : 0;
    $body = '';

    while (strlen($body) < $length && ($chunk = fread($client, $length - strlen($body))) !== false && $chunk !== '') {
        $body .= $chunk;
    }

    parse_str((string) parse_url($target, PHP_URL_QUERY), $query);
    $path = (string) parse_url($target, PHP_URL_PATH);
    $keep = str_starts_with($path, '/keep/');
    [$status, $payload] = route($method, $keep ? substr($path, strlen('/keep')) : $path, $query, $body);

    $reason = [200 => 'OK', 404 => 'Not Found', 503 => 'Service Unavailable'][$status];
    $connection = $keep ? 'keep-alive' : 'close';

    fwrite($client, "HTTP/1.1 {$status} {$reason}\r\nContent-Type: application/json\r\nContent-Length: ".strlen($payload)."\r\nConnection: {$connection}\r\n\r\n".$payload);

    return $keep;
}

/**
 * @param array<string, mixed> $query
 * @return array{0: int, 1: string}
 */
function route(string $method, string $path, array $query, string $body): array
{
    switch ($path) {
        case '/delay':
            usleep((int) ($query['ms'] ?? 0) * 1000);

            return [200, json_encode(['delayed' => (int) ($query['ms'] ?? 0)])];

        case '/echo':
            return [200, json_encode(['method' => $method, 'query' => $query, 'body' => $body])];

        case '/flaky':
            $counter = sys_get_temp_dir().'/venusian-http-flaky-'.preg_replace('/\W/', '', $query['key'] ?? '');
            $seen = (int) @file_get_contents($counter);
            file_put_contents($counter, (string) ($seen + 1));

            if ($seen < (int) ($query['fail'] ?? 0)) {
                return [503, json_encode(['attempt' => $seen + 1])];
            }

            unlink($counter);

            return [200, json_encode(['attempt' => $seen + 1])];

        default:
            return [404, json_encode(['missing' => $path])];
    }
}
