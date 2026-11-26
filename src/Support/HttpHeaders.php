<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker\Support;

/**
 * Reads raw HTTP header lines as produced by the PHP streams wrapper.
 *
 * When redirects are followed, the wrapper returns the header lines of every
 * hop one after another. Only the last response is relevant, otherwise a
 * header from an earlier hop (e.g. Content-Type of a 301) could be mistaken
 * for a header of the final page.
 */
final class HttpHeaders
{
    private function __construct()
    {
    }

    /**
     * Returns the header lines that belong to the final response
     * (from the last status line to the end).
     *
     * @param list<string> $lines
     *
     * @return list<string>
     */
    public static function finalResponse(array $lines): array
    {
        $start = 0;

        foreach ($lines as $index => $line) {
            if (preg_match('#^HTTP/\S+\s#', $line) === 1) {
                $start = $index;
            }
        }

        return array_slice($lines, $start);
    }

    /**
     * Status code of the final response, or 0 when there is no status line.
     *
     * @param list<string> $lines
     */
    public static function statusCode(array $lines): int
    {
        foreach (self::finalResponse($lines) as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $matches) === 1) {
                return (int) $matches[1];
            }
        }

        return 0;
    }

    /**
     * Value of a header in the final response, or '' when it is absent.
     *
     * @param list<string> $lines
     */
    public static function value(array $lines, string $name): string
    {
        $prefix = strtolower($name) . ':';
        $value = '';

        foreach (self::finalResponse($lines) as $line) {
            if (str_starts_with(strtolower($line), $prefix)) {
                $value = trim(substr($line, strlen($prefix)));
            }
        }

        return $value;
    }
}
