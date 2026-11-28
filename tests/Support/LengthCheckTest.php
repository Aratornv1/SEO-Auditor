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
        self::assertSame($expected, LengthCheck::outcome($text, 5, 10, 'Text')->status);
    }

    public function testMessagesNameTheSubjectAndTheRange(): void
    {
        self::assertSame(
            'Text is too short (2 characters, recommended 5–10)',
            LengthCheck::outcome('ab', 5, 10, 'Text')->message,
        );
        self::assertSame(
            'Text has an optimal length (6 characters)',
            LengthCheck::outcome('abcdef', 5, 10, 'Text')->message,
        );
    }

    public function testNoteIsAppendedOnlyToTheTooLongMessage(): void
    {
        $long = LengthCheck::outcome(str_repeat('a', 21), 5, 10, 'Description', ', search engines will truncate it');
        $short = LengthCheck::outcome('ab', 5, 10, 'Description', ', search engines will truncate it');

        self::assertSame('Description is too long (21 characters, recommended 5–10), search engines will truncate it', $long->message);
        self::assertStringNotContainsString('truncate', $short->message);
    }
}
