<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker\Tests\Rules;

use Aratornv1\SeoChecker\Rules\H1Rule;
use Aratornv1\SeoChecker\Tests\Support\RunsRules;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class H1RuleTest extends TestCase
{
    use RunsRules;

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function h1Provider(): iterable
    {
        $page = static fn (string $body): string => "<html><body>{$body}</body></html>";

        yield 'missing' => [$page('<h2>Subtitle</h2>'), 'error'];
        yield 'single' => [$page('<h1>Main topic</h1>'), 'success'];
        yield 'single with nested markup' => [$page('<h1>Main <em>topic</em> here</h1>'), 'success'];
        yield 'empty' => [$page('<h1></h1>'), 'error'];
        yield 'whitespace only' => [$page("<h1>  \n  </h1>"), 'error'];
        yield 'two h1' => [$page('<h1>One</h1><h1>Two</h1>'), 'warning'];
        yield 'many h1' => [$page('<h1>One</h1><h1>Two</h1><h1>Three</h1>'), 'warning'];
        yield 'empty h1 followed by a real one is reported as duplicate, not empty' => [
            $page('<h1> </h1><h1>Real title</h1>'),
            'warning',
        ];
        yield 'all h1 empty' => [$page('<h1></h1><h1> </h1>'), 'error'];
        yield 'logo image with alt counts as heading text' => [$page('<h1><img src="logo.png" alt="Company"></h1>'), 'success'];
        yield 'image with empty alt is empty' => [$page('<h1><img src="logo.png" alt=""></h1>'), 'error'];
        yield 'image without alt is empty' => [$page('<h1><img src="logo.png"></h1>'), 'error'];
        yield 'text wins over image alt' => [$page('<h1><img src="l.png" alt="Logo"> Welcome</h1>'), 'success'];
    }

    #[DataProvider('h1Provider')]
    public function testReturnsExpectedStatus(string $html, string $expectedStatus): void
    {
        $this->assertStatus(new H1Rule(), $html, $expectedStatus);
    }

    public function testEmptyThenFilledH1MessageMentionsDuplicates(): void
    {
        $result = $this->runRule(new H1Rule(), '<html><body><h1> </h1><h1>Real title</h1></body></html>');

        self::assertStringContainsString('2 теги', $result['message']);
        self::assertStringNotContainsString('порожній', $result['message']);
    }

    public function testImageAltIsUsedAsPreview(): void
    {
        $result = $this->runRule(new H1Rule(), '<html><body><h1><img src="l.png" alt="Company"></h1></body></html>');

        self::assertStringContainsString('Company', $result['message']);
    }

    public function testLongHeadingIsTruncatedInMessage(): void
    {
        $long = str_repeat('Заголовок ', 20);
        $result = $this->runRule(new H1Rule(), "<html><body><h1>{$long}</h1></body></html>");

        self::assertSame('success', $result['status']);
        self::assertStringContainsString('…', $result['message']);
        self::assertLessThan(120, mb_strlen($result['message']));
    }
}
