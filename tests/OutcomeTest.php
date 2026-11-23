<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker\Tests;

use Aratornv1\SeoChecker\Outcome;
use Aratornv1\SeoChecker\Status;
use PHPUnit\Framework\TestCase;

final class OutcomeTest extends TestCase
{
    public function testNamedConstructorsSetTheStatus(): void
    {
        self::assertSame(Status::Success, Outcome::success('a')->status);
        self::assertSame(Status::Warning, Outcome::warning('b')->status);
        self::assertSame(Status::Error, Outcome::error('c')->status);
    }

    public function testKeepsTheMessage(): void
    {
        self::assertSame('Тег відсутній', Outcome::error('Тег відсутній')->message);
    }
}
