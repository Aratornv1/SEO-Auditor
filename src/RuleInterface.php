<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker;

use DOMXPath;

/**
 * Contract for a single SEO rule (the Strategy interface).
 *
 * Every check is a separate class implementing this interface. The engine
 * (Analyzer) only knows the contract and nothing about concrete rules, so new
 * checks can be added without touching the engine (Open/Closed Principle).
 */
interface RuleInterface
{
    /**
     * Short human-readable name of the check, shown in the report (e.g. "Title").
     */
    public function getName(): string;

    /**
     * Runs the check against the page DOM.
     */
    public function check(DOMXPath $xpath): Outcome;
}
