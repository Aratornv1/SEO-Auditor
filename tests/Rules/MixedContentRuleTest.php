<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker\Tests\Rules;

use Aratornv1\SeoChecker\Rules\MixedContentRule;
use Aratornv1\SeoChecker\Tests\Support\RunsRules;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MixedContentRuleTest extends TestCase
{
    use RunsRules;

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function pageProvider(): iterable
    {
        yield 'no resources' => ['<html><body><p>Text</p></body></html>', 'success'];
        yield 'https and relative resources' => [
            '<html><head><link rel="stylesheet" href="https://cdn.example.com/a.css">'
            . '<script src="/app.js"></script></head><body><img src="logo.png" alt=""></body></html>',
            'success',
        ];
        yield 'protocol-relative URL is not http' => ['<html><body><img src="//cdn.example.com/a.png" alt=""></body></html>', 'success'];
        yield 'http link is not a resource' => ['<html><body><a href="http://example.com">x</a></body></html>', 'success'];
        yield 'http icon is not a stylesheet' => ['<html><head><link rel="icon" href="http://example.com/i.png"></head></html>', 'success'];
        yield 'http image' => ['<html><body><img src="http://example.com/a.png" alt=""></body></html>', 'warning'];
        yield 'http image upper case scheme' => ['<html><body><img src="HTTP://example.com/a.png" alt=""></body></html>', 'warning'];
        yield 'http candidate inside srcset' => [
            '<html><body><img src="https://example.com/a.png" srcset="https://example.com/a1.png 1x, http://example.com/a2.png 2x" alt=""></body></html>',
            'warning',
        ];
        yield 'http video poster' => ['<html><body><video src="https://example.com/v.mp4" poster="http://example.com/p.jpg"></video></body></html>', 'warning'];
        yield 'http script' => ['<html><head><script src="http://example.com/app.js"></script></head></html>', 'error'];
        yield 'http stylesheet, rel in any case and with other tokens' => [
            '<html><head><link rel="Preload Stylesheet" href="http://example.com/a.css"></head></html>',
            'error',
        ];
        yield 'http iframe' => ['<html><body><iframe src="http://example.com/embed"></iframe></body></html>', 'error'];
        yield 'active wins over passive' => [
            '<html><body><img src="http://example.com/a.png" alt=""><script src="http://example.com/a.js"></script></body></html>',
            'error',
        ];
    }

    #[DataProvider('pageProvider')]
    public function testReturnsExpectedStatus(string $html, string $expectedStatus): void
    {
        $this->assertStatus(new MixedContentRule(), $html, $expectedStatus);
    }

    public function testListsUniqueUrlsAndShortensLongLists(): void
    {
        $images = '';
        foreach (['a', 'b', 'a', 'c', 'd', 'e'] as $name) {
            $images .= sprintf('<img src="http://example.com/%s.png" alt="">', $name);
        }

        $result = $this->runRule(new MixedContentRule(), "<html><body>{$images}</body></html>");

        self::assertStringContainsString('http://example.com/a.png, http://example.com/b.png, http://example.com/c.png', $result->message);
        // a.png is listed once; d.png and e.png are summarised.
        self::assertSame(1, substr_count($result->message, 'a.png'));
        self::assertStringContainsString('і ще 2', $result->message);
    }

    public function testHasName(): void
    {
        self::assertSame('Mixed content', (new MixedContentRule())->getName());
    }
}
