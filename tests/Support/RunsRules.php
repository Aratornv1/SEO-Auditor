<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker\Tests\Support;

use Aratornv1\SeoChecker\Outcome;
use Aratornv1\SeoChecker\RuleInterface;
use Aratornv1\SeoChecker\XPathFactory;

/**
 * Helper for rule tests: runs a rule against an HTML string.
 */
trait RunsRules
{
    private function runRule(RuleInterface $rule, string $html): Outcome
    {
        return $rule->check((new XPathFactory())->create($html));
    }

    private function assertStatus(RuleInterface $rule, string $html, string $expected): void
    {
        $result = $this->runRule($rule, $html);

        self::assertSame($expected, $result->status->value, 'Unexpected status, message: ' . $result->message);
        self::assertNotSame('', $result->message);
    }
}
