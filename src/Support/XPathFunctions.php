<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker\Support;

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
}
