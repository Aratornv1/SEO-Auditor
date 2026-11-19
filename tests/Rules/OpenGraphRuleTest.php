<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker\Tests\Rules;

use Aratornv1\SeoChecker\Rules\OpenGraphRule;
use Aratornv1\SeoChecker\Tests\Support\RunsRules;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OpenGraphRuleTest extends TestCase
{
    use RunsRules;

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function openGraphProvider(): iterable
    {
        $page = static fn (string $meta): string => "<html><head>{$meta}</head></html>";
        $og = static fn (string $property, string $content): string => sprintf('<meta property="%s" content="%s">', $property, $content);

        $title = $og('og:title', 'Title');
        $description = $og('og:description', 'Description');
        $image = $og('og:image', 'https://example.com/cover.png');

        yield 'all three present' => [$page($title . $description . $image), 'success'];
        yield 'property is case-insensitive' => [$page($title . $description . $og('OG:Image', 'https://example.com/a.png')), 'success'];
        yield 'nothing present' => [$page(''), 'warning'];
        yield 'missing image' => [$page($title . $description), 'warning'];
        yield 'missing title' => [$page($description . $image), 'warning'];
        yield 'empty content counts as missing' => [$page($title . $description . $og('og:image', '')), 'warning'];
        yield 'whitespace content counts as missing' => [$page($title . $description . $og('og:image', '   ')), 'warning'];
        yield 'relative image url' => [$page($title . $description . $og('og:image', '/cover.png')), 'warning'];
        yield 'twitter tags do not replace og' => [
            $page('<meta name="twitter:title" content="T"><meta name="twitter:image" content="https://example.com/a.png">'),
            'warning',
        ];
        yield 'og via name attribute is not valid' => [
            $page('<meta name="og:title" content="T"><meta name="og:description" content="D"><meta name="og:image" content="https://example.com/a.png">'),
            'warning',
        ];
        yield 'duplicate empty then filled' => [
            $page($og('og:title', '') . $title . $description . $image),
            'success',
        ];
    }

    #[DataProvider('openGraphProvider')]
    public function testReturnsExpectedStatus(string $html, string $expectedStatus): void
    {
        $this->assertStatus(new OpenGraphRule(), $html, $expectedStatus);
    }

    public function testMessageListsMissingTags(): void
    {
        $result = $this->runRule(
            new OpenGraphRule(),
            '<html><head><meta property="og:title" content="T"></head></html>',
        );

        self::assertStringContainsString('og:description', $result['message']);
        self::assertStringContainsString('og:image', $result['message']);
        self::assertStringNotContainsString('og:title', $result['message']);
    }
}
