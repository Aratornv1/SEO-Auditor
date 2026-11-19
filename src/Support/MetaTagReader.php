<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker\Support;

use DOMElement;
use DOMXPath;
use InvalidArgumentException;

/**
 * Reads <meta> tags by their "name" or "property" attribute.
 *
 * HTML attribute values are case-insensitive for these keys in practice
 * (<meta name="Description">), so the lookup is case-insensitive too.
 */
final class MetaTagReader
{
    /**
     * Returns the raw "content" of every <meta name="..."> with that name,
     * in document order. A tag without "content" yields an empty string.
     *
     * @return list<string>
     */
    public function byName(DOMXPath $xpath, string $name): array
    {
        return $this->read($xpath, 'name', $name);
    }

    /**
     * Same as byName() but for the "property" attribute (Open Graph).
     *
     * @return list<string>
     */
    public function byProperty(DOMXPath $xpath, string $property): array
    {
        return $this->read($xpath, 'property', $property);
    }

    /**
     * @return list<string>
     */
    private function read(DOMXPath $xpath, string $attribute, string $key): array
    {
        // The key is interpolated into an XPath literal, so only a safe
        // character set is accepted (rules pass constants, never user input).
        if (preg_match('/^[a-z0-9:_-]+$/', $key) !== 1) {
            throw new InvalidArgumentException(sprintf('Unsupported meta key "%s".', $key));
        }

        $query = sprintf("//meta[%s = '%s']", XPathFunctions::lower('@' . $attribute), $key);

        $nodes = $xpath->query($query);
        if ($nodes === false) {
            return [];
        }

        $contents = [];
        foreach ($nodes as $node) {
            if ($node instanceof DOMElement) {
                $contents[] = $node->getAttribute('content');
            }
        }

        return $contents;
    }
}
