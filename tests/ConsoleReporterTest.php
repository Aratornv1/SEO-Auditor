<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker\Tests;

use Aratornv1\SeoChecker\ConsoleReporter;
use Aratornv1\SeoChecker\Result;
use Aratornv1\SeoChecker\Status;
use PHPUnit\Framework\TestCase;

final class ConsoleReporterTest extends TestCase
{
    public function testRendersPlainLabelsAndSummary(): void
    {
        $output = (new ConsoleReporter(useEmoji: false))->render('https://example.com', [
            new Result('Title', Status::Success, 'fine'),
            new Result('H1', Status::Error, 'missing'),
            new Result('Meta description', Status::Warning, 'short'),
        ]);

        self::assertStringContainsString('https://example.com', $output);
        self::assertStringContainsString('[ OK ] Title', $output);
        self::assertStringContainsString('[FAIL] H1', $output);
        self::assertStringContainsString('[WARN] Meta description', $output);
        self::assertStringContainsString('1 success · 1 warning · 1 error', $output);
    }

    public function testRendersEmoji(): void
    {
        $output = (new ConsoleReporter(useEmoji: true))->render('https://example.com', [
            new Result('Title', Status::Success, 'fine'),
        ]);

        self::assertStringContainsString('✅', $output);
    }

    public function testColorsAreOptIn(): void
    {
        $results = [new Result('Title', Status::Error, 'missing')];

        self::assertStringNotContainsString("\033[", (new ConsoleReporter(false, false))->render('u', $results));
        self::assertStringContainsString("\033[31m", (new ConsoleReporter(false, true))->render('u', $results));
    }
}
