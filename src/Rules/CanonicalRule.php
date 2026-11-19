<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker\Rules;

use Aratornv1\SeoChecker\Outcome;
use Aratornv1\SeoChecker\RuleInterface;
use Aratornv1\SeoChecker\Support\MetaTagReader;
use Aratornv1\SeoChecker\Support\Text;
use Aratornv1\SeoChecker\Support\XPathFunctions;
use DOMElement;
use DOMXPath;

/**
 * Checks <link rel="canonical"> and that the page declares one main address.
 *
 * The canonical URL tells search engines which version of a page is the main
 * one, which protects from duplicate-content problems caused by URL
 * parameters, tracking tags or www / non-www variants. A page can name its
 * address in several places (canonical, og:url); when they disagree, the
 * search engine and the social networks get different "main" links for the
 * same content, so the rule reports the mismatch.
 */
final class CanonicalRule implements RuleInterface
{
    // How many conflicting addresses are listed in a message.
    private const MAX_LISTED_URLS = 3;

    private readonly MetaTagReader $meta;

    public function __construct(?MetaTagReader $meta = null)
    {
        $this->meta = $meta ?? new MetaTagReader();
    }

    public function getName(): string
    {
        return 'Canonical';
    }

    public function check(DOMXPath $xpath): array
    {
        $links = $this->findCanonicalLinks($xpath);

        if ($links === []) {
            return Outcome::warning('Тег <link rel="canonical"> відсутній');
        }

        $unique = array_values(array_unique($links));

        // Several different addresses for the same page: there is no single
        // main link, and search engines may ignore all of them.
        if (count($unique) > 1) {
            return Outcome::error(sprintf(
                'Знайдено кілька різних canonical-адрес, пошуковик може проігнорувати їх усі: %s',
                $this->listUrls($unique),
            ));
        }

        if (count($links) > 1) {
            return Outcome::warning(sprintf('Тег canonical продубльовано (знайдено %d)', count($links)));
        }

        $href = $unique[0];

        if ($href === '') {
            return Outcome::error('Атрибут href у canonical порожній');
        }

        if (preg_match('#^https?://[^\s/]+#i', $href) !== 1) {
            return Outcome::warning(sprintf('Canonical має бути абсолютною адресою, зараз: %s', $href));
        }

        $ogUrl = $this->findOgUrl($xpath);

        if ($ogUrl !== null && $this->normalizeUrl($ogUrl) !== $this->normalizeUrl($href)) {
            return Outcome::warning(sprintf(
                'Canonical (%s) і og:url (%s) вказують на різні адреси; '
                . 'головною вважається canonical, вирівняйте og:url',
                $href,
                $ogUrl,
            ));
        }

        return Outcome::success($ogUrl !== null
            ? sprintf('Canonical вказує на %s (збігається з og:url)', $href)
            : sprintf('Canonical вказує на %s', $href));
    }

    /**
     * rel may hold several tokens (rel="canonical alternate"), so match a token.
     * Only <head> is searched: search engines ignore a canonical link that
     * appears inside <body>, so it must not count here either.
     *
     * @return list<string>
     */
    private function findCanonicalLinks(DOMXPath $xpath): array
    {
        $query = sprintf(
            "//head/link[contains(concat(' ', normalize-space(%s), ' '), ' canonical ')]",
            XPathFunctions::lower('@rel'),
        );

        $nodes = $xpath->query($query);
        if ($nodes === false) {
            return [];
        }

        $links = [];
        foreach ($nodes as $node) {
            if ($node instanceof DOMElement) {
                $links[] = trim($node->getAttribute('href'));
            }
        }

        return $links;
    }

    /**
     * The first non-empty absolute og:url, or null when there is nothing to
     * compare with. An empty or relative og:url is not this rule's concern
     * (OpenGraphRule covers Open Graph quality).
     */
    private function findOgUrl(DOMXPath $xpath): ?string
    {
        foreach ($this->meta->byProperty($xpath, 'og:url') as $content) {
            $url = Text::normalize($content);

            if ($url !== '' && preg_match('#^https?://[^\s/]+#i', $url) === 1) {
                return $url;
            }
        }

        return null;
    }

    /**
     * Brings two spellings of the same address to one form: scheme and host
     * are case-insensitive, a trailing slash and a #fragment do not change
     * the page, and a default port is the same as no port.
     */
    private function normalizeUrl(string $url): string
    {
        $parts = parse_url($url);

        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            return $url;
        }

        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host']);

        $defaultPort = $scheme === 'https' ? 443 : 80;
        $port = isset($parts['port']) && $parts['port'] !== $defaultPort ? ':' . $parts['port'] : '';

        $path = rtrim($parts['path'] ?? '', '/');
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';

        return $scheme . '://' . $host . $port . $path . $query;
    }

    /**
     * @param list<string> $urls
     */
    private function listUrls(array $urls): string
    {
        $shown = array_map(
            static fn (string $url): string => $url === '' ? '(порожній href)' : $url,
            array_slice($urls, 0, self::MAX_LISTED_URLS),
        );

        $suffix = count($urls) > self::MAX_LISTED_URLS
            ? sprintf(' і ще %d', count($urls) - self::MAX_LISTED_URLS)
            : '';

        return implode(', ', $shown) . $suffix;
    }
}
