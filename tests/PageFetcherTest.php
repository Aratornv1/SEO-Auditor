<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker\Tests;

use Aratornv1\SeoChecker\Exception\FetchException;
use Aratornv1\SeoChecker\PageFetcher;
use Aratornv1\SeoChecker\Tests\Support\LocalServer;
use Aratornv1\SeoChecker\XPathFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests: a real PHP built-in server is started once and the
 * fetcher is exercised through both transports (cURL and streams).
 */
final class PageFetcherTest extends TestCase
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

    /**
     * @return iterable<string, array{bool}>
     */
    public static function transportProvider(): iterable
    {
        if (extension_loaded('curl')) {
            yield 'curl' => [true];
        }

        yield 'streams' => [false];
    }

    #[DataProvider('transportProvider')]
    public function testFetchesHtml(bool $preferCurl): void
    {
        $html = $this->fetcher($preferCurl)->fetch(self::$server->url('/ok'));

        self::assertStringContainsString('<title>Fixture page</title>', $html);
        self::assertStringContainsString('Привіт', $html);
    }

    #[DataProvider('transportProvider')]
    public function testFollowsRedirects(bool $preferCurl): void
    {
        $html = $this->fetcher($preferCurl)->fetch(self::$server->url('/redirect'));

        self::assertStringContainsString('Fixture page', $html);
    }

    // Transparent decompression is provided by the cURL transport only.
    public function testDecodesCompressedResponse(): void
    {
        if (!extension_loaded('curl')) {
            self::markTestSkipped('cURL extension is not available.');
        }

        $html = $this->fetcher(true)->fetch(self::$server->url('/gzip'));

        self::assertStringContainsString('Compressed page', $html);
    }

    #[DataProvider('transportProvider')]
    public function testToleratesMissingContentType(bool $preferCurl): void
    {
        $html = $this->fetcher($preferCurl)->fetch(self::$server->url('/no-content-type'));

        self::assertStringContainsString('No content type', $html);
    }

    #[DataProvider('transportProvider')]
    public function testRejectsHttpErrorStatus(bool $preferCurl): void
    {
        $this->expectException(FetchException::class);
        $this->expectExceptionMessage('404');

        $this->fetcher($preferCurl)->fetch(self::$server->url('/missing'));
    }

    #[DataProvider('transportProvider')]
    public function testRejectsServerError(bool $preferCurl): void
    {
        $this->expectException(FetchException::class);
        $this->expectExceptionMessage('500');

        $this->fetcher($preferCurl)->fetch(self::$server->url('/error'));
    }

    #[DataProvider('transportProvider')]
    public function testRejectsNonHtmlContentType(bool $preferCurl): void
    {
        $this->expectException(FetchException::class);
        $this->expectExceptionMessage('application/json');

        $this->fetcher($preferCurl)->fetch(self::$server->url('/json'));
    }

    #[DataProvider('transportProvider')]
    public function testRejectsEmptyBody(bool $preferCurl): void
    {
        $this->expectException(FetchException::class);
        $this->expectExceptionMessage('Порожня');

        $this->fetcher($preferCurl)->fetch(self::$server->url('/empty'));
    }

    #[DataProvider('transportProvider')]
    public function testRejectsOversizedResponse(bool $preferCurl): void
    {
        $this->expectException(FetchException::class);
        $this->expectExceptionMessage('ліміт');

        $this->fetcher($preferCurl, maxBytes: 5_000)->fetch(self::$server->url('/big'));
    }

    #[DataProvider('transportProvider')]
    public function testRejectsRedirectLoop(bool $preferCurl): void
    {
        $this->expectException(FetchException::class);

        $this->fetcher($preferCurl)->fetch(self::$server->url('/redirect-loop'));
    }

    public function testDoesNotFollowRedirectToLocalFile(): void
    {
        if (!extension_loaded('curl')) {
            self::markTestSkipped('Protocol restriction is enforced by the cURL transport.');
        }

        $this->expectException(FetchException::class);

        $this->fetcher(true)->fetch(self::$server->url('/redirect-to-file'));
    }

    #[DataProvider('transportProvider')]
    public function testUnreachableHostFails(bool $preferCurl): void
    {
        $this->expectException(FetchException::class);
        $this->expectExceptionMessage('Не вдалося');

        // Port 1 is reserved and nothing listens there.
        $this->fetcher($preferCurl, timeout: 2)->fetch('http://127.0.0.1:1/');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidUrlProvider(): iterable
    {
        yield 'ftp scheme' => ['ftp://example.com/file'];
        yield 'file scheme' => ['file:///etc/passwd'];
        yield 'no scheme' => ['example.com'];
        yield 'javascript scheme' => ['javascript:alert(1)'];
        yield 'missing host' => ['http:///path'];
    }

    #[DataProvider('invalidUrlProvider')]
    public function testRejectsInvalidUrls(string $url): void
    {
        $this->expectException(FetchException::class);

        (new PageFetcher())->fetch($url);
    }

    #[DataProvider('transportProvider')]
    public function testConvertsWindows1251ToUtf8ByHttpHeader(bool $preferCurl): void
    {
        $html = $this->fetcher($preferCurl)->fetch(self::$server->url('/cp1251-header'));

        self::assertStringContainsString('Привіт, світе — тестова сторінка', $html);
        self::assertTrue(mb_check_encoding($html, 'UTF-8'));
    }

    #[DataProvider('transportProvider')]
    public function testConvertsWindows1251ToUtf8ByMetaTag(bool $preferCurl): void
    {
        $html = $this->fetcher($preferCurl)->fetch(self::$server->url('/cp1251-meta'));

        self::assertStringContainsString('Привіт, світе — тестова сторінка', $html);

        // The converted page still says charset=windows-1251 in <meta>; the
        // DOM loader must not be misled by it.
        $title = (new XPathFactory())->create($html)->evaluate('string(//head/title)');

        self::assertSame('Привіт, світе — тестова сторінка', $title);
    }

    /**
     * @return iterable<string, array{bool, string, string}>
     */
    public static function nonPageProvider(): iterable
    {
        foreach (self::transportProvider() as $transport => [$preferCurl]) {
            yield "svg via $transport" => [$preferCurl, '/svg', 'image/svg+xml'];
            yield "rss feed via $transport" => [$preferCurl, '/rss', 'application/rss+xml'];
        }
    }

    #[DataProvider('nonPageProvider')]
    public function testRejectsNonPageXmlTypes(bool $preferCurl, string $path, string $contentType): void
    {
        $this->expectException(FetchException::class);
        $this->expectExceptionMessage($contentType);

        $this->fetcher($preferCurl)->fetch(self::$server->url($path));
    }

    // Keep the slowest tests last: the single-threaded server stays busy
    // for a moment after the client gives up.
    #[DataProvider('transportProvider')]
    public function testTimesOutOnSlowServer(bool $preferCurl): void
    {
        $this->expectException(FetchException::class);

        $this->fetcher($preferCurl, timeout: 1)->fetch(self::$server->url('/slow'));
    }

    #[DataProvider('transportProvider')]
    public function testTotalDeadlineAppliesToSlowDrippingResponse(bool $preferCurl): void
    {
        $started = microtime(true);

        try {
            $this->fetcher($preferCurl, timeout: 2)->fetch(self::$server->url('/drip'));
            self::fail('A response that drips for ~6 seconds must hit the 2 second total limit.');
        } catch (FetchException) {
            // Expected: the limit is total, not per read.
        }

        // The response would take ~6 s; the limit must cut it well before that.
        self::assertLessThan(4.5, microtime(true) - $started);
    }

    private function fetcher(bool $preferCurl, int $timeout = 5, int $maxBytes = 5_000_000): PageFetcher
    {
        return new PageFetcher(timeoutSeconds: $timeout, maxBytes: $maxBytes, preferCurl: $preferCurl);
    }
}
