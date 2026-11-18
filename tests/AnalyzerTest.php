<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker\Tests;

use Aratornv1\SeoChecker\Analyzer;
use Aratornv1\SeoChecker\Exception\FetchException;
use Aratornv1\SeoChecker\HtmlProviderInterface;
use Aratornv1\SeoChecker\RuleInterface;
use Aratornv1\SeoChecker\Status;
use DOMXPath;
use PHPUnit\Framework\TestCase;

final class AnalyzerTest extends TestCase
{
    public function testRunsAllRulesInRegistrationOrder(): void
    {
        $analyzer = (new Analyzer($this->providerReturning('<html><head><title>x</title></head></html>')))
            ->addRule($this->rule('First', 'success', 'ok'))
            ->addRule($this->rule('Second', 'warning', 'hmm'))
            ->addRule($this->rule('Third', 'error', 'bad'));

        $results = $analyzer->analyze('https://example.com');

        self::assertCount(3, $results);
        self::assertSame(['First', 'Second', 'Third'], array_map(static fn ($r) => $r->ruleName, $results));
        self::assertSame(Status::Success, $results[0]->status);
        self::assertSame(Status::Warning, $results[1]->status);
        self::assertSame(Status::Error, $results[2]->status);
        self::assertSame('hmm', $results[1]->message);
    }

    public function testRuleReceivesParsedDocument(): void
    {
        $rule = new class implements RuleInterface {
            public function getName(): string
            {
                return 'Probe';
            }

            public function check(DOMXPath $xpath): array
            {
                return ['status' => 'success', 'message' => (string) $xpath->evaluate('string(//head/title)')];
            }
        };

        $results = (new Analyzer($this->providerReturning('<html><head><title>Hello</title></head></html>')))
            ->addRule($rule)
            ->analyze('https://example.com');

        self::assertSame('Hello', $results[0]->message);
    }

    public function testExceptionInRuleBecomesErrorResult(): void
    {
        $broken = new class implements RuleInterface {
            public function getName(): string
            {
                return 'Broken';
            }

            public function check(DOMXPath $xpath): array
            {
                throw new \RuntimeException('boom');
            }
        };

        $results = (new Analyzer($this->providerReturning('<html></html>')))
            ->addRule($broken)
            ->addRule($this->rule('Healthy', 'success', 'ok'))
            ->analyze('https://example.com');

        self::assertSame(Status::Error, $results[0]->status);
        self::assertStringContainsString('boom', $results[0]->message);
        // The failing rule must not prevent the next one from running.
        self::assertSame(Status::Success, $results[1]->status);
    }

    public function testInvalidRuleOutputBecomesErrorResult(): void
    {
        $results = (new Analyzer($this->providerReturning('<html></html>')))
            ->addRule($this->rule('BadStatus', 'great', 'ok'))
            ->analyze('https://example.com');

        self::assertSame(Status::Error, $results[0]->status);
    }

    public function testFetchFailureIsPropagated(): void
    {
        $provider = new class implements HtmlProviderInterface {
            public function fetch(string $url): string
            {
                throw new FetchException('offline');
            }
        };

        $this->expectException(FetchException::class);

        (new Analyzer($provider))->analyze('https://example.com');
    }

    private function providerReturning(string $html): HtmlProviderInterface
    {
        return new class($html) implements HtmlProviderInterface {
            public function __construct(private readonly string $html)
            {
            }

            public function fetch(string $url): string
            {
                return $this->html;
            }
        };
    }

    private function rule(string $name, string $status, string $message): RuleInterface
    {
        return new class($name, $status, $message) implements RuleInterface {
            public function __construct(
                private readonly string $name,
                private readonly string $status,
                private readonly string $message,
            ) {
            }

            public function getName(): string
            {
                return $this->name;
            }

            public function check(DOMXPath $xpath): array
            {
                return ['status' => $this->status, 'message' => $this->message];
            }
        };
    }
}
