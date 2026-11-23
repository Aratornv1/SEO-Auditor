<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker\Rules;

use Aratornv1\SeoChecker\Outcome;
use Aratornv1\SeoChecker\RuleInterface;
use Aratornv1\SeoChecker\Support\Text;
use DOMXPath;

/**
 * Checks that the page has a single, non-empty <title> of a sensible length.
 *
 * The title is one of the strongest ranking signals and is shown as the
 * headline of the search snippet. Titles that are too long get truncated,
 * titles that are too short do not describe the page.
 */
final class TitleRule implements RuleInterface
{
    /**
     * Thresholds are injected so the rule stays configurable without
     * modification (Open/Closed Principle).
     */
    public function __construct(
        private readonly int $minLength = 30,
        private readonly int $maxLength = 60,
    ) {
    }

    public function getName(): string
    {
        return 'Title';
    }

    public function check(DOMXPath $xpath): Outcome
    {
        $titles = $xpath->query('//head/title');

        if ($titles === false || $titles->length === 0) {
            return Outcome::error('Тег <title> відсутній');
        }

        $filled = [];
        foreach ($titles as $title) {
            $text = Text::normalize($title->textContent);
            if ($text !== '') {
                $filled[] = $text;
            }
        }

        // Every <title> is empty.
        if ($filled === []) {
            return Outcome::error('Тег <title> порожній');
        }

        if ($titles->length > 1) {
            return Outcome::warning(sprintf('Знайдено кілька тегів <title> (%d), має бути один', $titles->length));
        }

        $text = $filled[0];
        $length = Text::length($text);

        if ($length < $this->minLength) {
            return Outcome::warning(sprintf(
                'Заголовок занадто короткий (%s, рекомендовано %d–%d)',
                Text::characters($length),
                $this->minLength,
                $this->maxLength,
            ));
        }

        if ($length > $this->maxLength) {
            return Outcome::warning(sprintf(
                'Заголовок занадто довгий (%s, рекомендовано %d–%d)',
                Text::characters($length),
                $this->minLength,
                $this->maxLength,
            ));
        }

        return Outcome::success(sprintf('Заголовок має оптимальну довжину (%s)', Text::characters($length)));
    }
}
