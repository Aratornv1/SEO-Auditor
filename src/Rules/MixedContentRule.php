<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker\Rules;

use Aratornv1\SeoChecker\Outcome;
use Aratornv1\SeoChecker\RuleInterface;
use Aratornv1\SeoChecker\Support\XPathFunctions;
use DOMAttr;
use DOMXPath;

/**
 * Finds resources that are loaded over plain http:// (mixed content).
 *
 * On an HTTPS page browsers block "active" resources (scripts, stylesheets,
 * frames) loaded over http://, which breaks the page, and warn about
 * "passive" ones (images, video, audio). Links (<a href>) are not resources
 * and are not checked.
 *
 * Rules see only the DOM, not the page URL, so this rule cannot tell whether
 * the page itself is served over HTTPS: any http:// resource is reported.
 */
final class MixedContentRule implements RuleInterface
{
    private const PASSIVE = [
        '//img/@src',
        '//img/@srcset',
        '//source/@src',
        '//source/@srcset',
        '//video/@src',
        '//video/@poster',
        '//audio/@src',
    ];

    private const MAX_EXAMPLES = 3;

    public function getName(): string
    {
        return 'Mixed content';
    }

    public function check(DOMXPath $xpath): Outcome
    {
        $active = $this->insecureUrls($xpath, $this->activeQueries());

        if ($active !== []) {
            return Outcome::error(sprintf(
                'Скрипти, стилі чи фрейми підключено по http:// (%s): на HTTPS-сторінці браузер їх заблокує',
                $this->examples($active),
            ));
        }

        $passive = $this->insecureUrls($xpath, self::PASSIVE);

        if ($passive !== []) {
            return Outcome::warning(sprintf(
                'Зображення чи медіа підключено по http:// (%s): на HTTPS-сторінці це змішаний контент',
                $this->examples($passive),
            ));
        }

        return Outcome::success('Усі ресурси підключено без http://');
    }

    /**
     * @return list<string>
     */
    private function activeQueries(): array
    {
        $stylesheet = sprintf(
            "//link[contains(concat(' ', normalize-space(%s), ' '), ' stylesheet ')]/@href",
            XPathFunctions::lower('@rel'),
        );

        return ['//script/@src', $stylesheet, '//iframe/@src', '//embed/@src', '//object/@data'];
    }

    /**
     * Unique http:// URLs found by the queries, in document order per query.
     *
     * @param list<string> $queries XPath expressions that select attributes
     *
     * @return list<string>
     */
    private function insecureUrls(DOMXPath $xpath, array $queries): array
    {
        $urls = [];

        foreach ($queries as $query) {
            $attributes = $xpath->query($query);
            if ($attributes === false) {
                continue;
            }

            foreach ($attributes as $attribute) {
                if (!$attribute instanceof DOMAttr) {
                    continue;
                }

                foreach ($this->candidates($attribute) as $url) {
                    if (preg_match('#^http://#i', $url) === 1) {
                        $urls[$url] = true;
                    }
                }
            }
        }

        return array_keys($urls);
    }

    /**
     * URLs in one attribute. A srcset holds several "url width" pairs
     * separated by commas; every other attribute holds a single URL.
     *
     * @return list<string>
     */
    private function candidates(DOMAttr $attribute): array
    {
        $value = trim($attribute->value);

        if ($attribute->name !== 'srcset') {
            return [$value];
        }

        $urls = [];
        foreach (explode(',', $value) as $candidate) {
            $url = strtok(trim($candidate), " \t\n");
            if ($url !== false) {
                $urls[] = $url;
            }
        }

        return $urls;
    }

    /**
     * @param list<string> $urls
     */
    private function examples(array $urls): string
    {
        $shown = implode(', ', array_slice($urls, 0, self::MAX_EXAMPLES));
        $rest = count($urls) - self::MAX_EXAMPLES;

        return $rest > 0 ? sprintf('%s і ще %d', $shown, $rest) : $shown;
    }
}
