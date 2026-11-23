<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker;

/**
 * Allowed statuses of a rule result.
 *
 * A rule picks one of them through Outcome; Analyzer copies it into the Result.
 */
enum Status: string
{
    case Success = 'success';
    case Warning = 'warning';
    case Error = 'error';
}
