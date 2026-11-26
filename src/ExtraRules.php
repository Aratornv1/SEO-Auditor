<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker;

use Aratornv1\SeoChecker\Rules\MixedContentRule;
use Aratornv1\SeoChecker\Rules\WordCountRule;

/**
 * Optional rules that are not part of the standard set of ten.
 *
 * The CLI runs them with --extra-rules; in code, register them next to
 * DefaultRules. Keeping them separate leaves the standard audit unchanged.
 */
final class ExtraRules
{
    private function __construct()
    {
    }

    /**
     * @return list<RuleInterface>
     */
    public static function create(): array
    {
        return [
            new MixedContentRule(),
            new WordCountRule(),
        ];
    }
}
