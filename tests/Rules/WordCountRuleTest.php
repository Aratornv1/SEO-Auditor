<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker\Tests\Rules;

use Aratornv1\SeoChecker\Rules\WordCountRule;
use Aratornv1\SeoChecker\Tests\Support\RunsRules;
use PHPUnit\Framework\TestCase;

final class WordCountRuleTest extends TestCase
{
    use RunsRules;

    public function testEmptyPageHasTooLittleText(): void
    {
        $this->assertStatus(new WordCountRule(), '<html><body></body></html>', 'warning');
        $this->assertStatus(new WordCountRule(), '<html><head><title>No body</title></head></html>', 'warning');
    }

    public function testEnoughWordsPass(): void
    {
        $html = '<html><body><p>' . str_repeat('word ', 300) . '</p></body></html>';

        $this->assertStatus(new WordCountRule(), $html, 'success');
    }

    public function testCountsWordsExactly(): void
    {
        // "п'ять" and "e-mail" are one word each; numbers count as words.
        $html = "<html><body><h1>Привіт, світе!</h1><p>П'ять порад про e-mail за 2026 рік.</p></body></html>";

        $result = $this->runRule(new WordCountRule(minWords: 1), $html);

        // Привіт, світе (2) + П'ять, порад, про, e-mail, за, 2026, рік (7).
        self::assertSame('Enough text (9 words)', $result->message);
    }

    public function testInvisibleTextIsNotCounted(): void
    {
        $html = '<html><head><title>Title is not body text</title></head><body><p>Two words</p>'
            . '<script>var a = "script text";</script><style>p { color: red; }</style>'
            . '<noscript>Enable JavaScript please</noscript><template><p>Hidden template</p></template>'
            . '</body></html>';

        $result = $this->runRule(new WordCountRule(minWords: 1), $html);

        self::assertSame('Enough text (2 words)', $result->message);
    }

    public function testAdjacentElementsAreSeparateWords(): void
    {
        $result = $this->runRule(new WordCountRule(minWords: 1), '<html><body><b>one</b><i>two</i></body></html>');

        self::assertSame('Enough text (2 words)', $result->message);
    }

    public function testThresholdIsConfigurable(): void
    {
        $html = '<html><body><p>Only three words</p></body></html>';

        $this->assertStatus(new WordCountRule(minWords: 3), $html, 'success');
        $this->assertStatus(new WordCountRule(minWords: 4), $html, 'warning');
    }

    public function testWarningShowsCountAndThreshold(): void
    {
        $result = $this->runRule(new WordCountRule(), '<html><body><p>One</p></body></html>');

        self::assertSame('Not enough text on the page (1 word, at least 300 recommended)', $result->message);
    }

    public function testHasName(): void
    {
        self::assertSame('Word count', (new WordCountRule())->getName());
    }
}
