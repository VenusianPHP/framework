<?php

// Router for `php -S`. Routes:
//   /delay?ms=N         answers after N milliseconds
//   /echo               answers with the method, query and body it got
//   /flaky?key=K&fail=N answers 503 for the first N requests with key K, then 200

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
header('Content-Type: application/json');

switch ($path) {
    case '/delay':
        usleep((int) ($_GET['ms'] ?? 0) * 1000);
        echo json_encode(['delayed' => (int) ($_GET['ms'] ?? 0)]);
        break;

    case '/echo':
        echo json_encode(['method' => $_SERVER['REQUEST_METHOD'], 'query' => $_GET, 'body' => file_get_contents('php://input')]);
        break;

    case '/flaky':
        $counter = sys_get_temp_dir().'/venusian-http-flaky-'.preg_replace('/\W/', '', $_GET['key'] ?? '');
        $seen = (int) @file_get_contents($counter);
        file_put_contents($counter, (string) ($seen + 1));

        if ($seen < (int) ($_GET['fail'] ?? 0)) {
            http_response_code(503);
            echo json_encode(['attempt' => $seen + 1]);
            break;
        }

        unlink($counter);
        echo json_encode(['attempt' => $seen + 1]);
        break;

    default:
        http_response_code(404);
        echo json_encode(['missing' => $path]);
}
