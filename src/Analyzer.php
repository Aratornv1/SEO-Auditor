<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker;

use Aratornv1\SeoChecker\Exception\FetchException;
use DOMXPath;
use Throwable;

/**
 * The audit engine (the Context in the Strategy pattern).
 *
 * It does not know any concrete rule and works only with RuleInterface.
 * A new rule is just a new class plus one addRule() call.
 */
final class Analyzer
{
    /** @var list<RuleInterface> */
    private array $rules = [];

    public function __construct(
        private readonly HtmlProviderInterface $htmlProvider,
        private readonly XPathFactory $xpathFactory = new XPathFactory(),
    ) {
    }

    public function addRule(RuleInterface $rule): static
    {
        $this->rules[] = $rule;

        return $this;
    }

    /**
     * @return list<Result> results in the order the rules were registered
     *
     * @throws FetchException when the page cannot be downloaded
     */
    public function analyze(string $url): array
    {
        $xpath = $this->xpathFactory->create($this->htmlProvider->fetch($url));

        $results = [];
        foreach ($this->rules as $rule) {
            $results[] = $this->runRule($rule, $xpath);
        }

        return $results;
    }

    /**
     * A failure in one rule must not break the whole audit, so an exception
     * is converted into a result with the "error" status.
     */
    private function runRule(RuleInterface $rule, DOMXPath $xpath): Result
    {
        $name = $rule->getName();

        try {
            $outcome = $rule->check($xpath);
        } catch (Throwable $e) {
            return new Result($name, Status::Error, 'Правило завершилося з помилкою: ' . $e->getMessage());
        }

        return new Result($name, $outcome->status, $outcome->message);
    }
}
