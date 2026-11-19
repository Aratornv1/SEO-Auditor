<?php

// Router script for PHP's built-in web server, used only by PageFetcherTest.
// It emulates the HTTP situations the fetcher has to handle.

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

switch ($path) {
    case '/ok':
        header('Content-Type: text/html; charset=utf-8');
        echo '<html><head><title>Fixture page</title></head><body>Привіт</body></html>';
        break;

    case '/redirect':
        header('Location: /ok', true, 302);
        break;

    case '/redirect-loop':
        header('Location: /redirect-loop', true, 302);
        break;

    case '/redirect-to-file':
        header('Location: file:///etc/passwd', true, 302);
        break;

    case '/missing':
        http_response_code(404);
        header('Content-Type: text/html');
        echo '<html><body>Not found</body></html>';
        break;

    case '/error':
        http_response_code(500);
        header('Content-Type: text/html');
        echo '<html><body>Server error</body></html>';
        break;

    case '/json':
        header('Content-Type: application/json');
        echo '{"hello":"world"}';
        break;

    case '/empty':
        header('Content-Type: text/html');
        break;

    case '/no-content-type':
        header('Content-Type:');
        echo '<html><head><title>No content type</title></head></html>';
        break;

    case '/big':
        header('Content-Type: text/html');
        echo '<html><body>' . str_repeat('x', 20000) . '</body></html>';
        break;

    case '/huge':
        // About 1.5 MiB: above a 1 MB limit, below the 2 MiB default.
        header('Content-Type: text/html');
        echo '<html lang="uk"><head><title>Huge page</title></head><body>' . str_repeat('x', 1_500_000) . '</body></html>';
        break;

    case '/gzip':
        header('Content-Type: text/html');
        header('Content-Encoding: gzip');
        echo gzencode('<html><head><title>Compressed page</title></head></html>');
        break;

    case '/cp1251-header':
        // PHP would append "; charset=UTF-8" to text/* types by default.
        ini_set('default_charset', '');
        header('Content-Type: text/html; charset=windows-1251');
        echo mb_convert_encoding(
            '<html lang="uk"><head><title>Привіт, світе — тестова сторінка</title></head><body><h1>Заголовок</h1></body></html>',
            'Windows-1251',
            'UTF-8',
        );
        break;

    case '/cp1251-meta':
        // No charset in the HTTP header: only the <meta> tag declares the encoding.
        ini_set('default_charset', '');
        header('Content-Type: text/html');
        echo mb_convert_encoding(
            '<html lang="uk"><head><meta charset="windows-1251"><title>Привіт, світе — тестова сторінка</title></head><body></body></html>',
            'Windows-1251',
            'UTF-8',
        );
        break;

    case '/svg':
        header('Content-Type: image/svg+xml');
        echo '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"></svg>';
        break;

    case '/rss':
        header('Content-Type: application/rss+xml');
        echo '<rss version="2.0"><channel><title>Feed</title></channel></rss>';
        break;

    case '/drip':
        // Sends one byte every half second: never idle for long, but never finishes quickly.
        header('Content-Type: text/html');
        for ($i = 0; $i < 12; $i++) {
            echo 'x';
            flush();
            usleep(500_000);
        }
        break;

    case '/slow':
        header('Content-Type: text/html');
        flush();
        sleep(3);
        echo '<html><body>late</body></html>';
        break;

    default:
        http_response_code(404);
        echo 'unknown route';
}
