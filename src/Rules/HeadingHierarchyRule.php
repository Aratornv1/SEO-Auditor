<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker\Rules;

use Aratornv1\SeoChecker\Outcome;
use Aratornv1\SeoChecker\RuleInterface;
use DOMXPath;

/**
 * Checks that heading levels do not skip (e.g. h2 followed directly by h4).
 *
 * A logical outline helps crawlers understand how the content is organised
 * and makes the page navigable for assistive technologies.
 */
final class HeadingHierarchyRule implements RuleInterface
{
    // How many offending transitions are listed in the message.
    private const MAX_EXAMPLES = 3;

    public function getName(): string
    {
        return 'Heading hierarchy';
    }

    public function check(DOMXPath $xpath): Outcome
    {
        // A union query returns nodes in document order.
        $headings = $xpath->query('//h1 | //h2 | //h3 | //h4 | //h5 | //h6');

        if ($headings === false || $headings->length === 0) {
            return Outcome::warning('На сторінці немає жодного заголовка (h1–h6)');
        }

        $skips = [];
        $previous = null;

        foreach ($headings as $heading) {
            $level = (int) substr($heading->nodeName, 1);

            // Going deeper by more than one level is a skip. Going back up
            // to any shallower level is always fine. The first heading has
            // no predecessor; a missing h1 is reported by H1Rule.
            if ($previous !== null && $level > $previous + 1) {
                $skips[] = sprintf('h%d → h%d', $previous, $level);
            }

            $previous = $level;
        }

        if ($skips === []) {
            return Outcome::success(sprintf('Ієрархія заголовків послідовна (заголовків: %d)', $headings->length));
        }

        $shown = array_slice($skips, 0, self::MAX_EXAMPLES);
        $suffix = count($skips) > self::MAX_EXAMPLES ? sprintf(' і ще %d', count($skips) - self::MAX_EXAMPLES) : '';

        return Outcome::warning('Пропущено рівні заголовків: ' . implode(', ', $shown) . $suffix);
    }
}
