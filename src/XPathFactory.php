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
    public function create(string $html): DOMXPath
    {
        $document = new DOMDocument();

        // Real-world HTML is almost never valid: collect libxml warnings
        // internally so they do not leak into the console.
        $previous = libxml_use_internal_errors(true);

        try {
            // The <?xml encoding> prefix forces libxml to read the input as
            // UTF-8; without it non-ASCII text (e.g. Cyrillic) gets garbled.
            $document->loadHTML('<?xml encoding="UTF-8">' . $html);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return new DOMXPath($document);
    }
}
