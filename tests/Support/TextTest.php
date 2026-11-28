<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker\Tests\Support;

use Aratornv1\SeoChecker\Support\Text;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TextTest extends TestCase
{
    public function testNormalizeCollapsesWhitespace(): void
    {
        self::assertSame('a b c', Text::normalize("  a \n\t b   c  "));
        self::assertSame('', Text::normalize(" \n "));
    }

    public function testLengthCountsCharactersNotBytes(): void
    {
        self::assertSame(6, Text::length('Привіт'));
    }

    /**
     * @return iterable<int, array{int, string}>
     */
    public static function charactersProvider(): iterable
    {
        yield [0, '0 characters'];
        yield [1, '1 character'];
        yield [2, '2 characters'];
        yield [11, '11 characters'];
        yield [21, '21 characters'];
        yield [101, '101 characters'];
    }

    #[DataProvider('charactersProvider')]
    public function testCharactersUsesCorrectNounForm(int $count, string $expected): void
    {
        self::assertSame($expected, Text::characters($count));
    }
}
