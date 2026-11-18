<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker;

/**
 * Immutable result of running a single rule.
 */
final class Result
{
    public function __construct(
        public readonly string $ruleName,
        public readonly Status $status,
        public readonly string $message,
    ) {
    }
}
