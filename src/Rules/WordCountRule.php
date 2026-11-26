<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker\Rules;

use Aratornv1\SeoChecker\Outcome;
use Aratornv1\SeoChecker\RuleInterface;
use Aratornv1\SeoChecker\Support\Text;
use DOMText;
use DOMXPath;

/**
 * Checks that the page has enough visible text.
 *
 * Pages with very little text ("thin content") rarely rank well: search
 * engines have nothing to understand the topic from. Text inside <script>,
 * <style>, <noscript> and <template> is not visible and is not counted.
 */
final class WordCountRule implements RuleInterface
{
    // Text nodes in <body> that a visitor actually sees.
    private const VISIBLE_TEXT = '//body//text()'
        . '[not(ancestor::script or ancestor::style or ancestor::noscript or ancestor::template)]';

    // A word: letters or digits, optionally joined by an apostrophe or a hyphen (п'ять, e-mail).
    private const WORD = "/[\\p{L}\\p{N}]+(?:['’ʼ-][\\p{L}\\p{N}]+)*/u";

    /**
     * The threshold is injected so the rule stays configurable without
     * modification (Open/Closed Principle).
     */
    public function __construct(
        private readonly int $minWords = 300,
    ) {
    }

    public function getName(): string
    {
        return 'Word count';
    }

    public function check(DOMXPath $xpath): Outcome
    {
        $count = $this->countWords($xpath);

        if ($count < $this->minWords) {
            return Outcome::warning(sprintf(
                'Замало тексту на сторінці (%s, рекомендовано щонайменше %d)',
                $this->words($count),
                $this->minWords,
            ));
        }

        return Outcome::success(sprintf('Тексту достатньо (%s)', $this->words($count)));
    }

    private function countWords(DOMXPath $xpath): int
    {
        $nodes = $xpath->query(self::VISIBLE_TEXT);
        if ($nodes === false) {
            return 0;
        }

        $text = '';
        foreach ($nodes as $node) {
            if ($node instanceof DOMText) {
                // A space between nodes, so "<b>one</b>two" is not glued into one word.
                $text .= ' ' . $node->data;
            }
        }

        $count = preg_match_all(self::WORD, $text);

        return $count === false ? 0 : $count;
    }

    private function words(int $count): string
    {
        return $count . ' ' . Text::plural($count, 'слово', 'слова', 'слів');
    }
}
