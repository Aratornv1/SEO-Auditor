<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker\Rules;

use Aratornv1\SeoChecker\Outcome;
use Aratornv1\SeoChecker\RuleInterface;
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

    public function check(DOMXPath $xpath): array
    {
        $total = (int) $xpath->evaluate('count(//img)');

        if ($total === 0) {
            return Outcome::success('Зображень на сторінці немає');
        }

        $missing = (int) $xpath->evaluate('count(//img[not(@alt)])');

        if ($missing === 0) {
            $decorative = (int) $xpath->evaluate('count(//img[normalize-space(@alt) = ""])');

            return Outcome::success(sprintf(
                'Усі зображення мають атрибут alt (усього: %d, декоративних із порожнім alt: %d)',
                $total,
                $decorative,
            ));
        }

        $message = sprintf('Зображень без атрибута alt: %d із %d', $missing, $total);

        return $missing / $total >= self::ERROR_RATIO
            ? Outcome::error($message)
            : Outcome::warning($message);
    }
}
