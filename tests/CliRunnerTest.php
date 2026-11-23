<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker\Tests;

use Aratornv1\SeoChecker\Cli;
use Aratornv1\SeoChecker\Exception\FetchException;
use Aratornv1\SeoChecker\HtmlProviderInterface;
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

    public function testSeveralUrlsAreAuditedInOrder(): void
    {
        $good = $this->fixture('good.html');
        $provider = $this->provider(['https://a.test' => $good, 'https://b.test' => $good]);

        [$code, $out] = $this->runCli(['--no-emoji', 'a.test', 'b.test'], $provider);

        self::assertSame(Cli::EXIT_OK, $code);
        self::assertSame(['https://a.test', 'https://b.test'], $provider->requested);
        self::assertSame(2, substr_count($out, 'Підсумок'));
        self::assertLessThan(strpos($out, 'https://b.test'), strpos($out, 'https://a.test'));
    }

    public function testFailedDownloadDoesNotStopTheOthersAndWinsTheExitCode(): void
    {
        // The first page has rule errors (would be code 1), the second cannot be downloaded.
        $provider = $this->provider(['https://bad.test' => $this->fixture('bad.html')]);

        [$code, $out, $err] = $this->runCli(['--no-emoji', 'bad.test', 'missing.test'], $provider);

        self::assertSame(Cli::EXIT_USAGE, $code);
        self::assertStringContainsString('Підсумок', $out);
        self::assertStringContainsString('missing.test', $err);
    }

    public function testMoreThanFiveUrlsIsAUsageError(): void
    {
        $provider = $this->provider([]);

        [$code, , $err] = $this->runCli(['a.test', 'b.test', 'c.test', 'd.test', 'e.test', 'f.test'], $provider);

        self::assertSame(Cli::EXIT_USAGE, $code);
        self::assertStringContainsString('At most 5 URLs', $err);
        self::assertSame([], $provider->requested, 'Nothing is fetched when the arguments are invalid.');
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
