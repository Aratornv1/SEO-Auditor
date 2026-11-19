<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker\Tests\Rules;

use Aratornv1\SeoChecker\Rules\ViewportRule;
use Aratornv1\SeoChecker\Tests\Support\RunsRules;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ViewportRuleTest extends TestCase
{
    use RunsRules;

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function viewportProvider(): iterable
    {
        $page = static fn (string $meta): string => "<html><head>{$meta}</head></html>";

        yield 'missing' => [$page(''), 'error'];
        yield 'standard' => [$page('<meta name="viewport" content="width=device-width, initial-scale=1">'), 'success'];
        yield 'name is case-insensitive' => [$page('<meta name="Viewport" content="width=device-width">'), 'success'];
        yield 'extra spaces and caps' => [$page('<meta name="viewport" content=" Width = Device-Width ,initial-scale=1">'), 'success'];
        yield 'fixed width' => [$page('<meta name="viewport" content="width=1024">'), 'warning'];
        yield 'no width at all' => [$page('<meta name="viewport" content="initial-scale=1">'), 'warning'];
        yield 'empty content' => [$page('<meta name="viewport" content="">'), 'warning'];
        yield 'zoom disabled via user-scalable' => [
            $page('<meta name="viewport" content="width=device-width, user-scalable=no">'),
            'warning',
        ];
        yield 'zoom disabled via maximum-scale' => [
            $page('<meta name="viewport" content="width=device-width, maximum-scale=1">'),
            'warning',
        ];
        yield 'non-numeric maximum-scale is ignored by browsers' => [
            $page('<meta name="viewport" content="width=device-width, maximum-scale=yes">'),
            'success',
        ];
        yield 'maximum-scale exactly 1.0 locks zoom' => [
            $page('<meta name="viewport" content="width=device-width, maximum-scale=1.0">'),
            'warning',
        ];
        yield 'first tag empty, second valid' => [
            $page('<meta name="viewport" content=""><meta name="viewport" content="width=device-width, initial-scale=1">'),
            'success',
        ];
        yield 'generous maximum-scale is fine' => [
            $page('<meta name="viewport" content="width=device-width, maximum-scale=5">'),
            'success',
        ];
    }

    #[DataProvider('viewportProvider')]
    public function testReturnsExpectedStatus(string $html, string $expectedStatus): void
    {
        $this->assertStatus(new ViewportRule(), $html, $expectedStatus);
    }
}
