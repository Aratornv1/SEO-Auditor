<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker\Tests\Rules;

use Aratornv1\SeoChecker\Rules\CanonicalRule;
use Aratornv1\SeoChecker\Tests\Support\RunsRules;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CanonicalRuleTest extends TestCase
{
    use RunsRules;

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function canonicalProvider(): iterable
    {
        $page = static fn (string $links): string => "<html><head>{$links}</head></html>";

        yield 'missing' => [$page(''), 'warning'];
        yield 'absolute https' => [$page('<link rel="canonical" href="https://example.com/page">'), 'success'];
        yield 'absolute http' => [$page('<link rel="canonical" href="http://example.com/">'), 'success'];
        yield 'rel is case-insensitive' => [$page('<link rel="Canonical" href="https://example.com/">'), 'success'];
        yield 'multi-token rel' => [$page('<link rel="canonical alternate" href="https://example.com/">'), 'success'];
        yield 'relative url' => [$page('<link rel="canonical" href="/page">'), 'warning'];
        yield 'protocol-relative url' => [$page('<link rel="canonical" href="//example.com/page">'), 'warning'];
        yield 'empty href' => [$page('<link rel="canonical" href="">'), 'error'];
        yield 'no href attribute' => [$page('<link rel="canonical">'), 'error'];
        yield 'duplicated, same target' => [
            $page('<link rel="canonical" href="https://example.com/"><link rel="canonical" href="https://example.com/">'),
            'warning',
        ];
        yield 'conflicting targets' => [
            $page('<link rel="canonical" href="https://example.com/a"><link rel="canonical" href="https://example.com/b">'),
            'error',
        ];
        yield 'canonical in body is ignored (search engines ignore it too)' => [
            '<html><head></head><body><link rel="canonical" href="https://example.com/"></body></html>',
            'warning',
        ];
        yield 'body canonical does not create a false conflict' => [
            '<html><head><link rel="canonical" href="https://example.com/a"></head>'
            . '<body><link rel="canonical" href="https://example.com/b"></body></html>',
            'success',
        ];
        // canonical vs og:url: different spellings of one address are fine,
        // different addresses mean the page names two "main" links.
        yield 'og:url identical to canonical' => [
            $page('<link rel="canonical" href="https://example.com/page"><meta property="og:url" content="https://example.com/page">'),
            'success',
        ];
        yield 'og:url differs only by a trailing slash' => [
            $page('<link rel="canonical" href="https://example.com/page"><meta property="og:url" content="https://example.com/page/">'),
            'success',
        ];
        yield 'og:url differs only by host case' => [
            $page('<link rel="canonical" href="https://example.com/page"><meta property="og:url" content="https://EXAMPLE.com/page">'),
            'success',
        ];
        yield 'og:url differs only by a fragment' => [
            $page('<link rel="canonical" href="https://example.com/page"><meta property="og:url" content="https://example.com/page#top">'),
            'success',
        ];
        yield 'og:url with an explicit default port' => [
            $page('<link rel="canonical" href="https://example.com/page"><meta property="og:url" content="https://example.com:443/page">'),
            'success',
        ];
        yield 'root with and without slash' => [
            $page('<link rel="canonical" href="https://example.com"><meta property="og:url" content="https://example.com/">'),
            'success',
        ];
        yield 'og:url points to another page' => [
            $page('<link rel="canonical" href="https://example.com/page"><meta property="og:url" content="https://example.com/other">'),
            'warning',
        ];
        yield 'og:url uses http while canonical uses https' => [
            $page('<link rel="canonical" href="https://example.com/page"><meta property="og:url" content="http://example.com/page">'),
            'warning',
        ];
        yield 'og:url on another host (www)' => [
            $page('<link rel="canonical" href="https://example.com/page"><meta property="og:url" content="https://www.example.com/page">'),
            'warning',
        ];
        yield 'og:url differs by query string' => [
            $page('<link rel="canonical" href="https://example.com/page"><meta property="og:url" content="https://example.com/page?utm_source=x">'),
            'warning',
        ];
        yield 'empty og:url is ignored' => [
            $page('<link rel="canonical" href="https://example.com/page"><meta property="og:url" content="">'),
            'success',
        ];
        yield 'relative og:url is ignored here' => [
            $page('<link rel="canonical" href="https://example.com/page"><meta property="og:url" content="/other">'),
            'success',
        ];
        yield 'og:url without canonical is not this rule\'s business' => [
            $page('<meta property="og:url" content="https://example.com/page">'),
            'warning',
        ];
        yield 'other link rels are ignored' => [
            $page('<link rel="stylesheet" href="/style.css"><link rel="alternate" href="https://example.com/">'),
            'warning',
        ];
    }

    #[DataProvider('canonicalProvider')]
    public function testReturnsExpectedStatus(string $html, string $expectedStatus): void
    {
        $this->assertStatus(new CanonicalRule(), $html, $expectedStatus);
    }

    public function testMismatchMessageNamesBothAddressesAndTheMainOne(): void
    {
        $result = $this->runRule(
            new CanonicalRule(),
            '<html><head><link rel="canonical" href="https://example.com/main">'
            . '<meta property="og:url" content="https://example.com/copy"></head></html>',
        );

        self::assertSame('warning', $result->status->value);
        self::assertStringContainsString('https://example.com/main', $result->message);
        self::assertStringContainsString('https://example.com/copy', $result->message);
        self::assertStringContainsString('canonical is treated as the main one', $result->message);
    }

    public function testConflictMessageListsTheDifferentAddresses(): void
    {
        $result = $this->runRule(
            new CanonicalRule(),
            '<html><head><link rel="canonical" href="https://example.com/a">'
            . '<link rel="canonical" href="https://example.com/b"></head></html>',
        );

        self::assertSame('error', $result->status->value);
        self::assertStringContainsString('https://example.com/a', $result->message);
        self::assertStringContainsString('https://example.com/b', $result->message);
    }

    public function testConflictMessageIsShortenedForManyAddresses(): void
    {
        $links = '';
        foreach (range(1, 6) as $number) {
            $links .= '<link rel="canonical" href="https://example.com/' . $number . '">';
        }

        $result = $this->runRule(new CanonicalRule(), "<html><head>{$links}</head></html>");

        self::assertStringContainsString('and 3 more', $result->message);
        self::assertStringNotContainsString('https://example.com/6', $result->message);
    }

    public function testMatchingOgUrlIsMentionedInSuccessMessage(): void
    {
        $result = $this->runRule(
            new CanonicalRule(),
            '<html><head><link rel="canonical" href="https://example.com/page">'
            . '<meta property="og:url" content="https://example.com/page"></head></html>',
        );

        self::assertStringContainsString('matches og:url', $result->message);
    }

    public function testSuccessMessageContainsUrl(): void
    {
        $result = $this->runRule(
            new CanonicalRule(),
            '<html><head><link rel="canonical" href="https://example.com/page"></head></html>',
        );

        self::assertStringContainsString('https://example.com/page', $result->message);
    }
}
