<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker\Tests;

use Aratornv1\SeoChecker\Cli;
use Aratornv1\SeoChecker\Exception\FetchException;
use Aratornv1\SeoChecker\HtmlProviderInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Runs the Cli class in-process: a stub HTML provider and in-memory streams,
 * so there is no subprocess, no network and no local server.
 */
final class CliRunnerTest extends TestCase
{
    public function testHelpPrintsUsageAndSucceeds(): void
    {
        [$code, $out] = $this->runCli(['--help']);

        self::assertSame(Cli::EXIT_OK, $code);
        self::assertStringContainsString('--max-size', $out);
        self::assertStringContainsString('max: ' . Cli::MAX_SIZE_CEILING_MB, $out);
    }

    public function testNoUrlPrintsUsageAndFails(): void
    {
        [$code, $out] = $this->runCli([]);

        self::assertSame(Cli::EXIT_USAGE, $code);
        self::assertStringContainsString('Usage: seo-check', $out);
    }

    public function testUnknownOptionIsReportedOnStderr(): void
    {
        [$code, $out, $err] = $this->runCli(['--bogus', 'example.com']);

        self::assertSame(Cli::EXIT_USAGE, $code);
        self::assertStringContainsString('Unknown option: --bogus', $err);
        self::assertStringContainsString('Usage: seo-check', $out);
    }

    public function testSchemeIsAddedToUrlsWithoutOne(): void
    {
        $provider = $this->provider(['https://example.com' => $this->fixture('good.html')]);

        [$code] = $this->runCli(['--no-emoji', 'example.com'], $provider);

        self::assertSame(Cli::EXIT_OK, $code);
        self::assertSame(['https://example.com'], $provider->requested);
    }

    public function testMaxSizeIsPassedToTheProviderInBytes(): void
    {
        $limit = null;
        $provider = $this->provider(['https://example.com' => $this->fixture('good.html')]);
        $factory = static function (int $maxBytes) use (&$limit, $provider): HtmlProviderInterface {
            $limit = $maxBytes;

            return $provider;
        };

        $this->runCli(['--max-size=0.5', 'example.com'], null, $factory);

        self::assertSame(524288, $limit);
    }

    public function testErrorsInRulesGiveExitCodeOne(): void
    {
        $provider = $this->provider(['https://example.com' => $this->fixture('bad.html')]);

        [$code, $out] = $this->runCli(['--no-emoji', 'example.com'], $provider);

        self::assertSame(Cli::EXIT_ISSUES, $code);
        self::assertStringContainsString('[FAIL]', $out);
    }

    public function testSecondUrlIsAUsageError(): void
    {
        $provider = $this->provider([]);

        [$code, , $err] = $this->runCli(['a.test', 'b.test'], $provider);

        self::assertSame(Cli::EXIT_USAGE, $code);
        self::assertStringContainsString('Only one URL', $err);
        self::assertSame([], $provider->requested, 'Nothing is fetched when the arguments are invalid.');
    }

    public function testFailedDownloadIsReportedOnStderrInTextMode(): void
    {
        [$code, $out, $err] = $this->runCli(['missing.test']);

        self::assertSame(Cli::EXIT_USAGE, $code);
        self::assertSame('', $out);
        self::assertStringContainsString('missing.test', $err);
    }

