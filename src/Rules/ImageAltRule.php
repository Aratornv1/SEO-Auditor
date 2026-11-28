<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker\Rules;

use Aratornv1\SeoChecker\Outcome;
use Aratornv1\SeoChecker\RuleInterface;
use Aratornv1\SeoChecker\Support\XPathFunctions;
use DOMXPath;

/**
 * Checks that images carry an "alt" attribute.
 *
 * The alt text is indexed for image search and read aloud by screen readers.
 * An empty alt="" is deliberately accepted: it is the correct way to mark a
 * purely decorative image. Only a missing attribute is reported.
 */
final class ImageAltRule implements RuleInterface
{
    /**
     * Share of images without alt from which the result becomes an error
     * instead of a warning.
     */
    private const ERROR_RATIO = 0.5;

    public function getName(): string
    {
        return 'Image alt';
    }

    public function check(DOMXPath $xpath): Outcome
    {
        $total = XPathFunctions::count($xpath, '//img');

        if ($total === 0) {
            return Outcome::success('The page has no images');
        }

        $missing = XPathFunctions::count($xpath, '//img[not(@alt)]');

        if ($missing === 0) {
            $decorative = XPathFunctions::count($xpath, '//img[normalize-space(@alt) = ""]');

            return Outcome::success(sprintf(
                'All images have an alt attribute (total: %d, decorative with an empty alt: %d)',
                $total,
                $decorative,
            ));
        }

        $message = sprintf('Images without an alt attribute: %d of %d', $missing, $total);

        return $missing / $total >= self::ERROR_RATIO
            ? Outcome::error($message)
            : Outcome::warning($message);
    }
}
