<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker\Tests\Rules;

use Aratornv1\SeoChecker\Rules\TitleRule;
use Aratornv1\SeoChecker\Tests\Support\RunsRules;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TitleRuleTest extends TestCase
{
    use RunsRules;

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function titleProvider(): iterable
    {
        yield 'missing title' => ['<html><head></head><body></body></html>', 'error'];
        yield 'empty title' => ['<html><head><title>   </title></head></html>', 'error'];
        yield 'too short' => ['<html><head><title>Short</title></head></html>', 'warning'];
        yield 'too long' => [
            '<html><head><title>' . str_repeat('word ', 20) . '</title></head></html>',
            'warning',
        ];
        yield 'optimal length' => [
            '<html><head><title>A perfectly reasonable page title here</title></head></html>',
            'success',
        ];
        yield 'duplicated title' => [
            '<html><head><title>First title that is long enough</title>'
            . '<title>Second title that is long enough</title></head></html>',
            'warning',
        ];
        yield 'empty title followed by a filled one is a duplicate, not empty' => [
            '<html><head><title></title><title>A perfectly reasonable page title here</title></head></html>',
            'warning',
        ];
        yield 'two empty titles' => ['<html><head><title></title><title> </title></head></html>', 'error'];
        yield 'cyrillic counted in characters, not bytes' => [
            // 36 characters (but 70+ bytes in UTF-8) - must be a success.
            '<html><head><title>Найкращий сайт про оптимізацію SEO</title></head></html>',
            'success',
        ];
        yield 'whitespace inside tag is collapsed' => [
            "<html><head><title>\n    A perfectly reasonable\n    page title here\n</title></head></html>",
            'success',
        ];
        yield 'svg title in body is ignored' => [
            '<html><head></head><body><svg><title>Icon title that is long enough</title></svg></body></html>',
            'error',
        ];
    }

    #[DataProvider('titleProvider')]
    public function testReturnsExpectedStatus(string $html, string $expectedStatus): void
    {
        $this->assertStatus(new TitleRule(), $html, $expectedStatus);
    }

    public function testThresholdsAreConfigurable(): void
    {
        $this->assertStatus(
            new TitleRule(minLength: 3, maxLength: 10),
            '<html><head><title>Short</title></head></html>',
            'success',
        );
    }

    public function testHasName(): void
    {
        self::assertSame('Title', (new TitleRule())->getName());
    }
}
