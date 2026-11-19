<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker\Tests\Support;

use RuntimeException;

/**
 * Starts PHP's built-in web server on a free local port for integration tests.
 */
final class LocalServer
{
    /** @var resource|null */
    private $process;

    private int $port = 0;

    public function start(string $routerScript): void
    {
        $this->port = $this->findFreePort();

        $command = [PHP_BINARY, '-S', '127.0.0.1:' . $this->port, $routerScript];
        $pipes = [];

        $process = proc_open(
            $command,
            [0 => ['pipe', 'r'], 1 => ['file', $this->nullDevice(), 'w'], 2 => ['file', $this->nullDevice(), 'w']],
            $pipes,
        );

        if (!is_resource($process)) {
            throw new RuntimeException('Cannot start the PHP built-in server.');
        }

        $this->process = $process;
        $this->waitUntilReady();
    }

    public function stop(): void
    {
        if ($this->process !== null) {
            proc_terminate($this->process);
            proc_close($this->process);
            $this->process = null;
        }
    }

    public function url(string $path): string
    {
        return sprintf('http://127.0.0.1:%d%s', $this->port, $path);
    }

    private function findFreePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if ($socket === false) {
            throw new RuntimeException("Cannot find a free port: {$errstr}");
        }

        $name = stream_socket_get_name($socket, false);
        fclose($socket);

        return (int) substr((string) $name, (int) strrpos((string) $name, ':') + 1);
    }

    private function waitUntilReady(): void
    {
        for ($attempt = 0; $attempt < 50; $attempt++) {
            $connection = @fsockopen('127.0.0.1', $this->port, $errno, $errstr, 0.2);
            if ($connection !== false) {
                fclose($connection);

                return;
            }

            usleep(100_000);
        }

        $this->stop();

        throw new RuntimeException('The PHP built-in server did not become ready in time.');
    }

    private function nullDevice(): string
    {
        return PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
    }
}
