<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker;

use Aratornv1\SeoChecker\Rules\CanonicalRule;
use Aratornv1\SeoChecker\Rules\H1Rule;
use Aratornv1\SeoChecker\Rules\HeadingHierarchyRule;
use Aratornv1\SeoChecker\Rules\HtmlLangRule;
use Aratornv1\SeoChecker\Rules\ImageAltRule;
use Aratornv1\SeoChecker\Rules\MetaDescriptionRule;
use Aratornv1\SeoChecker\Rules\OpenGraphRule;
use Aratornv1\SeoChecker\Rules\RobotsMetaRule;
use Aratornv1\SeoChecker\Rules\TitleRule;
use Aratornv1\SeoChecker\Rules\ViewportRule;

/**
 * The standard set of ten SEO rules.
 *
 * Keeping the list in one place lets the CLI and the tests share it, while
 * the Analyzer itself still knows nothing about concrete rules. A custom
 * audit can ignore this class and register any rules it likes.
 */
final class DefaultRules
{
    private function __construct()
    {
    }

    /**
     * @return list<RuleInterface>
     */
    public static function create(): array
    {
        return [
            new TitleRule(),
            new MetaDescriptionRule(),
            new H1Rule(),
            new HeadingHierarchyRule(),
            new ImageAltRule(),
            new CanonicalRule(),
            new RobotsMetaRule(),
            new ViewportRule(),
            new OpenGraphRule(),
            new HtmlLangRule(),
        ];
    }
}
