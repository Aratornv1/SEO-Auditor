<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker;

/**
 * What a rule returns: a status and a message, without the rule name.
 *
 * It is a typed value object, so a rule cannot return an invalid status or
 * forget the message. The named constructors keep rule code short:
 * `return Outcome::error('...')`. Analyzer adds the rule name and turns it
 * into a Result.
 */
final class Outcome
{
    public function __construct(
        public readonly Status $status,
        public readonly string $message,
    ) {
    }

    public static function success(string $message): self
    {
        return new self(Status::Success, $message);
    }

    public static function warning(string $message): self
    {
        return new self(Status::Warning, $message);
    }

    public static function error(string $message): self
    {
        return new self(Status::Error, $message);
    }
}
