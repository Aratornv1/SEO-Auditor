<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker;

/**
 * Allowed statuses of a rule result.
 *
 * Rules return plain string values (see RuleInterface); Analyzer converts them
 * into this enum and rejects anything else.
 */
enum Status: string
{
    case Success = 'success';
    case Warning = 'warning';
    case Error = 'error';
}
