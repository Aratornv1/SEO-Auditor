<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker\Support;

/**
 * Small text helpers shared by several rules.
 */
final class Text
{
    private function __construct()
    {
    }

    /**
     * Trims the text and collapses any run of whitespace (including line
     * breaks and indentation) into a single space.
     */
    public static function normalize(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /**
     * Length in characters (not bytes), so non-ASCII text is measured fairly.
     */
    public static function length(string $text): int
    {
        return mb_strlen($text);
    }

    /**
     * Picks the English noun form for a number: 1 character, 0 or 2 characters.
     */
    public static function plural(int $number, string $one, string $many): string
    {
        return abs($number) === 1 ? $one : $many;
    }

    /**
     * "1 character", "41 characters" - a number together with the right noun form.
     */
    public static function characters(int $count): string
    {
        return $count . ' ' . self::plural($count, 'character', 'characters');
    }
}
