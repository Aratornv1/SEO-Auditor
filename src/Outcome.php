<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker;

/**
 * Named constructors for the array a rule returns, so rules never repeat
 * status strings and a typo cannot produce an invalid result.
 */
final class Outcome
{
    private function __construct()
    {
    }

    /**
     * @return array{status: 'success', message: string}
     */
    public static function success(string $message): array
    {
        return ['status' => Status::Success->value, 'message' => $message];
    }

    /**
     * @return array{status: 'warning', message: string}
     */
    public static function warning(string $message): array
    {
        return ['status' => Status::Warning->value, 'message' => $message];
    }

    /**
     * @return array{status: 'error', message: string}
     */
    public static function error(string $message): array
    {
        return ['status' => Status::Error->value, 'message' => $message];
    }
}
