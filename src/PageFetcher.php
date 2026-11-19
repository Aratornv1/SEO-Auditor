<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker;

use Aratornv1\SeoChecker\Exception\FetchException;
use Aratornv1\SeoChecker\Support\Charset;
use Aratornv1\SeoChecker\Support\HttpHeaders;

/**
 * Downloads page HTML over HTTP(S) and returns it as UTF-8.
 *
 * Uses cURL when the extension is available and falls back to PHP streams
 * otherwise. Both transports go through the same validation, so callers get
 * identical behaviour: http(s) only, redirects followed, a total time limit,
 * a size limit, an HTML content-type check and a FetchException for every
 * failure.
 */
final class PageFetcher implements HtmlProviderInterface
{
    /**
     * Default response size limit: 2 MiB. Real pages are well below this
     * (typically under 700 KB), while memory use grows with page size
     * (roughly 30x the HTML size once parsed into a DOM), so the cap keeps
     * the worst case bounded.
     */
    public const DEFAULT_MAX_BYTES = 2 * 1024 * 1024;

    private const MAX_REDIRECTS = 5;

    // Chunk size for the streams transport; the deadline is checked between chunks.
    private const READ_CHUNK = 8192;

    /**
     * @param int    $timeoutSeconds total time allowed for one request (both transports)
     * @param int    $maxBytes       responses larger than this are rejected
     * @param bool   $preferCurl     set to false to force the streams transport
     */
    public function __construct(
        private readonly int $timeoutSeconds = 10,
        private readonly int $maxBytes = self::DEFAULT_MAX_BYTES,
        private readonly string $userAgent = 'seo-checker/1.0 (+https://github.com/Aratornv1/SEO-Auditor)',
        private readonly bool $preferCurl = true,
    ) {
    }

    public function fetch(string $url): string
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (!is_string($scheme) || !in_array(strtolower($scheme), ['http', 'https'], true)) {
            throw new FetchException(sprintf('Непідтримувана схема URL: %s (потрібно http або https)', $url));
        }

        if (parse_url($url, PHP_URL_HOST) === null) {
            throw new FetchException(sprintf('Некоректний URL: %s', $url));
        }

        $response = $this->preferCurl && extension_loaded('curl')
            ? $this->requestWithCurl($url)
            : $this->requestWithStreams($url);

        $body = $this->validate($url, $response);

