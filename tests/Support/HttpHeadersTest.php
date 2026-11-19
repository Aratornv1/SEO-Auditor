<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker\Tests\Support;

use Aratornv1\SeoChecker\Support\HttpHeaders;
use PHPUnit\Framework\TestCase;

final class HttpHeadersTest extends TestCase
{
    /**
     * Header lines of a redirect chain: 301 -> 200.
     *
     * @return list<string>
     */
    private function redirectChain(): array
    {
        return [
            'HTTP/1.1 301 Moved Permanently',
            'Content-Type: text/html',
            'Location: /new',
            'HTTP/1.1 200 OK',
            'Server: test',
        ];
    }

    public function testStatusCodeIsTakenFromTheFinalResponse(): void
    {
        self::assertSame(200, HttpHeaders::statusCode($this->redirectChain()));
    }

    public function testFinalResponseDropsEarlierHops(): void
    {
        self::assertSame(['HTTP/1.1 200 OK', 'Server: test'], HttpHeaders::finalResponse($this->redirectChain()));
    }

    public function testHeaderFromAnEarlierHopIsNotInherited(): void
    {
        // The 301 carried Content-Type, the final 200 did not: the value must be empty.
        self::assertSame('', HttpHeaders::value($this->redirectChain(), 'Content-Type'));
    }

    public function testHeaderLookupIsCaseInsensitive(): void
    {
        $lines = ['HTTP/1.1 200 OK', 'content-TYPE:  text/html; charset=utf-8 '];

        self::assertSame('text/html; charset=utf-8', HttpHeaders::value($lines, 'Content-Type'));
    }

    public function testMissingStatusLineGivesZero(): void
    {
        self::assertSame(0, HttpHeaders::statusCode(['Content-Type: text/html']));
        self::assertSame(0, HttpHeaders::statusCode([]));
    }

    public function testHttp2StatusLine(): void
    {
        self::assertSame(404, HttpHeaders::statusCode(['HTTP/2 404']));
    }
}
