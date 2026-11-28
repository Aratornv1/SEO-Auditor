<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker\Tests\Support;

use Aratornv1\SeoChecker\Exception\FetchException;
use Aratornv1\SeoChecker\HtmlProviderInterface;

/**
 * Serves the given pages and fails with FetchException for every other URL.
 * Remembers which URLs were requested, so tests can check what was fetched.
 */
final class StubHtmlProvider implements HtmlProviderInterface
{
    /** @var list<string> */
    public array $requested = [];

    /**
     * @param array<string, string> $pages URL => HTML
     */
    public function __construct(private readonly array $pages = [])
    {
    }

    public function fetch(string $url): string
    {
        $this->requested[] = $url;

        return $this->pages[$url] ?? throw new FetchException('Could not download ' . $url);
    }
}