    public function testJsonFormatPrintsOneObjectWithAllResults(): void
    {
        $provider = $this->provider(['https://example.com' => $this->fixture('good.html')]);

        [$code, $out, $err] = $this->runCli(['--format=json', 'example.com'], $provider);
        $data = json_decode($out, true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(Cli::EXIT_OK, $code);
        self::assertSame('', $err);
        self::assertSame('https://example.com', $data['url']);
        self::assertCount(10, $data['results']);
        self::assertSame(['rule', 'status', 'message'], array_keys($data['results'][0]));
        self::assertSame('Title', $data['results'][0]['rule']);
    }

    public function testJsonFormatKeepsExitCodeOneForRuleErrors(): void
    {
        $provider = $this->provider(['https://example.com' => $this->fixture('bad.html')]);

        [$code, $out] = $this->runCli(['--format', 'json', 'example.com'], $provider);
        $statuses = array_column(json_decode($out, true, flags: JSON_THROW_ON_ERROR)['results'], 'status');

        self::assertSame(Cli::EXIT_ISSUES, $code);
        self::assertContains('error', $statuses);
    }

    public function testJsonFormatReportsFailedDownloadAsJsonLine(): void
    {
        [$code, $out, $err] = $this->runCli(['--format=json', 'missing.test']);
        $data = json_decode($out, true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(Cli::EXIT_USAGE, $code);
        self::assertSame('', $err, 'stdout carries the error, stderr stays empty.');
        self::assertSame('https://missing.test', $data['url']);
        self::assertStringContainsString('missing.test', $data['error']);
        self::assertArrayNotHasKey('results', $data);
    }

    /**
     * @return iterable<string, array{list<string>, string}>
     */
    public static function invalidFormatProvider(): iterable
    {
        yield 'unknown value' => [['--format=xml', 'example.com'], 'Invalid --format value "xml"'];
        yield 'missing value' => [['example.com', '--format'], 'Missing value for --format'];
        yield 'next option is not a value' => [['--format', '--no-emoji', 'example.com'], 'Missing value for --format'];
    }

    /**
     * @param list<string> $args
     */
    #[DataProvider('invalidFormatProvider')]
    public function testInvalidFormatIsAUsageError(array $args, string $message): void
    {
        [$code, , $err] = $this->runCli($args);

        self::assertSame(Cli::EXIT_USAGE, $code);
        self::assertStringContainsString($message, $err);
    }

    public function testColorIsOnlyUsedWhenTheTerminalAllowsIt(): void
    {
        $page = $this->fixture('good.html');

        [, $plain] = $this->runCli(['example.com'], $this->provider(['https://example.com' => $page]), null, false);
        [, $colored] = $this->runCli(['example.com'], $this->provider(['https://example.com' => $page]), null, true);
        $withFlag = $this->provider(['https://example.com' => $page]);
        [, $disabled] = $this->runCli(['--no-color', 'example.com'], $withFlag, null, true);

        self::assertStringNotContainsString("\033[", $plain);
        self::assertStringContainsString("\033[", $colored);
        self::assertStringNotContainsString("\033[", $disabled);
    }

    /**
     * @param list<string> $args
     *
     * @return array{int, string, string} exit code, stdout, stderr
     */
    private function runCli(
        array $args,
        ?object $provider = null,
        ?\Closure $factory = null,
        bool $colorAllowed = false,
    ): array {
        $out = fopen('php://memory', 'w+');
        $err = fopen('php://memory', 'w+');
        self::assertIsResource($out);
        self::assertIsResource($err);

        $provider ??= $this->provider([]);
        $factory ??= static fn (int $maxBytes): HtmlProviderInterface => $provider;

        $code = (new Cli($factory, $out, $err, $colorAllowed))->run($args);

        rewind($out);
        rewind($err);

        return [$code, (string) stream_get_contents($out), (string) stream_get_contents($err)];
    }

    private function fixture(string $name): string
    {
        return (string) file_get_contents(__DIR__ . '/Fixtures/' . $name);
    }

    /**
     * A provider that serves the given pages and fails for every other URL.
     *
     * @param array<string, string> $pages URL => HTML
     */
    private function provider(array $pages): object
    {
        return new class ($pages) implements HtmlProviderInterface {
            /** @var list<string> */
            public array $requested = [];

            /**
             * @param array<string, string> $pages
             */
            public function __construct(private readonly array $pages)
            {
            }

            public function fetch(string $url): string
            {
                $this->requested[] = $url;

                return $this->pages[$url] ?? throw new FetchException('Не вдалося завантажити ' . $url);
            }
        };
    }
}
