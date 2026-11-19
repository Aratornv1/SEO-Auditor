<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker\Tests\Rules;

use Aratornv1\SeoChecker\Rules\HeadingHierarchyRule;
use Aratornv1\SeoChecker\Tests\Support\RunsRules;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HeadingHierarchyRuleTest extends TestCase
{
    use RunsRules;

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function hierarchyProvider(): iterable
    {
        $page = static fn (string $body): string => "<html><body>{$body}</body></html>";

        yield 'no headings' => [$page('<p>text</p>'), 'warning'];
        yield 'single h1' => [$page('<h1>A</h1>'), 'success'];
        yield 'proper nesting' => [$page('<h1>A</h1><h2>B</h2><h3>C</h3><h2>D</h2>'), 'success'];
        yield 'going back up is fine' => [$page('<h1>A</h1><h2>B</h2><h3>C</h3><h4>D</h4><h2>E</h2>'), 'success'];
        yield 'jump from h1 to h3' => [$page('<h1>A</h1><h3>B</h3>'), 'warning'];
        yield 'jump from h2 to h4' => [$page('<h1>A</h1><h2>B</h2><h4>C</h4>'), 'warning'];
        yield 'skip after returning up' => [$page('<h1>A</h1><h2>B</h2><h3>C</h3><h2>D</h2><h4>E</h4>'), 'warning'];
        yield 'multiple h1 are not a hierarchy problem' => [$page('<h1>A</h1><h1>B</h1>'), 'success'];
        yield 'first heading deeper than h1 is left to H1Rule' => [$page('<h2>A</h2><h3>B</h3>'), 'success'];
        yield 'headings nested in containers keep document order' => [
            $page('<div><h1>A</h1></div><section><h2>B</h2></section><section><h4>C</h4></section>'),
            'warning',
        ];
    }

    #[DataProvider('hierarchyProvider')]
    public function testReturnsExpectedStatus(string $html, string $expectedStatus): void
    {
        $this->assertStatus(new HeadingHierarchyRule(), $html, $expectedStatus);
    }

    public function testMessageNamesTheSkippedLevels(): void
    {
        $result = $this->runRule(
            new HeadingHierarchyRule(),
            '<html><body><h1>A</h1><h3>B</h3></body></html>',
        );

        self::assertStringContainsString('h1 → h3', $result['message']);
    }

    public function testManySkipsAreSummarised(): void
    {
        $body = '<h1>A</h1>' . str_repeat('<h3>x</h3><h5>y</h5>', 3);
        $result = $this->runRule(new HeadingHierarchyRule(), "<html><body>{$body}</body></html>");

        self::assertSame('warning', $result['status']);
        self::assertStringContainsString('і ще', $result['message']);
    }
}
