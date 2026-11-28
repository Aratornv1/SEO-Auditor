<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker\Tests\Rules;

use Aratornv1\SeoChecker\Rules\ImageAltRule;
use Aratornv1\SeoChecker\Tests\Support\RunsRules;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ImageAltRuleTest extends TestCase
{
    use RunsRules;

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function imageProvider(): iterable
    {
        $page = static fn (string $body): string => "<html><body>{$body}</body></html>";

        yield 'no images' => [$page('<p>text</p>'), 'success'];
        yield 'all have alt' => [$page('<img src="a.png" alt="A"><img src="b.png" alt="B">'), 'success'];
        yield 'empty alt is valid (decorative)' => [$page('<img src="a.png" alt="">'), 'success'];
        yield 'one of four missing' => [
            $page('<img src="a.png" alt="A"><img src="b.png" alt="B"><img src="c.png" alt="C"><img src="d.png">'),
            'warning',
        ];
        yield 'half missing is an error' => [$page('<img src="a.png" alt="A"><img src="b.png">'), 'error'];
        yield 'all missing is an error' => [$page('<img src="a.png"><img src="b.png"><img src="c.png">'), 'error'];
        yield 'single image without alt' => [$page('<img src="a.png">'), 'error'];
        yield 'images in nested containers are counted' => [
            $page('<div><figure><img src="a.png"></figure></div><img src="b.png" alt="B"><img src="c.png" alt="C">'),
            'warning',
        ];
    }

    #[DataProvider('imageProvider')]
    public function testReturnsExpectedStatus(string $html, string $expectedStatus): void
    {
        $this->assertStatus(new ImageAltRule(), $html, $expectedStatus);
    }

    public function testMessageReportsCounts(): void
    {
        $result = $this->runRule(
            new ImageAltRule(),
            '<html><body><img src="a.png" alt="A"><img src="b.png" alt="B"><img src="c.png" alt="C"><img src="d.png"></body></html>',
        );

        self::assertStringContainsString('1 of 4', $result->message);
    }
}
