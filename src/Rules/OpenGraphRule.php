<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker\Rules;

use Aratornv1\SeoChecker\Outcome;
use Aratornv1\SeoChecker\RuleInterface;
use Aratornv1\SeoChecker\Support\MetaTagReader;
use Aratornv1\SeoChecker\Support\Text;
use DOMXPath;

/**
 * Checks the core Open Graph tags (og:title, og:description, og:image).
 *
 * They control how a link looks when shared in social networks and
 * messengers, which drives clicks and shares from those channels.
 */
final class OpenGraphRule implements RuleInterface
{
    private const REQUIRED = ['og:title', 'og:description', 'og:image'];

    private readonly MetaTagReader $meta;

    public function __construct(?MetaTagReader $meta = null)
    {
        $this->meta = $meta ?? new MetaTagReader();
    }

    public function getName(): string
    {
        return 'Open Graph';
    }

    public function check(DOMXPath $xpath): Outcome
    {
        $values = [];
        $missing = [];

        foreach (self::REQUIRED as $property) {
            $value = $this->firstFilled($this->meta->byProperty($xpath, $property));

            if ($value === null) {
                $missing[] = $property;
            } else {
                $values[$property] = $value;
            }
        }

        if (count($missing) === count(self::REQUIRED)) {
            return Outcome::warning('Open Graph не налаштовано (немає og:title, og:description, og:image)');
        }

        if ($missing !== []) {
            return Outcome::warning('Бракує Open Graph-тегів: ' . implode(', ', $missing));
        }

        if (preg_match('#^https?://#i', $values['og:image']) !== 1) {
            return Outcome::warning('og:image має бути абсолютною адресою (інакше соцмережі не покажуть зображення)');
        }

        return Outcome::success('Усі базові Open Graph-теги на місці');
    }

    /**
     * @param list<string> $contents
     */
    private function firstFilled(array $contents): ?string
    {
        foreach ($contents as $content) {
            $text = Text::normalize($content);
            if ($text !== '') {
                return $text;
            }
        }

        return null;
    }
}
