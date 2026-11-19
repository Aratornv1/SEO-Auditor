<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker\Rules;

use Aratornv1\SeoChecker\Outcome;
use Aratornv1\SeoChecker\RuleInterface;
use DOMXPath;

/**
 * Checks that <html> declares the page language through a valid "lang" value.
 *
 * The language helps search engines serve the page to the right audience and
 * is required by screen readers and browser translation features.
 */
final class HtmlLangRule implements RuleInterface
{
    // Simplified BCP 47 shape: primary subtag plus optional subtags (uk, en-US, zh-Hant-TW).
    private const LANG_PATTERN = '/^[a-z]{2,3}(-[a-z0-9]{2,8})*$/i';

    public function getName(): string
    {
        return 'HTML lang';
    }

    public function check(DOMXPath $xpath): array
    {
        $lang = trim((string) $xpath->evaluate('string(/html/@lang)'));

        if ($lang === '') {
            return Outcome::warning('Атрибут lang у тегу <html> відсутній або порожній');
        }

        if (preg_match(self::LANG_PATTERN, $lang) !== 1) {
            return Outcome::warning(sprintf('Некоректне значення lang="%s" (очікується, наприклад, "uk" або "en-US")', $lang));
        }

        return Outcome::success(sprintf('Мову сторінки задано: lang="%s"', $lang));
    }
}
