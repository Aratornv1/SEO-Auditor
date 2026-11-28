<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker\Rules;

use Aratornv1\SeoChecker\Outcome;
use Aratornv1\SeoChecker\RuleInterface;
use Aratornv1\SeoChecker\Support\MetaTagReader;
use Aratornv1\SeoChecker\Support\LengthCheck;
use Aratornv1\SeoChecker\Support\Text;
use DOMXPath;

/**
 * Checks the meta description presence and length.
 *
 * It does not influence ranking directly, but it usually becomes the snippet
 * text in search results and therefore affects the click-through rate.
 */
final class MetaDescriptionRule implements RuleInterface
{
    private readonly MetaTagReader $meta;

    public function __construct(
        private readonly int $minLength = 70,
        private readonly int $maxLength = 160,
        ?MetaTagReader $meta = null,
    ) {
        $this->meta = $meta ?? new MetaTagReader();
    }

    public function getName(): string
    {
        return 'Meta description';
    }

    public function check(DOMXPath $xpath): Outcome
    {
        $descriptions = $this->meta->byName($xpath, 'description');

        if ($descriptions === []) {
            return Outcome::warning('The meta description tag is missing');
        }

        $filled = array_values(array_filter(
            array_map([Text::class, 'normalize'], $descriptions),
            static fn (string $text): bool => $text !== '',
        ));

        // Every description tag is empty.
        if ($filled === []) {
            return Outcome::warning('The meta description tag is empty');
        }

        if (count($descriptions) > 1) {
            return Outcome::warning(sprintf(
                'Several meta description tags found (%d), there should be only one',
                count($descriptions),
            ));
        }

        return LengthCheck::outcome(
            $filled[0],
            $this->minLength,
            $this->maxLength,
            'Description',
            ', search engines will truncate it',
        );
    }
}