        // The DOM loader expects UTF-8, so pages in other encodings are converted here.
        return Charset::toUtf8($body, $response['contentType']);
    }

    /**
     * @return array{body: string, status: int, contentType: string}
     */
    private function requestWithCurl(string $url): array
    {
        $body = '';
        $tooLarge = false;
        $maxBytes = $this->maxBytes;

        $handle = curl_init($url);
        if ($handle === false) {
            throw new FetchException('Не вдалося ініціалізувати cURL');
        }

        curl_setopt_array($handle, [
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => self::MAX_REDIRECTS,
            CURLOPT_CONNECTTIMEOUT => min(5, $this->timeoutSeconds),
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_USERAGENT => $this->userAgent,
            // An empty value lets cURL negotiate and transparently decode gzip/deflate/br.
            CURLOPT_ENCODING => '',
            CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml'],
            // Never follow a redirect into file://, ftp:// and friends.
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            // Collect the body ourselves so an oversized response can be aborted early.
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$body, &$tooLarge, $maxBytes): int {
                $body .= $chunk;

                if (strlen($body) > $maxBytes) {
                    $tooLarge = true;

                    // Returning a length different from the chunk size aborts the transfer.
                    return 0;
                }

                return strlen($chunk);
            },
        ]);

        // PHP for Windows ships without a CA bundle (curl.cainfo is empty), so
        // HTTPS would fail verification. Use the Windows certificate store
        // instead. Verification itself stays enabled.
        if (PHP_OS_FAMILY === 'Windows' && defined('CURLSSLOPT_NATIVE_CA') && ini_get('curl.cainfo') === '') {
            curl_setopt($handle, CURLOPT_SSL_OPTIONS, CURLSSLOPT_NATIVE_CA);
        }

        $ok = curl_exec($handle);

        if ($tooLarge) {
            throw $this->tooLarge($url);
        }

        if ($ok === false) {
            throw new FetchException(sprintf('Не вдалося завантажити %s: %s', $url, curl_error($handle)));
        }

        return [
            'body' => $body,
            'status' => (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE),
            'contentType' => (string) curl_getinfo($handle, CURLINFO_CONTENT_TYPE),
        ];
    }

    /**
     * @return array{body: string, status: int, contentType: string}
     */
    private function requestWithStreams(string $url): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $this->timeoutSeconds,
                'follow_location' => 1,
                'max_redirects' => self::MAX_REDIRECTS,
                'user_agent' => $this->userAgent,
                // Read the body even on 4xx/5xx so the status can be reported.
                'ignore_errors' => true,
                'header' => "Accept: text/html,application/xhtml+xml\r\n",
            ],
        ]);

        // The "timeout" option above is an idle timeout per read, not a total
        // one, so a total deadline is enforced manually in the read loop.
        $deadline = microtime(true) + $this->timeoutSeconds;

        $stream = @fopen($url, 'rb', false, $context);
        if ($stream === false) {
            $reason = error_get_last()['message'] ?? 'невідома помилка';
            throw new FetchException(sprintf('Не вдалося завантажити %s: %s', $url, $reason));
        }

        $body = '';

        try {
            while (!feof($stream)) {
                $remaining = $deadline - microtime(true);
                if ($remaining <= 0) {
                    throw $this->timedOut($url);
                }

                // Never wait on a single read longer than what is left of the total budget.
                stream_set_timeout($stream, max(1, (int) ceil($remaining)));

                $chunk = fread($stream, self::READ_CHUNK);

                if ($chunk === false) {
                    throw new FetchException(sprintf('Не вдалося прочитати відповідь від %s', $url));
                }

                // The "timed_out" flag alone is unreliable: PHP also raises it on
                // reads that returned data instantly. A real idle timeout is an
                // empty read together with the flag.
                if ($chunk === '' && (stream_get_meta_data($stream)['timed_out'] ?? false) === true) {
                    throw $this->timedOut($url);
                }

                $body .= $chunk;

                if (strlen($body) > $this->maxBytes) {
                    throw $this->tooLarge($url);
                }
            }

            $meta = stream_get_meta_data($stream);
        } finally {
            fclose($stream);
        }

        $headers = array_values(array_filter((array) ($meta['wrapper_data'] ?? []), 'is_string'));

        return [
            'body' => $body,
            'status' => HttpHeaders::statusCode($headers),
            'contentType' => HttpHeaders::value($headers, 'Content-Type'),
        ];
    }

    private function tooLarge(string $url): FetchException
    {
        return new FetchException(sprintf(
            'Відповідь від %s перевищує ліміт розміру (%s МБ)',
            $url,
            rtrim(rtrim(number_format($this->maxBytes / 1048576, 2, '.', ''), '0'), '.'),
        ));
    }

    private function timedOut(string $url): FetchException
    {
        return new FetchException(sprintf('Час очікування відповіді від %s вичерпано (%d с)', $url, $this->timeoutSeconds));
    }

    /**
     * @param array{body: string, status: int, contentType: string} $response
     */
    private function validate(string $url, array $response): string
    {
        $status = $response['status'];

        // Anything but 2xx is a failure: 4xx/5xx are errors, and a lingering 3xx
        // means redirects ended without reaching a page. Status 0 means the
        // response had no readable status line.
        if ($status < 200 || $status >= 300) {
            throw new FetchException($status === 0
                ? sprintf('Сервер повернув некоректну відповідь для %s', $url)
                : sprintf('Сервер відповів статусом HTTP %d для %s', $status, $url));
        }

        // A missing Content-Type is tolerated (some servers omit it). Only real
        // web pages are accepted: feeds, SVG and other XML types are not.
        if ($response['contentType'] !== ''
            && preg_match('#^\s*(text/html|application/xhtml\+xml)\s*(;|$)#i', $response['contentType']) !== 1
        ) {
            throw new FetchException(sprintf(
                'За адресою %s не HTML-сторінка (Content-Type: %s)',
                $url,
                $response['contentType'],
            ));
        }

        if (trim($response['body']) === '') {
            throw new FetchException(sprintf('Порожня відповідь від %s', $url));
        }

        return $response['body'];
    }
}
