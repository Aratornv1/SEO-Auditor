<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker\Rules;

use Aratornv1\SeoChecker\RuleInterface;
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

    public function check(DOMXPath $xpath): array
    {
        $titles = $xpath->query('//head/title');

        if ($titles === false || $titles->length === 0) {
            return $this->result('error', 'Тег <title> відсутній');
        }

        $text = $this->normalize($titles->item(0)?->textContent ?? '');

        if ($text === '') {
            return $this->result('error', 'Тег <title> порожній');
        }

        if ($titles->length > 1) {
            return $this->result('warning', sprintf('Знайдено кілька тегів <title> (%d), має бути один', $titles->length));
        }

        $length = mb_strlen($text);

        if ($length < $this->minLength) {
            return $this->result('warning', sprintf(
                'Заголовок занадто короткий (%d символів, рекомендовано %d–%d)',
                $length,
                $this->minLength,
                $this->maxLength,
            ));
        }

        if ($length > $this->maxLength) {
            return $this->result('warning', sprintf(
                'Заголовок занадто довгий (%d символів, рекомендовано %d–%d)',
                $length,
                $this->minLength,
                $this->maxLength,
            ));
        }

        return $this->result('success', sprintf('Заголовок має оптимальну довжину (%d символів)', $length));
    }

    /**
     * Collapses whitespace so line breaks and indentation inside the tag
     * do not inflate the measured length.
     */
    private function normalize(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /**
     * @return array{status: string, message: string}
     */
    private function result(string $status, string $message): array
    {
        return ['status' => $status, 'message' => $message];
    }
}
