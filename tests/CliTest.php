<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker\Tests;

use Aratornv1\SeoChecker\PageFetcher;
use Aratornv1\SeoChecker\Tests\Support\LocalServer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Runs the real bin/seo-check as a subprocess against a local server.
 */
final class CliTest extends TestCase
{
    private static LocalServer $server;

    public static function setUpBeforeClass(): void
    {
        self::$server = new LocalServer();
        self::$server->start(__DIR__ . '/Fixtures/router.php');
    }

    public static function tearDownAfterClass(): void
    {
        self::$server->stop();
    }

    public function testDefaultLimitIsTwoMegabytes(): void
    {
        self::assertSame(2 * 1024 * 1024, PageFetcher::DEFAULT_MAX_BYTES);
    }

    public function testAuditsPageWithDefaultLimit(): void
    {
        [$code, $out] = $this->cli(['--no-emoji', self::$server->url('/huge')]);

        // 1.5 MB fits into the 2 MB default limit, so the audit runs.
        self::assertContains($code, [0, 1], $out);
        self::assertStringContainsString('Summary', $out);
    }

    public function testMaxSizeOptionRejectsLargerPage(): void
    {
        [$code, $out] = $this->cli(['--max-size=1', self::$server->url('/huge')]);

        self::assertSame(2, $code);
        self::assertStringContainsString('exceeds the size limit', $out);
        self::assertStringContainsString('1 MB', $out);
    }

    public function testMaxSizeAcceptsSeparateArgumentAndDecimals(): void
    {
        [$code] = $this->cli(['--max-size', '0.5', self::$server->url('/huge')]);

        self::assertSame(2, $code);

        [$code] = $this->cli(['--max-size', '3', '--no-emoji', self::$server->url('/huge')]);

        self::assertContains($code, [0, 1]);
    }

    /**
     * @return iterable<string, array{list<string>}>
     */
    public static function invalidMaxSizeProvider(): iterable
    {
        yield 'not a number' => [['--max-size=abc', 'http://127.0.0.1:1/']];
        yield 'zero' => [['--max-size=0', 'http://127.0.0.1:1/']];
        yield 'negative' => [['--max-size=-5', 'http://127.0.0.1:1/']];
        yield 'above ceiling' => [['--max-size=101', 'http://127.0.0.1:1/']];
        yield 'missing value' => [['http://127.0.0.1:1/', '--max-size']];
        yield 'empty value' => [['--max-size=', 'http://127.0.0.1:1/']];
    }

    /**
     * @param list<string> $args
     */
    #[DataProvider('invalidMaxSizeProvider')]
    public function testInvalidMaxSizeIsAUsageError(array $args): void
    {
        [$code, $out] = $this->cli($args);

        self::assertSame(2, $code);
        self::assertStringContainsString('--max-size', $out);
    }

    public function testSecondUrlIsAnErrorInsteadOfSilentOverride(): void
    {
        [$code, $out] = $this->cli(['http://127.0.0.1:1/a', 'http://127.0.0.1:1/b']);

        self::assertSame(2, $code);
        self::assertStringContainsString('Only one URL', $out);
    }

    public function testJsonFormatOutputIsValidJson(): void
    {
        [$code, $out] = $this->cli(['--format=json', self::$server->url('/huge')]);

        self::assertContains($code, [0, 1], $out);
        $data = json_decode($out, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        self::assertIsArray($data['results'] ?? null);
        self::assertCount(10, $data['results']);
    }

    public function testMaxSizeDoesNotSwallowTheNextOption(): void
    {
        [$code, $out] = $this->cli(['--max-size', '--no-emoji', 'http://127.0.0.1:1/']);

        self::assertSame(2, $code);
        self::assertStringContainsString('Missing value for --max-size', $out);
        self::assertStringNotContainsString('Invalid --max-size value "--no-emoji"', $out);
    }

    public function testDoubleDashEndsOptionParsing(): void
    {
        // After "--" the argument is a URL, not an option, even though it starts with "-".
        [$code, $out] = $this->cli(['--', '-weird-host']);

        // It is treated as a (bad) URL rather than rejected as an unknown option.
        self::assertSame(2, $code);
        self::assertStringNotContainsString('Unknown option', $out);

        [$code] = $this->cli(['--no-emoji', '--', self::$server->url('/huge')]);

        self::assertContains($code, [0, 1]);
    }

    public function testUnknownOptionIsRejected(): void
    {
        [$code, $out] = $this->cli(['--bogus', 'http://127.0.0.1:1/']);

        self::assertSame(2, $code);
        self::assertStringContainsString('Unknown option: --bogus', $out);
    }

    public function testHelpMentionsMaxSize(): void
    {
        [$code, $out] = $this->cli(['--help']);

        self::assertSame(0, $code);
        self::assertStringContainsString('--max-size', $out);
    }

    /**
     * @param list<string> $args
     *
     * @return array{int, string} exit code and combined stdout+stderr
     */
    private function cli(array $args): array
    {
        $command = array_merge([PHP_BINARY, dirname(__DIR__) . '/bin/seo-check', '--no-color'], $args);

        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);

        $output = (string) stream_get_contents($pipes[1]) . (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $output];
    }
}
