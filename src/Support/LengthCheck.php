<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker\Support;

use Aratornv1\SeoChecker\Outcome;

/**
 * Checks that a text fits a recommended length range.
 *
 * Shared by rules that measure a single text (the <title>, the meta
 * description), so the wording of the length messages stays the same.
 * Finding the text and deciding how serious a missing tag is stays in
 * each rule.
 */
final class LengthCheck
{
    private function __construct()
    {
    }

    /**
     * @param string $subject     what is measured, starts the message ("Title", "Description")
     * @param string $tooLongNote appended to the "too long" message, e.g. ", search engines will truncate it"
     */
    public static function outcome(
        string $text,
        int $minLength,
        int $maxLength,
        string $subject,
        string $tooLongNote = '',
    ): Outcome {
        $length = Text::length($text);

        if ($length < $minLength) {
            return Outcome::warning(sprintf(
                '%s is too short (%s, recommended %d–%d)',
                $subject,
                Text::characters($length),
                $minLength,
                $maxLength,
            ));
        }

        if ($length > $maxLength) {
            return Outcome::warning(sprintf(
                '%s is too long (%s, recommended %d–%d)%s',
                $subject,
                Text::characters($length),
                $minLength,
                $maxLength,
                $tooLongNote,
            ));
        }

        return Outcome::success(sprintf('%s has an optimal length (%s)', $subject, Text::characters($length)));
    }
}
