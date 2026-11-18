<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker;

/**
 * Renders results as a readable terminal report.
 */
final class ConsoleReporter implements ReporterInterface
{
    private const ICONS = [
        'success' => '✅',
        'warning' => '⚠️ ',
        'error' => '❌',
    ];

    // Plain-text fallback for terminals that cannot display emoji.
    private const PLAIN_LABELS = [
        'success' => '[ OK ]',
        'warning' => '[WARN]',
        'error' => '[FAIL]',
    ];

    private const COLORS = [
        'success' => "\033[32m",
        'warning' => "\033[33m",
        'error' => "\033[31m",
    ];

    private const COLOR_RESET = "\033[0m";

    public function __construct(
        private readonly bool $useEmoji = true,
        private readonly bool $useColor = false,
    ) {
    }

    public function render(string $url, array $results): string
    {
        $lines = ['', 'SEO-аудит: ' . $url, ''];

        $nameWidth = 0;
        foreach ($results as $result) {
            $nameWidth = max($nameWidth, mb_strlen($result->ruleName));
        }

        $counts = [Status::Success->value => 0, Status::Warning->value => 0, Status::Error->value => 0];

        foreach ($results as $result) {
            $counts[$result->status->value]++;

            $lines[] = sprintf(
                ' %s %s  %s',
                $this->marker($result->status),
                $this->pad($result->ruleName, $nameWidth),
                $result->message,
            );
        }

        $lines[] = '';
        $lines[] = sprintf(
            'Підсумок: %d %s · %d %s · %d %s',
            $counts['success'],
            $this->summaryLabel(Status::Success),
            $counts['warning'],
            $this->summaryLabel(Status::Warning),
            $counts['error'],
            $this->summaryLabel(Status::Error),
        );
        $lines[] = '';

        return implode(PHP_EOL, $lines);
    }

    private function marker(Status $status): string
    {
        $marker = $this->useEmoji ? self::ICONS[$status->value] : self::PLAIN_LABELS[$status->value];

        return $this->colorize($marker, $status);
    }

    private function summaryLabel(Status $status): string
    {
        return $this->useEmoji ? trim(self::ICONS[$status->value]) : strtolower($status->value);
    }

    private function colorize(string $text, Status $status): string
    {
        if (!$this->useColor) {
            return $text;
        }

        return self::COLORS[$status->value] . $text . self::COLOR_RESET;
    }

    /**
     * Multibyte-safe right padding (str_pad counts bytes, not characters).
     */
    private function pad(string $text, int $width): string
    {
        return $text . str_repeat(' ', max(0, $width - mb_strlen($text)));
    }
}
