<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker\Rules;

use Aratornv1\SeoChecker\Outcome;
use Aratornv1\SeoChecker\RuleInterface;
use Aratornv1\SeoChecker\Support\Text;
use Aratornv1\SeoChecker\Support\XPathFunctions;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Checks that the page has exactly one meaningful <h1>.
 *
 * The <h1> states the main topic of the page for search engines and screen
 * readers. A missing or duplicated <h1> blurs that signal. A heading that
 * contains only an image is meaningful when the image has an alt text.
 */
final class H1Rule implements RuleInterface
{
    private const PREVIEW_LENGTH = 50;

    public function getName(): string
    {
        return 'H1';
    }

    public function check(DOMXPath $xpath): Outcome
    {
        // h1 may sit anywhere in the document (header, main, article).
        $headings = $xpath->query('//h1');

        if ($headings === false || $headings->length === 0) {
            return Outcome::error('The page has no <h1>');
        }

        $names = [];
        foreach ($headings as $heading) {
            if (!$heading instanceof DOMElement) {
                continue;
            }

            $name = $this->accessibleName($xpath, $heading);
            if ($name !== '') {
                $names[] = $name;
            }
        }

        // Every <h1> is empty: there is no usable main heading at all.
        if ($names === []) {
            return Outcome::error('The <h1> tag is empty (no text and no alt on an image inside it)');
        }

        if ($headings->length > 1) {
            return Outcome::warning(sprintf(
                'Found %d %s, there should be only one',
                $headings->length,
                Text::plural($headings->length, '<h1> tag', '<h1> tags'),
            ));
        }

        return Outcome::success(sprintf('One <h1>: "%s"', mb_strimwidth($names[0], 0, self::PREVIEW_LENGTH, '…')));
    }

    /**
     * The text of the heading, or the alt of an image inside it when the
     * heading has no text of its own (a common pattern for logo headings).
     */
    private function accessibleName(DOMXPath $xpath, DOMNode $heading): string
    {
        $text = Text::normalize($heading->textContent);

        if ($text !== '') {
            return $text;
        }

        return Text::normalize(XPathFunctions::string($xpath, './/img/@alt', $heading));
    }
}
