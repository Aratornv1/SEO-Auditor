<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker;

use Aratornv1\SeoChecker\Exception\FetchException;

/**
 * Source of page HTML.
 *
 * Analyzer depends on this abstraction instead of the network (Dependency
 * Inversion Principle): PageFetcher is used in production, a stub in tests.
 */
interface HtmlProviderInterface
{
    /**
     * @throws FetchException when the HTML cannot be obtained
     */
    public function fetch(string $url): string;
}
