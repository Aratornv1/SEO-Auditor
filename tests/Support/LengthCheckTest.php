<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker\Tests\Support;

use Aratornv1\SeoChecker\Status;
use Aratornv1\SeoChecker\Support\LengthCheck;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LengthCheckTest extends TestCase
{
    /**
     * @return iterable<string, array{string, Status}>
     */
    public static function lengthProvider(): iterable
    {
        yield 'below minimum' => [str_repeat('a', 4), Status::Warning];
        yield 'exactly minimum' => [str_repeat('a', 5), Status::Success];
        yield 'exactly maximum' => [str_repeat('a', 10), Status::Success];
        yield 'above maximum' => [str_repeat('a', 11), Status::Warning];
        // 10 Cyrillic letters are 20 bytes: must still count as 10 characters.
        yield 'cyrillic counted in characters' => ['Привітання', Status::Success];
    }

    #[DataProvider('lengthProvider')]
    public function testBoundariesAreInclusive(string $text, Status $expected): void
    {
        self::assertSame($expected, LengthCheck::outcome($text, 5, 10, 'Текст')->status);
    }

    public function testMessagesNameTheSubjectAndTheRange(): void
    {
        self::assertSame(
            'Текст занадто короткий (2 символи, рекомендовано 5–10)',
            LengthCheck::outcome('ab', 5, 10, 'Текст')->message,
        );
        self::assertSame(
            'Текст має оптимальну довжину (6 символів)',
            LengthCheck::outcome('abcdef', 5, 10, 'Текст')->message,
        );
    }

    public function testNoteIsAppendedOnlyToTheTooLongMessage(): void
    {
        $long = LengthCheck::outcome(str_repeat('a', 21), 5, 10, 'Опис', ', пошуковик його обріже');
        $short = LengthCheck::outcome('ab', 5, 10, 'Опис', ', пошуковик його обріже');

        self::assertSame('Опис занадто довгий (21 символ, рекомендовано 5–10), пошуковик його обріже', $long->message);
        self::assertStringNotContainsString('обріже', $short->message);
    }
}
