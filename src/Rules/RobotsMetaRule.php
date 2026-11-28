<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker\Rules;

use Aratornv1\SeoChecker\Outcome;
use Aratornv1\SeoChecker\RuleInterface;
use Aratornv1\SeoChecker\Support\MetaTagReader;
use DOMXPath;

/**
 * Checks that the page is not accidentally blocked from indexing.
 *
 * A forgotten noindex (often left over from a staging environment) removes
 * the page from search results entirely, which makes it one of the most
 * expensive SEO mistakes.
 *
 * Note: only the <meta> tag is inspected; an X-Robots-Tag HTTP header
 * cannot be seen from the HTML alone.
 */
final class RobotsMetaRule implements RuleInterface
{
    // Meta names that crawlers read for indexing directives.
    private const META_NAMES = ['robots', 'googlebot'];

    private readonly MetaTagReader $meta;

    public function __construct(?MetaTagReader $meta = null)
    {
        $this->meta = $meta ?? new MetaTagReader();
    }

    public function getName(): string
    {
        return 'Robots meta';
    }

    public function check(DOMXPath $xpath): Outcome
    {
        $directives = $this->collectDirectives($xpath);

        // "none" is a shorthand for "noindex, nofollow".
        if (in_array('noindex', $directives, true) || in_array('none', $directives, true)) {
            return Outcome::error('The page is blocked from indexing (noindex in meta robots)');
        }

        if (in_array('nofollow', $directives, true)) {
            return Outcome::warning('Links on the page are not followed (nofollow in meta robots)');
        }

        if ($directives === []) {
            return Outcome::success('No indexing restrictions (no meta robots, the default applies)');
        }

        return Outcome::success('The page can be indexed');
    }

    /**
     * @return list<string> lowercase directives from all robots-like meta tags
     */
    private function collectDirectives(DOMXPath $xpath): array
    {
        $directives = [];

        foreach (self::META_NAMES as $name) {
            foreach ($this->meta->byName($xpath, $name) as $content) {
                foreach (explode(',', strtolower($content)) as $directive) {
                    $directive = trim($directive);
                    if ($directive !== '') {
                        $directives[] = $directive;
                    }
                }
            }
        }

        return $directives;
    }
}
