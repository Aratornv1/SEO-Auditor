<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker;

/**
 * Responsible only for presenting results. New output formats (JSON, HTML)
 * are added as new implementations, without changing Analyzer.
 */
interface ReporterInterface
{
    /**
     * @param list<Result> $results
     */
    public function render(string $url, array $results): string;
}
