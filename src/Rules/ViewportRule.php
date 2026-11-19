<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker\Rules;

use Aratornv1\SeoChecker\Outcome;
use Aratornv1\SeoChecker\RuleInterface;
use Aratornv1\SeoChecker\Support\MetaTagReader;
use DOMXPath;

/**
 * Checks the viewport meta tag.
 *
 * Google indexes the mobile version of a page first. Without a proper
 * viewport the page is rendered as a zoomed-out desktop layout and is
 * considered not mobile friendly.
 */
final class ViewportRule implements RuleInterface
{
    private readonly MetaTagReader $meta;

    public function __construct(?MetaTagReader $meta = null)
    {
        $this->meta = $meta ?? new MetaTagReader();
    }

    public function getName(): string
    {
        return 'Viewport';
    }

    public function check(DOMXPath $xpath): array
    {
        $tags = $this->meta->byName($xpath, 'viewport');

        if ($tags === []) {
            return Outcome::error('Мета-тег viewport відсутній (сторінка не адаптована під мобільні)');
        }

        // Use the first tag that actually has content.
        $content = '';
        foreach ($tags as $tag) {
            if (trim($tag) !== '') {
                $content = $tag;
                break;
            }
        }

        $directives = $this->parse($content);

        if (($directives['width'] ?? null) !== 'device-width') {
            return Outcome::warning('У viewport не задано width=device-width');
        }

        if ($this->blocksZoom($directives)) {
            return Outcome::warning('Viewport забороняє масштабування (user-scalable=no / maximum-scale=1), це погіршує доступність');
        }

        return Outcome::success('Viewport налаштовано коректно');
    }

    /**
     * Parses "width=device-width, initial-scale=1" into a lowercase map.
     *
     * @return array<string, string>
     */
    private function parse(string $content): array
    {
        $directives = [];

        foreach (preg_split('/[,;]/', $content) ?: [] as $part) {
            $pair = explode('=', $part, 2);
            if (count($pair) === 2) {
                $directives[strtolower(trim($pair[0]))] = strtolower(trim($pair[1]));
            }
        }

        return $directives;
    }

    /**
     * @param array<string, string> $directives
     */
    private function blocksZoom(array $directives): bool
    {
        if (in_array($directives['user-scalable'] ?? '', ['no', '0'], true)) {
            return true;
        }

        // A non-numeric value (e.g. "yes") is ignored by browsers; casting it
        // to float would wrongly give 0 and look like a zoom lock.
        return isset($directives['maximum-scale'])
            && is_numeric($directives['maximum-scale'])
            && (float) $directives['maximum-scale'] <= 1.0;
    }
}
