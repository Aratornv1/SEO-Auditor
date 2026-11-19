<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker\Tests\Rules;

use Aratornv1\SeoChecker\Rules\RobotsMetaRule;
use Aratornv1\SeoChecker\Tests\Support\RunsRules;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RobotsMetaRuleTest extends TestCase
{
    use RunsRules;

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function robotsProvider(): iterable
    {
        $page = static fn (string $meta): string => "<html><head>{$meta}</head></html>";

        yield 'no robots meta (indexable by default)' => [$page(''), 'success'];
        yield 'index, follow' => [$page('<meta name="robots" content="index, follow">'), 'success'];
        yield 'noindex' => [$page('<meta name="robots" content="noindex">'), 'error'];
        yield 'noindex, nofollow' => [$page('<meta name="robots" content="noindex, nofollow">'), 'error'];
        yield 'case-insensitive values' => [$page('<meta name="ROBOTS" content="NoIndex">'), 'error'];
        yield 'spaces around directives' => [$page('<meta name="robots" content="  follow ,  noindex  ">'), 'error'];
        yield 'none is noindex+nofollow' => [$page('<meta name="robots" content="none">'), 'error'];
        yield 'nofollow only' => [$page('<meta name="robots" content="index, nofollow">'), 'warning'];
        yield 'googlebot noindex' => [$page('<meta name="googlebot" content="noindex">'), 'error'];
        yield 'noindex in second tag' => [
            $page('<meta name="robots" content="index"><meta name="googlebot" content="noindex">'),
            'error',
        ];
        yield 'unrelated directives only' => [$page('<meta name="robots" content="max-snippet:-1">'), 'success'];
        yield 'empty content' => [$page('<meta name="robots" content="">'), 'success'];
        yield 'noindex word inside another meta is ignored' => [
            $page('<meta name="description" content="how to use noindex correctly">'),
            'success',
        ];
    }

    #[DataProvider('robotsProvider')]
    public function testReturnsExpectedStatus(string $html, string $expectedStatus): void
    {
        $this->assertStatus(new RobotsMetaRule(), $html, $expectedStatus);
    }
}
