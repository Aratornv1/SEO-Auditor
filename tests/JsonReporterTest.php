<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker\Tests;

use Aratornv1\SeoChecker\JsonReporter;
use Aratornv1\SeoChecker\Result;
use Aratornv1\SeoChecker\Status;
use PHPUnit\Framework\TestCase;

final class JsonReporterTest extends TestCase
{
    public function testRendersUrlAndResultsAsOneLine(): void
    {
        $output = (new JsonReporter())->render('https://example.com/a', [
            new Result('Title', Status::Success, 'fine'),
            new Result('H1', Status::Error, 'missing'),
        ]);

        self::assertStringEndsWith(PHP_EOL, $output);
        self::assertSame(1, substr_count($output, PHP_EOL), 'One JSON object per line.');
        self::assertSame([
            'url' => 'https://example.com/a',
            'results' => [
                ['rule' => 'Title', 'status' => 'success', 'message' => 'fine'],
                ['rule' => 'H1', 'status' => 'error', 'message' => 'missing'],
            ],
        ], json_decode($output, true, flags: JSON_THROW_ON_ERROR));
    }

    public function testKeepsCyrillicAndSlashesReadable(): void
    {
        $output = (new JsonReporter())->render('https://example.com/a', [
            new Result('Title', Status::Warning, 'Заголовок занадто короткий'),
        ]);

        self::assertStringContainsString('Заголовок занадто короткий', $output);
        self::assertStringContainsString('https://example.com/a', $output);
    }

    public function testRendersDownloadError(): void
    {
        $output = (new JsonReporter())->renderError('https://example.com', 'The server responded with HTTP status 404');

        self::assertSame(
            ['url' => 'https://example.com', 'error' => 'The server responded with HTTP status 404'],
            json_decode($output, true, flags: JSON_THROW_ON_ERROR),
        );
    }

    public function testEmptyResultListIsAnEmptyArray(): void
    {
        $output = (new JsonReporter())->render('https://example.com', []);

        self::assertStringContainsString('"results":[]', $output);
    }
}
