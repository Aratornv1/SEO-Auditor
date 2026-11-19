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
     * Picks the correct Ukrainian noun form for a number:
     * 1 символ, 2 символи, 5 символів (also 21 символ, 12 символів).
     */
    public static function plural(int $number, string $one, string $few, string $many): string
    {
        $abs = abs($number);
        $lastTwo = $abs % 100;
        $last = $abs % 10;

        if ($lastTwo >= 11 && $lastTwo <= 14) {
            return $many;
        }

        return match (true) {
            $last === 1 => $one,
            $last >= 2 && $last <= 4 => $few,
            default => $many,
        };
    }

    /**
     * "41 символ", "102 символи" - a number together with the right noun form.
     */
    public static function characters(int $count): string
    {
        return $count . ' ' . self::plural($count, 'символ', 'символи', 'символів');
    }
}
