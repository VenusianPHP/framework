<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$q = $_GET;
header('Content-Type: application/json');
match (true) {
    $path === '/ok' => print json_encode(['ok' => true]),
    str_starts_with($path, '/status/') => http_response_code((int) substr($path, 8)),
    $path === '/slow' => (usleep(1000 * (int) ($q['ms'] ?? 200)) || true) && print json_encode(['slept' => (int) ($q['ms'] ?? 200)]),
    $path === '/echo' => print json_encode(['method' => $_SERVER['REQUEST_METHOD'], 'body' => file_get_contents('php://input')]),
    default => http_response_code(404),
};
