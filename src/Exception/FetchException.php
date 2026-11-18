<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker\Exception;

use RuntimeException;

/**
 * The page HTML could not be obtained (network error, HTTP status, empty body).
 */
final class FetchException extends RuntimeException
{
}
