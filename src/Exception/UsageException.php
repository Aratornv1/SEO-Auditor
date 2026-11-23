<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker\Exception;

use RuntimeException;

/**
 * The command line arguments are invalid (unknown option, bad value, too many URLs).
 */
final class UsageException extends RuntimeException
{
    /**
     * @param string $message   what is wrong; may be empty when only the usage text is needed
     * @param bool   $showUsage whether the usage text should be printed after the message
     */
    public function __construct(string $message = '', public readonly bool $showUsage = false)
    {
        parent::__construct($message);
    }
}
