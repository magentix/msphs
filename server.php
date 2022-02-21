<?php

declare(strict_types=1);

error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);

const ROOT = __DIR__ . '/';
const SITE = ROOT . 'www/';

$resource = stream_context_create(
    [
        'ssl' => [
            'local_cert' => ROOT . 'cert.pem',
            'local_pk' => ROOT . 'key.pem',
            'verify_peer' => false,
            'verify_peer_name' => false,
        ]
    ]
);
$socket = stream_socket_server(address: 'tlsv1.3://0:443', context: $resource);

while (true) {
    if (!($fSocket = stream_socket_accept($socket, -1))) {
        continue;
    }
    try {
        $request = parseRequest(trim(fread($fSocket, 1024) ?: ''));
        $response = $request['method'] === 'GET' ? getResponse($request) : "HTTP/1.1 403 Forbidden\r\n\r\n";
    } catch (Exception $exception) {
        $response = "HTTP/1.1 500 Internal Server Error\r\n\r\n";
    }
    fwrite($fSocket, $response);
    fclose($fSocket);
}

function parseRequest(string $request): array
{
    $result = ['method' => '', 'path' => '', 'headers' => []];

    preg_match('/^(?P<method>.*) (?P<path>.*) (?P<protocol>.*)(\r|\r?\n)(?P<headers>.*)$/sU', $request, $matches);

    $result['method'] = $matches['method'] ?? 'GET';
    $result['path'] = $matches['path'] ?? '/';

    $headers = explode("\r\n", trim($matches['headers'] ?? ''));
    $headers = array_filter(array_map('trim', $headers));

    foreach ($headers as $row) {
        $header = explode(':', $row, 2);
        if (!isset($header[0], $header[1])) {
            continue;
        }
        $result['headers'][trim(strtolower($header[0]))] = trim($header[1]);
    }

    return $result;
}

function getResponse(array $request): string
{
    $path = rtrim($request['path'], '/') . (!pathinfo($request['path'], PATHINFO_EXTENSION) ? '/' : '');
    $path .= str_ends_with($path, '/') ? 'index.html' : '';
    $file = SITE . ltrim(str_replace('../', '', $path), '/');

    $result = ['status' => 'HTTP/1.1 200 OK', 'headers' => []];

    if (!file_exists($file)) {
        $result['status'] = 'HTTP/1.1 404 Not Found';
        $file = SITE . '404.html';
    }

    $ext = pathinfo($file, PATHINFO_EXTENSION);

    $content = file_exists($file) ? file_get_contents($file) : '';
    if (in_array($ext, ['html', 'css', 'js']) && str_contains($request['headers']['accept-encoding'] ?? '', 'gzip')) {
        $content = gzencode($content, 9);
        $result['headers']['content-encoding'] = 'gzip';
    }

    $result['headers']['Content-Length'] = strlen($content);
    $result['headers']['X-Frame-Options'] = 'DENY';
    $result['headers']['X-XSS-Protection'] = "1; mode=block";
    $result['headers']['X-Content-Type-Options'] = 'nosniff';
    $result['headers']['Vary'] = 'Accept-Encoding';
    $result['headers']['Content-Type'] = match ($ext) {
        'html'  => 'text/html; charset=utf-8',
        'css'   => 'text/css; charset=utf-8',
        'js'    => 'application/javascript; charset=utf-8',
        default => mime_content_type($file)
    };

    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'js', 'css'])) {
        $result['headers']['Cache-Control'] = 'max-age=900';
        $result['headers']['Expires'] = gmdate("D, d M Y H:i:s", time() + 900) . ' GMT';
    }

    $response = $result['status'] . "\r\n";
    foreach ($result['headers'] as $key => $value) {
        $response .= $key . ':' . $value . "\r\n";
    }

    return $response . "\r\n" . $content;
}
