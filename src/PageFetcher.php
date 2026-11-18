<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker;

use Aratornv1\SeoChecker\Exception\FetchException;

/**
 * Downloads page HTML over HTTP(S).
 *
 * Minimal stream-based implementation; it will be hardened later
 * (cURL, better error reporting) behind the same HtmlProviderInterface.
 */
final class PageFetcher implements HtmlProviderInterface
{
    public function __construct(
        private readonly int $timeoutSeconds = 10,
        private readonly string $userAgent = 'seo-checker/1.0 (+https://github.com/Aratornv1/SEO-Auditor)',
    ) {
    }

    public function fetch(string $url): string
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (!is_string($scheme) || !in_array(strtolower($scheme), ['http', 'https'], true)) {
            throw new FetchException(sprintf('Непідтримувана схема URL: %s (потрібно http або https)', $url));
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $this->timeoutSeconds,
                'follow_location' => 1,
                'max_redirects' => 5,
                'user_agent' => $this->userAgent,
                // Read the body even on 4xx/5xx so the status can be reported.
                'ignore_errors' => true,
                'header' => "Accept: text/html,application/xhtml+xml\r\n",
            ],
        ]);

        // Suppress the PHP warning: failures are reported via FetchException.
        $body = @file_get_contents($url, false, $context);

        if ($body === false) {
            $reason = error_get_last()['message'] ?? 'невідома помилка';
            throw new FetchException(sprintf('Не вдалося завантажити %s: %s', $url, $reason));
        }

        // $http_response_header is populated by file_get_contents in this scope.
        $status = $this->extractStatusCode($http_response_header ?? []);
        if ($status >= 400) {
            throw new FetchException(sprintf('Сервер відповів статусом HTTP %d для %s', $status, $url));
        }

        if (trim($body) === '') {
            throw new FetchException(sprintf('Порожня відповідь від %s', $url));
        }

        return $body;
    }

    /**
     * With redirects the header list contains several status lines;
     * the last one belongs to the final response.
     *
     * @param list<string> $headers
     */
    private function extractStatusCode(array $headers): int
    {
        $code = 0;

        foreach ($headers as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $matches) === 1) {
                $code = (int) $matches[1];
            }
        }

        return $code;
    }
}
