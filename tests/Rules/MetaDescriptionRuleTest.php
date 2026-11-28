<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker\Tests\Rules;

use Aratornv1\SeoChecker\Rules\MetaDescriptionRule;
use Aratornv1\SeoChecker\Tests\Support\RunsRules;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MetaDescriptionRuleTest extends TestCase
{
    use RunsRules;

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function descriptionProvider(): iterable
    {
        $page = static fn (string $meta): string => "<html><head>{$meta}</head></html>";
        $tag = static fn (string $content): string => '<meta name="description" content="' . $content . '">';

        yield 'missing' => [$page(''), 'warning'];
        yield 'empty' => [$page($tag('')), 'warning'];
        yield 'whitespace only' => [$page($tag('    ')), 'warning'];
        yield 'too short' => [$page($tag('Too short to be useful')), 'warning'];
        yield 'too long' => [$page($tag(str_repeat('word ', 40))), 'warning'];
        yield 'optimal' => [$page($tag(str_repeat('word ', 20))), 'success'];
        yield 'exactly minimum (70)' => [$page($tag(str_repeat('a', 70))), 'success'];
        yield 'exactly maximum (160)' => [$page($tag(str_repeat('a', 160))), 'success'];
        yield 'one over maximum (161)' => [$page($tag(str_repeat('a', 161))), 'warning'];
        yield 'name is case-insensitive' => [
            $page('<meta name="Description" content="' . str_repeat('word ', 20) . '">'),
            'success',
        ];
        yield 'duplicated' => [
            $page($tag(str_repeat('word ', 20)) . $tag(str_repeat('text ', 20))),
            'warning',
        ];
        yield 'empty then filled is a duplicate' => [
            $page($tag('') . $tag(str_repeat('word ', 20))),
            'warning',
        ];
        yield 'two empty tags' => [$page($tag('') . $tag('  ')), 'warning'];
        yield 'cyrillic counted in characters' => [
            // 91 characters, but about 170 bytes in UTF-8.
            $page($tag('A practical guide to search engine optimisation: how to set up metadata and page structure.')),
            'success',
        ];
    }

    #[DataProvider('descriptionProvider')]
    public function testReturnsExpectedStatus(string $html, string $expectedStatus): void
    {
        $this->assertStatus(new MetaDescriptionRule(), $html, $expectedStatus);
    }

    public function testEmptyThenFilledMessageReportsDuplicates(): void
    {
        $result = $this->runRule(
            new MetaDescriptionRule(),
            '<html><head><meta name="description" content=""><meta name="description" content="'
            . str_repeat('word ', 20) . '"></head></html>',
        );

        self::assertStringContainsString('Several', $result->message);
    }

    public function testThresholdsAreConfigurable(): void
    {
        $this->assertStatus(
            new MetaDescriptionRule(minLength: 5, maxLength: 20),
            '<html><head><meta name="description" content="Short text"></head></html>',
            'success',
        );
    }
}
