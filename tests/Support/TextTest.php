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
        yield [0, '0 символів'];
        yield [1, '1 символ'];
        yield [2, '2 символи'];
        yield [4, '4 символи'];
        yield [5, '5 символів'];
        yield [11, '11 символів'];
        yield [12, '12 символів'];
        yield [14, '14 символів'];
        yield [21, '21 символ'];
        yield [22, '22 символи'];
        yield [25, '25 символів'];
        yield [101, '101 символ'];
        yield [111, '111 символів'];
        yield [112, '112 символів'];
    }

    #[DataProvider('charactersProvider')]
    public function testCharactersUsesCorrectUkrainianForm(int $count, string $expected): void
    {
        self::assertSame($expected, Text::characters($count));
    }
}
