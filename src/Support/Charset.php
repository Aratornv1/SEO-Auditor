<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker\Support;

use ValueError;

/**
 * Converts a downloaded page body to UTF-8.
 *
 * The DOM loader treats its input as UTF-8, so a page served in another
 * encoding (windows-1251, KOI8-U, ISO-8859-x ...) would come out as garbage
 * and break every length-based check. The encoding is taken, in order, from
 * a byte order mark, the HTTP Content-Type header and a <meta charset> tag.
 * Without any declaration, valid UTF-8 is kept as is and anything else is
 * read as windows-1252, which is the HTML standard's default.
 */
final class Charset
{
    // How many leading bytes are scanned for a <meta charset> declaration.
    private const SNIFF_BYTES = 4096;

    // HTML treats these labels as windows-1252 (a superset of ISO-8859-1).
    private const WINDOWS_1252_LABELS = ['iso-8859-1', 'latin1', 'l1', 'us-ascii', 'ascii', 'iso_8859-1'];

    private function __construct()
    {
    }

    public static function toUtf8(string $body, string $contentType = ''): string
    {
        if (str_starts_with($body, "\xEF\xBB\xBF")) {
            return substr($body, 3);
        }

        if (str_starts_with($body, "\xFF\xFE")) {
            return self::convert(substr($body, 2), 'UTF-16LE') ?? $body;
        }

        if (str_starts_with($body, "\xFE\xFF")) {
            return self::convert(substr($body, 2), 'UTF-16BE') ?? $body;
        }

        $charset = self::fromContentType($contentType) ?? self::fromMeta($body);

        if ($charset === null) {
            return mb_check_encoding($body, 'UTF-8') ? $body : (self::convert($body, 'Windows-1252') ?? $body);
        }

        $normalized = strtolower($charset);

        if ($normalized === 'utf-8' || $normalized === 'utf8') {
            return $body;
        }

        if (in_array($normalized, self::WINDOWS_1252_LABELS, true)) {
            $charset = 'Windows-1252';
        }

        // An unknown label leaves the body untouched rather than failing the audit.
        return self::convert($body, $charset) ?? $body;
    }

    private static function fromContentType(string $contentType): ?string
    {
        if (preg_match('/charset\s*=\s*"?([^\s;"]+)/i', $contentType, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    private static function fromMeta(string $body): ?string
    {
        $head = substr($body, 0, self::SNIFF_BYTES);

        // Covers both <meta charset="x"> and <meta http-equiv="Content-Type" content="...; charset=x">.
        if (preg_match('/<meta[^>]+charset\s*=\s*["\']?\s*([a-zA-Z0-9_:.\-]+)/i', $head, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    private static function convert(string $body, string $from): ?string
    {
        try {
            $converted = mb_convert_encoding($body, 'UTF-8', $from);

            return $converted === false ? null : $converted;
        } catch (ValueError) {
            // mbstring does not know this encoding label.
            return null;
        }
    }
}
