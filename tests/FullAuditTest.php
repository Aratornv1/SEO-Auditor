<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker\Tests;

use Aratornv1\SeoChecker\Analyzer;
use Aratornv1\SeoChecker\DefaultRules;
use Aratornv1\SeoChecker\ExtraRules;
use Aratornv1\SeoChecker\HtmlProviderInterface;
use Aratornv1\SeoChecker\Result;
use Aratornv1\SeoChecker\Status;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end check: the real set of ten rules against realistic pages.
 */
final class FullAuditTest extends TestCase
{
    public function testDefaultRuleSetHasTenUniqueRules(): void
    {
        $names = array_map(static fn ($rule) => $rule->getName(), DefaultRules::create());

        self::assertCount(10, $names);
        self::assertCount(10, array_unique($names), 'Rule names must be unique.');
    }

    public function testExtraRulesAreSeparateFromTheDefaultSet(): void
    {
        $default = array_map(static fn ($rule) => $rule->getName(), DefaultRules::create());
        $extra = array_map(static fn ($rule) => $rule->getName(), ExtraRules::create());

        self::assertSame(['Mixed content', 'Word count'], $extra);
        self::assertSame([], array_intersect($default, $extra), 'Optional rules must not repeat default ones.');
    }

    public function testWellOptimisedPagePassesEveryRule(): void
    {
        $results = $this->audit('good.html');

        self::assertCount(10, $results);

        foreach ($results as $result) {
            self::assertSame(
                Status::Success,
                $result->status,
                sprintf('%s should pass, got: %s', $result->ruleName, $result->message),
            );
        }
    }

    public function testBrokenPageIsFlaggedByEveryRule(): void
    {
        $statuses = [];
        foreach ($this->audit('bad.html') as $result) {
            $statuses[$result->ruleName] = $result->status;
        }

        self::assertSame([
            'Title' => Status::Warning,
            'Meta description' => Status::Warning,
            'H1' => Status::Warning,
            'Heading hierarchy' => Status::Warning,
            'Image alt' => Status::Error,
            'Canonical' => Status::Warning,
            'Robots meta' => Status::Error,
            'Viewport' => Status::Error,
            'Open Graph' => Status::Warning,
            'HTML lang' => Status::Warning,
        ], $statuses);
    }

    /**
     * @return list<Result>
     */
    private function audit(string $fixture): array
    {
        $html = (string) file_get_contents(__DIR__ . '/Fixtures/' . $fixture);

        $provider = new class ($html) implements HtmlProviderInterface {
            public function __construct(private readonly string $html)
            {
            }

            public function fetch(string $url): string
            {
                return $this->html;
            }
        };

        $analyzer = new Analyzer($provider);
        foreach (DefaultRules::create() as $rule) {
            $analyzer->addRule($rule);
        }

        return $analyzer->analyze('https://example.com');
    }
}
