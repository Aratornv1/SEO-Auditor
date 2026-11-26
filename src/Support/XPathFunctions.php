<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker\Support;

use DOMNode;
use DOMXPath;

/**
 * Helpers for building XPath 1.0 expressions.
 *
 * XPath 1.0 has no lower-case() function, so case-insensitive comparison
 * of attribute values is done with translate(). Keeping the idiom in one
 * place means a fix reaches every rule that uses it.
 */
final class XPathFunctions
{
    private const UPPER = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    private const LOWER = 'abcdefghijklmnopqrstuvwxyz';

    private function __construct()
    {
    }

    /**
     * Wraps an XPath expression (e.g. "@rel") into an ASCII lower-casing call.
     */
    public static function lower(string $expression): string
    {
        return sprintf("translate(%s, '%s', '%s')", $expression, self::UPPER, self::LOWER);
    }

    /**
     * Number of nodes matched by the expression: count(//img) as an int.
     *
     * DOMXPath::evaluate() is typed as mixed, so the result is checked
     * instead of being cast blindly.
     */
    public static function count(DOMXPath $xpath, string $expression): int
    {
        $value = $xpath->evaluate('count(' . $expression . ')');

        return is_float($value) || is_int($value) ? (int) $value : 0;
    }

    /**
     * String value of the first matched node, or '' when nothing matches.
     * With a context node the expression is relative to it (".//img/@alt").
     */
    public static function string(DOMXPath $xpath, string $expression, ?DOMNode $context = null): string
    {
        $value = $xpath->evaluate('string(' . $expression . ')', $context);

        return is_string($value) ? $value : '';
    }
}
