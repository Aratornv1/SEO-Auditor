<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker;

/**
 * Renders results as one JSON object for scripts and CI:
 * {"url": "...", "results": [{"rule": "...", "status": "...", "message": "..."}]}.
 */
final class JsonReporter implements ReporterInterface
{
    // Keep Cyrillic and URLs readable; never fail on a broken byte in a message.
    private const FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR;

    public function render(string $url, array $results): string
    {
        return $this->encode([
            'url' => $url,
            'results' => array_map(
                static fn (Result $result): array => [
                    'rule' => $result->ruleName,
                    'status' => $result->status->value,
                    'message' => $result->message,
                ],
                $results,
            ),
        ]);
    }

    /**
     * The page could not be downloaded: {"url": "...", "error": "..."}.
     */
    public function renderError(string $url, string $message): string
    {
        return $this->encode(['url' => $url, 'error' => $message]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function encode(array $data): string
    {
        return json_encode($data, self::FLAGS) . PHP_EOL;
    }
}
