<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker\Tests\Rules;

use Aratornv1\SeoChecker\Rules\HtmlLangRule;
use Aratornv1\SeoChecker\Tests\Support\RunsRules;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HtmlLangRuleTest extends TestCase
{
    use RunsRules;

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function langProvider(): iterable
    {
        yield 'missing attribute' => ['<html><head></head></html>', 'warning'];
        yield 'empty attribute' => ['<html lang=""><head></head></html>', 'warning'];
        yield 'whitespace only' => ['<html lang="  "><head></head></html>', 'warning'];
        yield 'simple language' => ['<html lang="uk"><head></head></html>', 'success'];
        yield 'language with region' => ['<html lang="en-US"><head></head></html>', 'success'];
        yield 'uppercase is accepted' => ['<html lang="UK"><head></head></html>', 'success'];
        yield 'three-part tag' => ['<html lang="zh-Hant-TW"><head></head></html>', 'success'];
        yield 'garbage value' => ['<html lang="english please"><head></head></html>', 'warning'];
        yield 'single letter' => ['<html lang="e"><head></head></html>', 'warning'];
    }

    #[DataProvider('langProvider')]
    public function testReturnsExpectedStatus(string $html, string $expectedStatus): void
    {
        $this->assertStatus(new HtmlLangRule(), $html, $expectedStatus);
    }
}
