<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker;

use DOMDocument;
use DOMXPath;

/**
 * Single responsibility: turn an HTML string into a DOMXPath.
 */
final class XPathFactory
{
    /**
     * @param string $html page markup, expected to be UTF-8 (PageFetcher guarantees it)
     */
    public function create(string $html): DOMXPath
    {
        $document = new DOMDocument();

        // Real-world HTML is almost never valid: collect libxml warnings
        // internally so they do not leak into the console.
        $previous = libxml_use_internal_errors(true);

        try {
            // loadHTML() throws on an empty string; an empty page simply has no elements.
            $document->loadHTML($html === '' ? '<html></html>' : $this->toAsciiEntities($html));
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return new DOMXPath($document);
    }

    /**
     * Replaces every non-ASCII character with a numeric entity (&#1055;).
     *
     * libxml guesses the document encoding itself and gives <meta charset>
     * priority over anything a caller declares, so a UTF-8 page that still
     * says charset=windows-1251 would be misread. Pure-ASCII input removes
     * the guesswork: entities always decode to the right Unicode characters.
     */
    private function toAsciiEntities(string $html): string
    {
        return mb_encode_numericentity($html, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8');
    }
}
